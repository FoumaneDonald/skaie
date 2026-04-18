<?php

namespace App\Http\Controllers\Api\Auth;

use App\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;
use Carbon\Carbon;

class AuthController extends Controller
{


    public function __construct(protected OtpService $otpService) {}

    // POST /api/auth/register  (customers only)
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $data['password'], // auto-hashed via cast
            'role'     => 'customer',
        ]);

        $this->otpService->generateAndSend($user);

        $tokens = $this->issueTokens($user);

        return response()->json([
            'message' => 'Registration successful. Please verify your email.',
            'user'    => $user,
            ...$tokens,
        ], 201);
    }

    // POST /api/auth/login
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user   = Auth::user();
        $tokens = $this->issueTokens($user);

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user,
            ...$tokens,
        ]);
    }

    // POST /api/auth/logout
    public function logout(Request $request): JsonResponse
    {
        // Revoke current access token
        $request->user()->token()->revoke();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // GET /api/auth/me
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    // POST /api/auth/refresh
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        // Find the token by ID
        $token = Token::find($request->refresh_token);

        // Validate: exists, not revoked, not expired, belongs to a real user
        if (!$token || $token->revoked) {
            return response()->json(['message' => 'Invalid refresh token.'], 401);
        }

        if ($token->expires_at && Carbon::parse($token->expires_at)->isPast()) {
            return response()->json(['message' => 'Refresh token expired. Please log in again.'], 401);
        }

        $user = User::find($token->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 401);
        }

        // Revoke the old token
        $token->revoke();

        // Issue fresh tokens
        $tokens = $this->issueTokens($user);

        return response()->json([
            'message' => 'Token refreshed.',
            ...$tokens,
        ]);
    }


    // POST /api/auth/email/verify
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        if (!$this->otpService->verify($user, $request->otp)) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }

    // POST /api/auth/email/resend
    public function resendOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $this->otpService->generateAndSend($user);

        return response()->json(['message' => 'OTP resent. Check your inbox.']);
    }

    // -------------------------------------------------------
    private function issueTokens(User $user): array
    {
        // Revoke old tokens to avoid accumulation
        $user->tokens()->delete();

        $tokenResult = $user->createToken('Personal Access Token');

        return [
            'token_type'    => 'Bearer',
            'access_token'  => $tokenResult->accessToken,
            'expires_in'    => 3600,
            'refresh_token' => $tokenResult->token->id,
        ];
    }
}
