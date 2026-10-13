<?php

namespace App\Http\Controllers\WebChat;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\WebChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Endpoint publik widget web chat (guest maupun user login).
 * Guest dikenali lewat visitor_key acak yang disimpan di localStorage browser;
 * user login dikenali dari token JWT (opsional di semua endpoint ini).
 */
class WebChatController extends Controller
{
    // Widget menampilkan riwayat 24 jam terakhir; sisanya lewat "muat chat sebelumnya".
    const RECENT_HOURS = 24;

    /** Buat / lanjutkan sesi chat. */
    public function session(Request $request): JsonResponse
    {
        $user = $this->currentUser();

        if ($user) {
            $session = ChatSession::where('user_uuid', $user->uuid)->orderByDesc('last_message_at')->first();
            if (!$session) {
                $session = ChatSession::create([
                    'user_uuid' => $user->uuid,
                    'name' => $user->name ?: ($user->username ?: 'Pengguna'),
                    'phone' => $user->mobile_number,
                ]);
                WebChat::runBot($session, '');
            } else {
                WebChat::recoverIfEngineFailed($session);
            }
            return $this->sessionResponse($session);
        }

        $validator = Validator::make($request->all(), [
            'visitor_key' => 'required|string|min:20|max:100',
            'name' => 'nullable|string|max:100',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
        ], [
            'phone.regex' => 'Nomor HP tidak valid',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $hash = ChatSession::hashVisitorKey($request->visitor_key);
        $session = ChatSession::whereNull('user_uuid')->where('visitor_key_hash', $hash)->first();
        $name = trim((string) $request->name);

        if (!$session) {
            if ($name === '') {
                // Belum pernah chat → frontend harus menampilkan form nama dulu.
                return response()->json(['message' => 'Nama wajib diisi', 'need_profile' => true], 422);
            }
            $session = ChatSession::create([
                'visitor_key_hash' => $hash,
                'name' => $name,
                'phone' => $request->phone ?: null,
            ]);
            WebChat::runBot($session, '');
        } else {
            if ($name !== '') {
                $session->update(['name' => $name, 'phone' => $request->phone ?: $session->phone]);
            }
            WebChat::recoverIfEngineFailed($session);
        }

        return $this->sessionResponse($session);
    }

    /** Riwayat: default 24 jam terakhir, ?before={uuid} untuk yang lebih lama. */
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

    /** Kirim pesan user → balasan otomatis bot. */
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

    /** "Mulai ulang": reset menu bot ke menu utama (riwayat tetap tersimpan). */
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

    /** User beralih ke WhatsApp admin: catat & kembalikan link wa.me berisi link riwayat chat. */
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

    /** Sesi milik pemanggil: user login (JWT) atau guest (visitor_key cocok). */
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

    /** JWT opsional: null kalau tidak login / token tidak valid. */
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
