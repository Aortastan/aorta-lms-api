<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Str;

/**
 * Logika bersama web chat (dipakai controller publik & admin).
 */
class WebChat
{
    const PAGE_SIZE = 30;
    // Status "minta admin" aktif selama 24 jam sejak user klik Chat Admin (kecuali ditandai selesai).
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

    /**
     * Jalankan mesin bot untuk $text, simpan balasannya, perbarui state menu.
     * @return array{messages: ChatMessage[], handoff: bool, not_understood: bool}
     */
    public static function runBot(ChatSession $session, string $text, ?array $result = null): array
    {
        $result = $result ?: BotEngine::reply($session, $text);

        if (is_array($result['state'])) {
            $session->bot_state = $result['state'];
        }

        $messages = [];
        $last = count($result['replies']) - 1;
        foreach ($result['replies'] as $i => $reply) {
            // Flag handoff / tak dimengerti ditempel di bubble terakhir supaya tombol WA
            // muncul di bawahnya (juga saat riwayat dimuat ulang).
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

    /**
     * Balasan bot terakhir = "chat otomatis sedang gangguan"? Cek ulang ke mesin bot;
     * kalau sudah pulih kirim menu utama. Kalau masih mati, tidak menambah pesan apa pun
     * (supaya tidak dobel tiap refresh).
     */
    public static function recoverIfEngineFailed(ChatSession $session): bool
    {
        $last = ChatMessage::where('chat_session_uuid', $session->uuid)->where('sender', 'bot')->orderByDesc('id')->first();
        $failed = $last && (!empty($last->meta['engine_error']) || $last->body === BotEngine::ENGINE_ERROR_TEXT);
        if (!$failed) {
            return false;
        }

        $session->bot_state = null; // mulai lagi dari menu utama
        $result = BotEngine::reply($session, '');
        if (!empty($result['engine_error'])) {
            return false;
        }
        self::runBot($session, '', $result);

        return true;
    }

    /**
     * Ambil satu halaman pesan (urut lama → baru).
     * - $beforeUuid diisi  → PAGE_SIZE pesan sebelum pesan itu ("muat lebih lama").
     * - $recentHours diisi → semua pesan N jam terakhir (maks 200).
     * - keduanya kosong    → PAGE_SIZE pesan terbaru.
     * @return array{messages: array, has_more: bool}
     */
    public static function page(ChatSession $session, ?string $beforeUuid = null, ?int $recentHours = null, bool $withNotes = false): array
    {
        // Catatan admin hanya untuk halaman Kelola Chat, tidak tampil di widget user.
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

        $rows = $query->get()->reverse()->values();
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

    /**
     * Link riwayat chat untuk pesan WA admin. Berisi kode acak (bukan uuid sesi);
     * halaman /r menerjemahkan kode → chatroom di Kelola Chat (khusus admin).
     */
    public static function adminChatUrl(ChatSession $session): string
    {
        if (!$session->share_code) {
            $session->share_code = ChatSession::newShareCode();
            $session->save();
        }

        return rtrim((string) config('services.webchat.site_url'), '/') . '/r/?c=' . $session->share_code;
    }

    /** wa.me ke nomor admin, isi pesan berisi link riwayat chat. null kalau nomor admin belum di-set. */
    public static function adminWaUrl(ChatSession $session): ?string
    {
        $number = self::normalizePhone((string) config('services.webchat.admin_wa'));
        if (!$number) {
            return null;
        }
        // Tanpa emoji: redirect wa.me merusak emoji (jadi "�") di WhatsApp Web/Desktop.
        $name = self::stripEmoji($session->name) ?: 'Pengunjung';
        $text = "Halo Admin Aorta,\n"
            . 'Saya *' . $name . '*' . ($session->phone ? ' (' . $session->phone . ')' : '') . " butuh bantuan.\n\n"
            . "Riwayat chat saya di web Aorta:\n" . self::adminChatUrl($session);

        return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
    }

    /** Buang emoji/simbol (redirect wa.me mengubahnya jadi "�"); huruf biasa tetap. */
    public static function stripEmoji(?string $text): string
    {
        $clean = preg_replace('/[\p{So}\p{Cs}\x{10000}-\x{10FFFF}\x{FE0F}\x{200D}]/u', '', (string) $text);

        return trim(preg_replace('/\s+/u', ' ', (string) $clean));
    }

    /** 0812.. / +62 812.. / 62812.. → 62812.. ('' kalau kosong) */
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
