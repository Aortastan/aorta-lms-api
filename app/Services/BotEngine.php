<?php

namespace App\Services;

use App\Models\ChatSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotEngine
{
    const CIRCUIT_KEY = 'webchat:engine_down';
    const CIRCUIT_SECONDS = 30;

    const ENGINE_ERROR_TEXT = "Maaf kak, chat otomatis sedang gangguan 🙏\n\nSilakan klik tombol *Chat Admin via WhatsApp* di bawah supaya dibantu langsung oleh admin ya.";

    public static function reply(ChatSession $session, string $text): array
    {
        $baseUrl = rtrim((string) config('services.webchat.bot_url'), '/');
        $publicUrl = rtrim((string) (config('services.webchat.bot_public_url') ?: $baseUrl), '/');
        $secret = (string) config('services.bot_sso_secret');

        $body = json_encode([
            'sessionId' => $session->uuid,
            'text' => $text,
            'name' => $session->name,
            'state' => $session->bot_state,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $ts = (string) (int) floor(microtime(true) * 1000);

        try {
            if (!$baseUrl || !$secret) {
                throw new \RuntimeException('WEBCHAT_BOT_URL / BOT_SSO_SECRET belum di-set');
            }
            if (Cache::has(self::CIRCUIT_KEY)) {
                throw new \RuntimeException('engine ditandai mati (circuit breaker)');
            }
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
            if (!is_array($data) || empty($data['replies']) || !is_array($data['replies'])) {
                throw new \RuntimeException('payload engine tidak valid');
            }

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
            if (strpos($e->getMessage(), 'circuit breaker') === false) {
                Log::warning('Web chat engine gagal: ' . $e->getMessage());
                Cache::put(self::CIRCUIT_KEY, 1, self::CIRCUIT_SECONDS);
            }

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
