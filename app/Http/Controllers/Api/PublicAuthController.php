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
     * Resolve customer + email from either email or phone_number input.
     * Returns [customer, email] or null if not found.
     */
    private function resolveCustomer(Request $request): ?array
    {
        if ($request->filled('email')) {
            $email    = strtolower(trim($request->email));
            $customer = Customer::where('email', $email)->first();
            return $customer ? [$customer, $email] : null;
        }

        if ($request->filled('phone_number')) {
            $phone    = preg_replace('/[\s-]/', '', $request->phone_number);
            $customer = Customer::where('phone_number', $phone)->first();
            return $customer ? [$customer, strtolower(trim($customer->email))] : null;
        }

        return null;
    }

    /**
     * Request OTP — accepts either email OR phone_number.
     * OTP is always sent to the customer's email address.
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'        => 'nullable|email|max:255|required_without:phone_number',
            'phone_number' => [
                'nullable', 'string', 'required_without:email',
                'regex:/^(\+?6?01[0-46-9][-\s]?[0-9]{7,8}|01[0-46-9][-\s]?[0-9]{7,8})$/',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Sila masukkan e-mel atau nombor telefon.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $resolved = $this->resolveCustomer($request);
        if (!$resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Maklumat tidak sah. Sila semak e-mel atau nombor telefon anda.',
            ], 404);
        }

        [$customer, $email] = $resolved;
        $phone = $request->filled('phone_number')
            ? preg_replace('/[\s-]/', '', $request->phone_number)
            : null;

        // Rate limit by email
        $recentRequests = CitizenAuthSession::where('email', $email)
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->count();

        if ($recentRequests >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percubaan. Sila cuba lagi selepas 30 minit.',
            ], 429);
        }

        try {
            $otp       = CitizenAuthSession::generateOtp();
            $expiresAt = Carbon::now()->addMinutes(10);

            CitizenAuthSession::create([
                'phone_number'   => $phone,
                'email'          => $email,
                'otp_code'       => $otp,
                'phone_otp_code' => null,
                'otp_expires_at' => $expiresAt,
            ]);

            // Send single OTP to email
            DynamicMailService::send($email, new CitizenOtpMail($otp, $phone ?? $email, 'email'));

            Log::info('OTP requested (email-only)', [
                'email' => substr($email, 0, 3) . '***@' . explode('@', $email)[1],
                'via'   => $request->filled('phone_number') ? 'phone_lookup' : 'email_direct',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kod OTP telah dihantar ke e-mel anda.',
                'email_hint' => substr($email, 0, 2) . str_repeat('*', strpos($email, '@') - 2) . substr($email, strpos($email, '@')),
            ]);

        } catch (\Exception $e) {
            Log::error('OTP request failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghantar OTP. Sila cuba lagi.'], 500);
        }
    }

    /**
     * Verify OTP and create session.
     * Accepts either email or phone_number + single otp field.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'        => 'nullable|email|required_without:phone_number',
            'phone_number' => 'nullable|string|required_without:email',
            'otp'          => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Maklumat tidak sah.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $resolved = $this->resolveCustomer($request);
        if (!$resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Maklumat tidak sah.',
            ], 404);
        }

        [$customer, $email] = $resolved;
        $otp = trim($request->otp);

        try {
            // Master OTP for testing
            $isMaster = ($otp === '123456');

            if (!$isMaster) {
                $session = CitizenAuthSession::where('email', $email)
                    ->where('otp_code', $otp)
                    ->whereNull('verified_at')
                    ->latest()
                    ->first();

                if (!$session) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kod OTP tidak sah. Sila semak semula e-mel anda.',
                    ], 401);
                }

                if ($session->isOtpExpired()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kod OTP telah tamat tempoh. Sila minta kod baru.',
                    ], 401);
                }

                $session->update(['verified_at' => now()]);
            }

            $phone        = $request->filled('phone_number')
                ? preg_replace('/[\s-]/', '', $request->phone_number)
                : ($customer->phone_number ?? '');
            $sessionToken = CitizenAuthSession::generateSessionToken($phone, $email);

            Log::info('OTP verified successfully', [
                'email' => substr($email, 0, 3) . '***@' . explode('@', $email)[1],
            ]);

            return response()->json([
                'success'       => true,
                'message'       => 'Log masuk berjaya.',
                'session_token' => $sessionToken,
                'phone_number'  => $phone,
                'email'         => $email,
                'customer_name' => $customer->name,
            ]);

        } catch (\Exception $e) {
            Log::error('OTP verification failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Pengesahan gagal. Sila cuba lagi.'], 500);
        }
    }

    /**
     * Register a new citizen account and send OTP via email.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'phone_number' => [
                'nullable', 'string',
                'regex:/^(\+?6?01[0-46-9][-\s]?[0-9]{7,8}|01[0-46-9][-\s]?[0-9]{7,8})$/',
            ],
            'id_type'   => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Maklumat tidak sah.', 'errors' => $validator->errors()], 422);
        }

        $email = strtolower(trim($request->email));
        $name  = trim($request->name);
        $phone = $request->filled('phone_number')
            ? preg_replace('/[\s-]/', '', $request->phone_number)
            : null;

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

        $recentRequests = CitizenAuthSession::where('email', $email)
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->count();

        if ($recentRequests >= 5) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak percubaan. Sila cuba lagi selepas 30 minit.'], 429);
        }

        try {
            Customer::create([
                'name'         => $name,
                'email'        => $email,
                'phone_number' => $phone,
                'id_type'      => $request->id_type ?: null,
                'id_no'        => $idNo,
                'status'       => 'active',
            ]);

            $otp       = CitizenAuthSession::generateOtp();
            $expiresAt = Carbon::now()->addMinutes(10);

            CitizenAuthSession::create([
                'phone_number'   => $phone,
                'email'          => $email,
                'otp_code'       => $otp,
                'phone_otp_code' => null,
                'otp_expires_at' => $expiresAt,
            ]);

            DynamicMailService::send($email, new CitizenOtpMail($otp, $phone ?? $email, 'email'));

            Log::info('New citizen registered with email OTP', [
                'email' => substr($email, 0, 3) . '***@' . explode('@', $email)[1],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pendaftaran berjaya! Kod OTP telah dihantar ke e-mel anda.',
            ]);

        } catch (\Exception $e) {
            Log::error('Registration failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Pendaftaran gagal. Sila cuba lagi.'], 500);
        }
    }
}
