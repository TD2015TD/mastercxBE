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

        return base64_encode($payload) . '.' . hash_hmac('sha256', $payload, config('app.key'));
    }

    /**
     * Verify a session token.
     */
    public static function verifySessionToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        
        // Verify signature
        if (!hash_equals(hash_hmac('sha256', $payload, config('app.key')), $signature)) {
            return null;
        }

        // Decode payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            return null;
        }

        // Check expiration
        if ($data['expires_at'] < now()->timestamp) {
            return null;
        }

        return $data;
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
