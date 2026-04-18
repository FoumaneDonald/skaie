<?php

namespace App\Services;

use App\Mail\EmailVerificationMail;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function generateAndSend(User $user): void
    {
        // Invalidate any existing OTPs for this user
        EmailVerificationOtp::where('user_id', $user->id)->delete();

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationOtp::create([
            'user_id'    => $user->id,
            'otp'        => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'used'       => false,
        ]);

        Mail::to($user->email)->send(new EmailVerificationMail($otp, $user->name));
    }

    public function verify(User $user, string $otp): bool
    {
        $record = EmailVerificationOtp::where('user_id', $user->id)
            ->where('used', false)
            ->latest()
            ->first();

        if (!$record || !$record->isValid($otp)) {
            return false;
        }

        // Mark OTP as used
        $record->update(['used' => true]);

        // Mark user as verified
        $user->update(['email_verified_at' => now()]);

        return true;
    }
}
