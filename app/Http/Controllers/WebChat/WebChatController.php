<?php

namespace App\Http\Controllers\WebChat;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\WebChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class WebChatController extends Controller
{
    const RECENT_HOURS = 24;

    public function session(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $phoneRule = ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'];
        $messages = ['phone.regex' => 'Nomor HP tidak valid'];

        if ($user) {
            $validator = Validator::make($request->all(), ['phone' => $phoneRule], $messages);
            if ($validator->fails()) {
                return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            $phone = trim((string) $request->phone) ?: null;
            $accountPhone = trim((string) $user->mobile_number) ?: null;
            $session = ChatSession::where('user_uuid', $user->uuid)->first();
            if (!$session) {
                $session = $this->createOnce(['user_uuid' => $user->uuid], [
                    'name' => $user->name ?: ($user->username ?: 'Pengguna'),
                    'phone' => $phone ?: $accountPhone,
                ]);
            } else {
                $fill = $phone ?: ($session->phone ? null : $accountPhone);
                if ($fill && $fill !== $session->phone) {
                    $session->update(['phone' => $fill]);
                }
                WebChat::recoverIfEngineFailed($session);
            }
            return $this->sessionResponse($session);
        }

        $validator = Validator::make($request->all(), [
            'visitor_key' => 'required|string|min:20|max:100',
            'name' => 'nullable|string|max:100',
            'phone' => $phoneRule,
        ], $messages);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $hash = ChatSession::hashVisitorKey($request->visitor_key);
        $session = ChatSession::whereNull('user_uuid')->where('visitor_key_hash', $hash)->first();
        $name = trim((string) $request->name);

        if (!$session) {
            if ($name === '') {
                return response()->json(['message' => 'Nama wajib diisi', 'need_profile' => true], 422);
            }
            $session = $this->createOnce(['visitor_key_hash' => $hash], [
                'name' => $name,
                'phone' => $request->phone ?: null,
            ]);
        } else {
            if ($name !== '') {
                $session->update(['name' => $name, 'phone' => $request->phone ?: $session->phone]);
            }
            WebChat::recoverIfEngineFailed($session);
        }

        return $this->sessionResponse($session);
    }

    public function messages(Request $request, $uuid): JsonResponse
    {
        $session = $this->ownedSession($request, $uuid);
        if (!$session) {
            return response()->json(['message' => 'Sesi chat tidak ditemukan'], 404);
        }
        $before = $request->query('before');
        $page = WebChat::page($session, $before ?: null, $before ? null : self::RECENT_HOURS);

        return response()->json(['message' => 'Success get data'] + $page);
    }

    public function send(Request $request, $uuid): JsonResponse
    {
        $session = $this->ownedSession($request, $uuid);
        if (!$session) {
            return response()->json(['message' => 'Sesi chat tidak ditemukan'], 404);
        }
        $validator = Validator::make($request->all(), ['text' => 'required|string|max:2000']);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $userMessage = WebChat::addMessage($session, 'user', trim($request->text));
        $bot = WebChat::runBot($session, trim($request->text));

        return response()->json([
            'message' => 'Success',
            'messages' => array_map([WebChat::class, 'format'], array_merge([$userMessage], $bot['messages'])),
            'handoff' => $bot['handoff'],
            'not_understood' => $bot['not_understood'],
        ]);
    }

    public function reset(Request $request, $uuid): JsonResponse
    {
        $session = $this->ownedSession($request, $uuid);
        if (!$session) {
            return response()->json(['message' => 'Sesi chat tidak ditemukan'], 404);
        }
        $session->bot_state = null;
        $bot = WebChat::runBot($session, '');

        return response()->json([
            'message' => 'Success',
            'messages' => array_map([WebChat::class, 'format'], $bot['messages']),
        ]);
    }

    public function handoff(Request $request, $uuid): JsonResponse
    {
        $session = $this->ownedSession($request, $uuid);
        if (!$session) {
            return response()->json(['message' => 'Sesi chat tidak ditemukan'], 404);
        }
        $waUrl = WebChat::adminWaUrl($session);
        if (!$waUrl) {
            return response()->json(['message' => 'Nomor WhatsApp admin belum diatur'], 503);
        }
        $session->handoff_at = now();
        $message = WebChat::addMessage($session, 'system', 'Beralih ke WhatsApp admin');

        return response()->json([
            'message' => 'Success',
            'wa_url' => $waUrl,
            'messages' => [WebChat::format($message)],
        ]);
    }

    private function createOnce(array $key, array $attributes): ChatSession
    {
        try {
            $session = ChatSession::create($key + $attributes);
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            return ChatSession::where($key)->firstOrFail();
        }
        WebChat::runBot($session, '');

        return $session;
    }

    private function sessionResponse(ChatSession $session): JsonResponse
    {
        return response()->json([
            'message' => 'Success',
            'session' => [
                'uuid' => $session->uuid,
                'name' => $session->name,
                'phone' => $session->phone,
                'is_member' => (bool) $session->user_uuid,
            ],
            'admin_wa_available' => (bool) WebChat::normalizePhone((string) config('services.webchat.admin_wa')),
        ]);
    }

    private function ownedSession(Request $request, $uuid): ?ChatSession
    {
        $session = ChatSession::find($uuid);
        if (!$session) {
            return null;
        }
        if ($session->user_uuid) {
            $user = $this->currentUser();
            return $user && $user->uuid === $session->user_uuid ? $session : null;
        }
        $key = (string) ($request->input('visitor_key') ?: $request->header('x-visitor-key'));

        return $key !== '' && hash_equals($session->visitor_key_hash, ChatSession::hashVisitorKey($key)) ? $session : null;
    }

    private function currentUser()
    {
        if (!request()->bearerToken()) {
            return null;
        }
        try {
            return JWTAuth::parseToken()->authenticate() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
