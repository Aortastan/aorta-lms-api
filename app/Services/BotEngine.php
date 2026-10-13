<?php

namespace App\Services;

use App\Models\ChatSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien mesin balasan otomatis web chat (bot-baileys POST /engine/reply).
 * Menu & template sama dengan bot WA dan diedit dari dashboard bot.
 * Request ditandatangani HMAC-SHA256(BOT_SSO_SECRET, "{ts}.{body}").
 */
class BotEngine
{
    // Balasan saat mesin bot tidak bisa dihubungi (juga dipakai untuk mendeteksi pesan lama).
    const ENGINE_ERROR_TEXT = "Maaf kak, chat otomatis sedang gangguan 🙏\n\nSilakan klik tombol *Chat Admin via WhatsApp* di bawah supaya dibantu langsung oleh admin ya.";

    /**
     * @param string $text pesan user; '' = sesi baru / mulai ulang (menu utama)
     * @return array{replies: array, state: array|null, handoff: bool, not_understood: bool, engine_error: bool}
     */
    public static function reply(ChatSession $session, string $text): array
    {
        $baseUrl = rtrim((string) config('services.webchat.bot_url'), '/');
        // URL lampiran harus bisa dibuka browser; bisa beda dengan alamat internal bot.
        $publicUrl = rtrim((string) (config('services.webchat.bot_public_url') ?: $baseUrl), '/');
        $secret = (string) config('services.bot_sso_secret');

        $body = json_encode([
            'sessionId' => $session->uuid,
            'text' => $text,
            'name' => $session->name,
            'state' => $session->bot_state,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ts = (string) round(microtime(true) * 1000);

        try {
            if (!$baseUrl || !$secret) {
                throw new \RuntimeException('WEBCHAT_BOT_URL / BOT_SSO_SECRET belum di-set');
            }
            // Timeout pendek: selama menunggu bot, satu worker PHP-FPM tertahan.
            $res = Http::timeout(5)
                ->withOptions(['connect_timeout' => 2])
                ->withHeaders([
                    'x-engine-ts' => $ts,
                    'x-engine-sig' => hash_hmac('sha256', $ts . '.' . $body, $secret),
                ])
                ->withBody($body, 'application/json')
                ->post($baseUrl . '/engine/reply');

            if (!$res->successful()) {
                throw new \RuntimeException('HTTP ' . $res->status());
            }
            $data = $res->json();

            $replies = [];
            foreach ((array) ($data['replies'] ?? []) as $r) {
                $media = null;
                if (!empty($r['media']['url'])) {
                    $media = [
                        'url' => $publicUrl . $r['media']['url'],
                        'name' => $r['media']['name'] ?? 'lampiran',
                        'mimetype' => $r['media']['mimetype'] ?? 'application/octet-stream',
                    ];
                }
                $replies[] = ['text' => (string) ($r['text'] ?? ''), 'media' => $media];
            }

            return [
                'replies' => $replies,
                'state' => $data['state'] ?? null,
                'handoff' => (bool) ($data['handoff'] ?? false),
                'not_understood' => (bool) ($data['notUnderstood'] ?? false),
                'engine_error' => false,
            ];
        } catch (\Throwable $e) {
            Log::warning('Web chat engine gagal: ' . $e->getMessage());

            // Mesin bot mati → jangan biarkan user tanpa jawaban; arahkan ke admin.
            return [
                'replies' => [['text' => self::ENGINE_ERROR_TEXT, 'media' => null]],
                'state' => $session->bot_state,
                'handoff' => true,
                'not_understood' => false,
                'engine_error' => true,
            ];
        }
    }
}
