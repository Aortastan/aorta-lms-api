<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\WebChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ChatSession::query();

        if ($search = trim((string) $request->query('search'))) {
            $search = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('last_message_preview', 'like', "%{$search}%");
            });
        }

        $filter = $request->query('filter');
        if ($filter === 'unread') {
            $this->whereUnread($query);
        } elseif ($filter === 'handoff') {
            $this->whereHandoffActive($query);
        }

        $chats = $query->orderByDesc('last_message_at')->paginate(30);

        $allTotal = ChatSession::count();
        $unreadTotal = $this->whereUnread(ChatSession::query())->count();
        $handoffTotal = $this->whereHandoffActive(ChatSession::query())->count();

        return response()->json([
            'message' => 'Success get data',
            'chats' => collect($chats->items())->map(function ($s) {
                return $this->formatSession($s);
            }),
            'meta' => [
                'current_page' => $chats->currentPage(),
                'last_page' => $chats->lastPage(),
                'total' => $chats->total(),
                'all_total' => $allTotal,
                'unread_total' => $unreadTotal,
                'handoff_total' => $handoffTotal,
            ],
        ], 200);
    }

    public function byCode($code): JsonResponse
    {
        $uuid = ChatSession::where('share_code', (string) $code)->value('uuid');
        if (!$uuid) {
            return response()->json(['message' => 'Link chat tidak valid'], 404);
        }

        return response()->json(['message' => 'Success get data', 'uuid' => $uuid], 200);
    }

    public function show($uuid): JsonResponse
    {
        $session = ChatSession::find($uuid);
        if (!$session) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        if ($session->user_uuid) {
            $session->load('user:uuid,name,username,email');
        }
        $session->admin_read_at = now();
        $session->save();

        $phone = WebChat::normalizePhone((string) $session->phone);

        return response()->json([
            'message' => 'Success get data',
            'chat' => $this->formatSession($session) + [
                'user' => $session->user ? [
                    'name' => $session->user->name,
                    'username' => $session->user->username,
                    'email' => $session->user->email,
                ] : null,
                'wa_user_url' => $phone ? 'https://wa.me/' . $phone : null,
            ],
        ], 200);
    }

    public function messages(Request $request, $uuid): JsonResponse
    {
        $session = ChatSession::find($uuid);
        if (!$session) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        $before = $request->query('before');
        $page = WebChat::page($session, $before ?: null, null, true);

        if (!$before && $request->query('mark_read') && $page['messages']) {
            $newest = Carbon::parse(end($page['messages'])['created_at']);
            if (!$session->admin_read_at || $newest->gt($session->admin_read_at)) {
                $session->admin_read_at = $newest;
                $session->save();
            }
        }

        return response()->json(['message' => 'Success get data'] + $page);
    }

    public function resolve($uuid): JsonResponse
    {
        $session = ChatSession::find($uuid);
        if (!$session) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        $session->handoff_at = null;
        $session->save();
        $admin = auth()->user();
        $note = WebChat::addMessage($session, 'note', 'Ditandai selesai oleh ' . ($admin->name ?? 'admin'));

        return response()->json([
            'message' => 'Success',
            'chat' => $this->formatSession($session),
            'note' => WebChat::format($note),
        ], 200);
    }

    private function whereHandoffActive($query)
    {
        return $query->where('handoff_at', '>=', now()->subHours(WebChat::HANDOFF_ACTIVE_HOURS));
    }

    private function whereUnread($query)
    {
        return $query->whereNotNull('last_user_message_at')->where(function ($q) {
            $q->whereNull('admin_read_at')->orWhereColumn('last_user_message_at', '>', 'admin_read_at');
        });
    }

    private function formatSession(ChatSession $s): array
    {
        return [
            'uuid' => $s->uuid,
            'name' => $s->name,
            'phone' => $s->phone,
            'is_member' => (bool) $s->user_uuid,
            'last_message_preview' => $s->last_message_preview,
            'last_message_at' => optional($s->last_message_at)->toIso8601String(),
            'handoff_at' => optional($s->handoff_at)->toIso8601String(),
            'handoff_active' => WebChat::isHandoffActive($s),
            'unread' => $s->last_user_message_at
                && (!$s->admin_read_at || $s->last_user_message_at->gt($s->admin_read_at)),
            'created_at' => optional($s->created_at)->toIso8601String(),
        ];
    }
}
