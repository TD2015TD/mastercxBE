<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CitizenAuthSession;
use App\Models\Customer;
use App\Mail\CitizenOtpMail;
use App\Services\DynamicMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PublicAuthController extends Controller
{
    /**
     * Request OTP code via email.
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+?6?01[0-46-9][-\s]?[0-9]{7,8}|01[0-46-9][-\s]?[0-9]{7,8})$/',
            ],
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Clean phone number (remove dashes and spaces)
        $phoneNumber = preg_replace('/[\s-]/', '', $request->phone_number);
        $email = strtolower(trim($request->email));

        // Verify that customer exists with this phone number and email
        // Normalize phone number for comparison (handle various formats)
        $normalizedPhone = preg_replace('/^(\+?60|60)/', '0', $phoneNumber);
        
        $customer = Customer::where('email', $email)
            ->where(function($query) use ($phoneNumber, $normalizedPhone) {
                $query->where('phone_number', $phoneNumber)
                      ->orWhere('phone_number', $normalizedPhone)
                      ->orWhere('phone_number', '+60' . ltrim($normalizedPhone, '0'))
                      ->orWhere('phone_number', '60' . ltrim($normalizedPhone, '0'));
            })
            ->first();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Tiada rekod pelanggan dijumpai dengan nombor telefon dan e-mel ini.',
            ], 404);
        }

        // Rate limiting: Max 3 OTP requests per 30 minutes
        $recentRequests = CitizenAuthSession::countRecentRequests($phoneNumber, 30);
        if ($recentRequests >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percubaan. Sila cuba lagi selepas 30 minit.',
            ], 429);
        }

        try {
            // Generate OTP
            $otpCode = CitizenAuthSession::generateOtp();
            $expiresAt = Carbon::now()->addMinutes(10);

            // Store OTP session
            CitizenAuthSession::create([
                'phone_number' => $phoneNumber,
                'email' => $email,
                'otp_code' => $otpCode,
                'otp_expires_at' => $expiresAt,
            ]);

            // Send OTP email using database SMTP configuration if available
            DynamicMailService::send($email, new CitizenOtpMail($otpCode, $phoneNumber));

            Log::info('OTP requested', [
                'phone' => substr($phoneNumber, 0, 3) . '***' . substr($phoneNumber, -2),
                'email' => substr($email, 0, 3) . '***@' . explode('@', $email)[1],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kod OTP telah dihantar ke email anda.',
            ]);

        } catch (\Exception $e) {
            Log::error('OTP request failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghantar OTP. Sila cuba lagi.',
            ], 500);
        }
    }

    /**
     * Verify OTP and create session.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Clean phone number
        $phoneNumber = preg_replace('/[\s-]/', '', $request->phone_number);
        $email = strtolower(trim($request->email));
        $otpCode = trim($request->otp_code);

        try {
            // Find the most recent OTP session
            $session = CitizenAuthSession::where('phone_number', $phoneNumber)
                ->where('email', $email)
                ->where('otp_code', $otpCode)
                ->whereNull('verified_at')
                ->latest()
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kod OTP tidak sah.',
                ], 401);
            }

            // Check if OTP is expired
            if ($session->isOtpExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kod OTP telah tamat tempoh. Sila minta kod baru.',
                ], 401);
            }

            // Verify that customer exists with this phone number and email
            // Normalize phone number for comparison (remove country code prefix if exists)
            $normalizedPhone = preg_replace('/^(\+?60|60)/', '0', $phoneNumber);
            
            $customer = Customer::where('email', $email)
                ->where(function($query) use ($phoneNumber, $normalizedPhone) {
                    $query->where('phone_number', $phoneNumber)
                          ->orWhere('phone_number', $normalizedPhone)
                          ->orWhere('phone_number', '+60' . ltrim($normalizedPhone, '0'))
                          ->orWhere('phone_number', '60' . ltrim($normalizedPhone, '0'));
                })
                ->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiada rekod pelanggan dijumpai dengan nombor telefon dan e-mel ini.',
                ], 404);
            }

            // Generate session token
            $sessionToken = CitizenAuthSession::generateSessionToken($phoneNumber, $email);

            // Update session
            $session->update([
                'session_token' => $sessionToken,
                'verified_at' => now(),
            ]);

            Log::info('OTP verified successfully', [
                'phone' => substr($phoneNumber, 0, 3) . '***' . substr($phoneNumber, -2),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Log masuk berjaya.',
                'session_token' => $sessionToken,
                'phone_number' => $phoneNumber,
                'email' => $email,
            ]);

        } catch (\Exception $e) {
            Log::error('OTP verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Pengesahan gagal. Sila cuba lagi.',
            ], 500);
        }
    }
}
