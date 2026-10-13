<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Str;

class WebChat
{
    const PAGE_SIZE = 30;
    const HANDOFF_ACTIVE_HOURS = 24;

    public static function addMessage(ChatSession $session, string $sender, ?string $body, ?array $media = null, ?array $meta = null): ChatMessage
    {
        $message = ChatMessage::create([
            'chat_session_uuid' => $session->uuid,
            'sender' => $sender,
            'body' => $body,
            'media' => $media,
            'meta' => $meta,
        ]);

        if ($sender === 'note') {
            return $message;
        }

        $preview = $body !== null && trim($body) !== '' ? $body : ($media['name'] ?? '');
        $session->last_message_preview = Str::limit(preg_replace('/\s+/u', ' ', strip_tags($preview)), 120);
        $session->last_message_at = $message->created_at;
        if ($sender === 'user') {
            $session->last_user_message_at = $message->created_at;
        }
        $session->save();

        return $message;
    }

    public static function runBot(ChatSession $session, string $text, ?array $result = null): array
    {
        $result = $result ?: BotEngine::reply($session, $text);

        if (is_array($result['state'])) {
            $session->bot_state = $result['state'];
        }

        $messages = [];
        $last = count($result['replies']) - 1;
        foreach ($result['replies'] as $i => $reply) {
            $meta = null;
            if ($i === $last && ($result['handoff'] || $result['not_understood'])) {
                $meta = ['handoff' => $result['handoff'], 'not_understood' => $result['not_understood']];
                if (!empty($result['engine_error'])) {
                    $meta['engine_error'] = true;
                }
            }
            $messages[] = self::addMessage($session, 'bot', $reply['text'], $reply['media'], $meta);
        }
        $session->save();

        return [
            'messages' => $messages,
            'handoff' => $result['handoff'],
            'not_understood' => $result['not_understood'],
        ];
    }

    public static function recoverIfEngineFailed(ChatSession $session): bool
    {
        $last = ChatMessage::where('chat_session_uuid', $session->uuid)->where('sender', 'bot')->orderByDesc('id')->first();
        $failed = $last && (!empty($last->meta['engine_error']) || $last->body === BotEngine::ENGINE_ERROR_TEXT);
        if (!$failed) {
            return false;
        }

        $session->bot_state = null;
        $result = BotEngine::reply($session, '');
        if (!empty($result['engine_error'])) {
            return false;
        }
        self::runBot($session, '', $result);

        return true;
    }

    public static function page(ChatSession $session, ?string $beforeUuid = null, ?int $recentHours = null, bool $withNotes = false): array
    {
        $scope = function ($q) use ($session, $withNotes) {
            $q->where('chat_session_uuid', $session->uuid);
            if (!$withNotes) {
                $q->where('sender', '!=', 'note');
            }
        };
        $query = ChatMessage::where($scope);
        $beforeId = null;

        if ($beforeUuid) {
            $beforeId = ChatMessage::where($scope)->where('uuid', $beforeUuid)->value('id');
            if (!$beforeId) {
                return ['messages' => [], 'has_more' => false];
            }
            $query->where('id', '<', $beforeId)->orderByDesc('id')->limit(self::PAGE_SIZE);
        } elseif ($recentHours) {
            $query->where('created_at', '>=', now()->subHours($recentHours))->orderByDesc('id')->limit(200);
        } else {
            $query->orderByDesc('id')->limit(self::PAGE_SIZE);
        }

        $rows = $query->get();
        if (!$beforeUuid && $recentHours && $rows->count() < self::PAGE_SIZE) {
            $rows = ChatMessage::where($scope)->orderByDesc('id')->limit(self::PAGE_SIZE)->get();
        }
        $rows = $rows->reverse()->values();
        $oldestId = $rows->isEmpty() ? ($beforeId ?: PHP_INT_MAX) : $rows->first()->id;
        $hasMore = ChatMessage::where($scope)->where('id', '<', $oldestId)->exists();

        return [
            'messages' => $rows->map(function ($m) {
                return self::format($m);
            })->all(),
            'has_more' => $hasMore,
        ];
    }

    public static function format(ChatMessage $m): array
    {
        return [
            'uuid' => $m->uuid,
            'sender' => $m->sender,
            'body' => $m->body,
            'media' => $m->media,
            'meta' => $m->meta,
            'created_at' => optional($m->created_at)->toIso8601String(),
        ];
    }

    public static function isHandoffActive(ChatSession $session): bool
    {
        return $session->handoff_at && $session->handoff_at->gte(now()->subHours(self::HANDOFF_ACTIVE_HOURS));
    }

    public static function adminChatUrl(ChatSession $session): string
    {
        if (!$session->share_code) {
            $session->share_code = ChatSession::newShareCode();
            $session->save();
        }

        return rtrim((string) config('services.webchat.site_url'), '/') . '/r/?c=' . $session->share_code;
    }

    public static function adminWaUrl(ChatSession $session): ?string
    {
        $number = self::normalizePhone((string) config('services.webchat.admin_wa'));
        if (!$number) {
            return null;
        }
        $name = self::stripEmoji($session->name) ?: 'Pengunjung';
        $text = "Halo Admin Aorta,\n"
            . 'Saya *' . $name . '*' . ($session->phone ? ' (' . $session->phone . ')' : '') . " butuh bantuan.\n\n"
            . "Riwayat chat saya di web Aorta:\n" . self::adminChatUrl($session);

        return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
    }

    public static function stripEmoji(?string $text): string
    {
        $clean = preg_replace('/[\p{So}\p{Cs}\x{10000}-\x{10FFFF}\x{FE0F}\x{200D}]/u', '', (string) $text);

        return trim(preg_replace('/\s+/u', ' ', (string) $clean));
    }

    public static function normalizePhone(string $phone): string
    {
        $n = preg_replace('/\D/', '', $phone);
        if ($n === '') {
            return '';
        }
        if (substr($n, 0, 1) === '0') {
            $n = '62' . substr($n, 1);
        }
        return $n;
    }
}
