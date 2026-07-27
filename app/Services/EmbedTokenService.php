<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Short-lived signed tokens for pages embedded as an iframe in a third-party client
 * (Mitel Ignite's OpenMedia targetUri) that can't rely on the agent's normal
 * localStorage-based login — cross-origin iframes commonly can't see it at all.
 */
class EmbedTokenService
{
    // Emails can sit unclaimed in Mitel's queue for a while before an agent picks them up
    // (observed backlog: several days) — TTL needs to comfortably outlast that.
    private const DEFAULT_TTL_MINUTES = 60 * 24 * 14; // 14 days

    public static function generate(string $type, int $id, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): string
    {
        return Crypt::encryptString(json_encode([
            'type' => $type,
            'id' => $id,
            'exp' => now()->addMinutes($ttlMinutes)->timestamp,
        ]));
    }

    /**
     * Returns the token's subject id if valid and not expired, otherwise null.
     */
    public static function validate(string $token, string $expectedType): ?int
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException $e) {
            return null;
        }

        if (!is_array($payload) || ($payload['type'] ?? null) !== $expectedType) {
            return null;
        }

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return (int) ($payload['id'] ?? 0) ?: null;
    }
}
