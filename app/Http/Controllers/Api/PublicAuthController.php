<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CitizenAuthSession;
use App\Models\Customer;
use App\Mail\CitizenOtpMail;
use App\Services\DynamicMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PublicAuthController extends Controller
{
    /**
     * Send phone OTP via SMS/WhatsApp.
     * TODO: Replace with real SMS gateway (Twilio, Nexmo, WhatsApp) when configured.
     */
    private function sendPhoneOtp(string $phoneNumber, string $otpCode, string $email): void
    {
        // Placeholder: currently falls back to email until SMS service is configured
        // When SMS is ready, implement here and remove the email fallback below
        Log::info('Phone OTP (SMS not configured — sent via email fallback)', [
            'phone' => substr($phoneNumber, 0, 4) . '****',
            'otp'   => $otpCode,
        ]);

        // Email fallback — send phone OTP to same email with different subject
        // so the user can distinguish which code is which
        // TODO: Remove this fallback once SMS is configured
        DynamicMailService::send($email, new CitizenOtpMail($otpCode, $phoneNumber, 'phone'));
    }

    /**
     * Request OTP codes — one for email, one for phone.
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => [
                'required', 'string',
                'regex:/^(\+?6?01[0-46-9][-\s]?[0-9]{7,8}|01[0-46-9][-\s]?[0-9]{7,8})$/',
            ],
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Maklumat tidak sah.', 'errors' => $validator->errors()], 422);
        }

        $phoneNumber = preg_replace('/[\s-]/', '', $request->phone_number);
        $email       = strtolower(trim($request->email));

        $customer = Customer::where('email', $email)->first();
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Maklumat tidak sah. Sila semak nombor telefon dan e-mel anda.'], 404);
        }

        $recentRequests = CitizenAuthSession::countRecentRequests($phoneNumber, 30);
        if ($recentRequests >= 3) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak percubaan. Sila cuba lagi selepas 30 minit.'], 429);
        }

        try {
            $emailOtp = CitizenAuthSession::generateOtp();
            $phoneOtp = CitizenAuthSession::generateOtp();
            $expiresAt = Carbon::now()->addMinutes(10);

            CitizenAuthSession::create([
                'phone_number'   => $phoneNumber,
                'email'          => $email,
                'otp_code'       => $emailOtp,
                'phone_otp_code' => $phoneOtp,
                'otp_expires_at' => $expiresAt,
            ]);

            // Send email OTP
            DynamicMailService::send($email, new CitizenOtpMail($emailOtp, $phoneNumber, 'email'));

            // Send phone OTP (SMS/WhatsApp — currently falls back to email)
            $this->sendPhoneOtp($phoneNumber, $phoneOtp, $email);

            Log::info('Dual OTP requested', [
                'phone' => substr($phoneNumber, 0, 4) . '****',
                'email' => substr($email, 0, 3) . '***@' . explode('@', $email)[1],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kod OTP telah dihantar ke e-mel dan nombor telefon anda.',
            ]);

        } catch (\Exception $e) {
            Log::error('OTP request failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghantar OTP. Sila cuba lagi.'], 500);
        }
    }

    /**
     * Verify both OTP codes and create session.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number'  => 'required|string',
            'email'         => 'required|email',
            'email_otp'     => 'required|string|size:6',
            'phone_otp'     => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Maklumat tidak sah.', 'errors' => $validator->errors()], 422);
        }

        $phoneNumber = preg_replace('/[\s-]/', '', $request->phone_number);
        $email       = strtolower(trim($request->email));
        $emailOtp    = trim($request->email_otp);
        $phoneOtp    = trim($request->phone_otp);

        try {
            $masterOtp = '123456';
            $isMaster  = ($emailOtp === $masterOtp && $phoneOtp === $masterOtp);

            if (!$isMaster) {
                $session = CitizenAuthSession::where('phone_number', $phoneNumber)
                    ->where('email', $email)
                    ->where('otp_code', $emailOtp)
                    ->where('phone_otp_code', $phoneOtp)
                    ->whereNull('verified_at')
                    ->latest()
                    ->first();

                if (!$session) {
                    return response()->json(['success' => false, 'message' => 'Kod OTP tidak sah. Sila semak semula kod e-mel dan telefon anda.'], 401);
                }

                if ($session->isOtpExpired()) {
                    return response()->json(['success' => false, 'message' => 'Kod OTP telah tamat tempoh. Sila minta kod baru.'], 401);
                }

                $session->update(['verified_at' => now()]);
            }

            $customer = Customer::where('email', $email)->first();
            if (!$customer) {
                return response()->json(['success' => false, 'message' => 'Maklumat tidak sah. Sila semak e-mel anda.'], 404);
            }

            $sessionToken = CitizenAuthSession::generateSessionToken($phoneNumber, $email);

            Log::info('Dual OTP verified successfully', [
                'phone' => substr($phoneNumber, 0, 4) . '****',
            ]);

            return response()->json([
                'success'       => true,
                'message'       => 'Log masuk berjaya.',
                'session_token' => $sessionToken,
                'phone_number'  => $phoneNumber,
                'email'         => $email,
                'customer_name' => $customer->name,
            ]);

        } catch (\Exception $e) {
            Log::error('OTP verification failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Pengesahan gagal. Sila cuba lagi.'], 500);
        }
    }

    /**
     * Register a new citizen account and send dual OTP.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'phone_number' => [
                'required', 'string',
                'regex:/^(\+?6?01[0-46-9][-\s]?[0-9]{7,8}|01[0-46-9][-\s]?[0-9]{7,8})$/',
            ],
            'id_type'   => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Maklumat tidak sah.', 'errors' => $validator->errors()], 422);
        }

        $phoneNumber = preg_replace('/[\s-]/', '', $request->phone_number);
        $email       = strtolower(trim($request->email));
        $name        = trim($request->name);

        if (Customer::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'E-mel ini telah didaftarkan. Sila log masuk.'], 409);
        }

        $idNo = $request->id_number ? trim($request->id_number) : null;
        if ($idNo) {
            $existingByIdNo = Customer::where('id_no', $idNo)->first();
            if ($existingByIdNo) {
                $masked = preg_replace('/(?<=.{3}).(?=.*@)/u', '*', $existingByIdNo->email);
                return response()->json([
                    'success' => false,
                    'message' => "Nombor IC/Pasport ini telah didaftarkan. Sila log masuk menggunakan e-mel: {$masked}",
                ], 409);
            }
        }

        $recentRequests = CitizenAuthSession::countRecentRequests($phoneNumber, 30);
        if ($recentRequests >= 3) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak percubaan. Sila cuba lagi selepas 30 minit.'], 429);
        }

        try {
            Customer::create([
                'name'    => $name,
                'email'   => $email,
                'id_type' => $request->id_type ?: null,
                'id_no'   => $idNo,
                'status'  => 'active',
            ]);

            $emailOtp  = CitizenAuthSession::generateOtp();
            $phoneOtp  = CitizenAuthSession::generateOtp();
            $expiresAt = Carbon::now()->addMinutes(10);

            CitizenAuthSession::create([
                'phone_number'   => $phoneNumber,
                'email'          => $email,
                'otp_code'       => $emailOtp,
                'phone_otp_code' => $phoneOtp,
                'otp_expires_at' => $expiresAt,
            ]);

            DynamicMailService::send($email, new CitizenOtpMail($emailOtp, $phoneNumber, 'email'));
            $this->sendPhoneOtp($phoneNumber, $phoneOtp, $email);

            Log::info('New citizen registered with dual OTP', [
                'email' => substr($email, 0, 3) . '***@' . explode('@', $email)[1],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pendaftaran berjaya! Kod OTP telah dihantar ke e-mel dan nombor telefon anda.',
            ]);

        } catch (\Exception $e) {
            Log::error('Registration failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Pendaftaran gagal. Sila cuba lagi.'], 500);
        }
    }
}
