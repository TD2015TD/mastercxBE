<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CitizenAuthSession extends Model
{
    protected $fillable = [
        'phone_number',
        'email',
        'otp_code',
        'phone_otp_code',
        'otp_expires_at',
        'session_token',
        'verified_at',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Generate a 6-digit OTP code.
     */
    public static function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a session token.
     */
    public static function generateSessionToken(string $phoneNumber, string $email): string
    {
        $payload = json_encode([
            'phone_number' => $phoneNumber,
            'email' => $email,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addHours(24)->timestamp,
            'random' => Str::random(32),
        ]);

        $encodedPayload = base64_encode($payload);
        $signature = hash_hmac('sha256', $encodedPayload, config('app.key'));
        
        return $encodedPayload . '.' . $signature;
    }

    /**
     * Verify a session token.
     */
    public static function verifySessionToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 2) {
                \Log::warning('Invalid token format: wrong number of parts', ['parts_count' => count($parts)]);
                return null;
            }

            [$payload, $signature] = $parts;
            
            // Verify signature
            $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));
            if (!hash_equals($expectedSignature, $signature)) {
                \Log::warning('Invalid token: signature mismatch');
                return null;
            }

            // Decode payload
            $decodedPayload = base64_decode($payload, true);
            if ($decodedPayload === false) {
                \Log::warning('Invalid token: base64 decode failed');
                return null;
            }
            
            $data = json_decode($decodedPayload, true);
            if (!$data) {
                \Log::warning('Invalid token: json decode failed');
                return null;
            }

            // Check expiration
            if (!isset($data['expires_at']) || $data['expires_at'] < now()->timestamp) {
                \Log::warning('Token expired', ['expires_at' => $data['expires_at'] ?? 'missing']);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('Token verification exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if OTP is expired.
     */
    public function isOtpExpired(): bool
    {
        return $this->otp_expires_at->isPast();
    }

    /**
     * Check if session is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Count OTP requests in the last X minutes for rate limiting.
     */
    public static function countRecentRequests(string $phoneNumber, int $minutes = 30): int
    {
        return self::where('phone_number', $phoneNumber)
            ->where('created_at', '>=', Carbon::now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Clean up expired and old sessions.
     */
    public static function cleanupExpiredSessions(): int
    {
        // Delete sessions older than 7 days
        return self::where('created_at', '<', Carbon::now()->subDays(7))->delete();
    }
}
