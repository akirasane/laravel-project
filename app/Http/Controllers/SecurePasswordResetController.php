<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PasswordHistory;
use App\Rules\SecurePassword;

class SecurePasswordResetController extends Controller
{
    /**
     * Show password reset request form.
     */
    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    /**
     * Send password reset link.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rate limiting
        $key = 'password-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => "Too many password reset attempts. Please try again in {$seconds} seconds."
            ]);
        }

        RateLimiter::hit($key, 3600); // 1 hour

        $user = User::where('email', $request->email)->first();

        // Always return success message to prevent email enumeration
        $response = Password::sendResetLink($request->only('email'));

        // Log the password reset request
        AuditLog::log(
            'password_reset_requested',
            'authentication',
            "Password reset requested for email: {$request->email}",
            $user,
            [
                'email' => $request->email,
                'user_exists' => $user ? true : false,
            ],
            'medium'
        );

        if ($response == Password::RESET_LINK_SENT) {
            return back()->with('status', 'We have emailed your password reset link!');
        }

        // Don't reveal if email exists or not
        return back()->with('status', 'We have emailed your password reset link!');
    }

    /**
     * Show password reset form.
     */
    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset')->with([
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset password.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', new SecurePassword()],
        ]);

        // Rate limiting
        $key = 'password-reset-attempt:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => "Too many password reset attempts. Please try again in {$seconds} seconds."
            ]);
        }

        RateLimiter::hit($key, 3600); // 1 hour

        $response = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                // Check if password has been used before
                if (PasswordHistory::hasBeenUsed($user, $password)) {
                    return false;
                }

                // Update password
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                // Add to password history
                PasswordHistory::addPassword($user, $user->password);

                // Reset failed login attempts
                $user->update([
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);

                // Invalidate all existing sessions
                $user->activeSessions()->update(['is_active' => false]);

                // Clear 2FA verification
                session()->forget('two_factor_verified');

                // Log successful password reset
                AuditLog::log(
                    'password_reset_completed',
                    'authentication',
                    "Password reset completed for user: {$user->email}",
                    $user,
                    [
                        'sessions_invalidated' => true,
                        'two_factor_cleared' => true,
                    ],
                    'medium'
                );
            }
        );

        if ($response == Password::PASSWORD_RESET) {
            RateLimiter::clear($key); // Clear rate limit on success
            return redirect()->route('login')->with('status', 'Your password has been reset! Please log in with your new password.');
        }

        // Log failed password reset
        $user = User::where('email', $request->email)->first();
        AuditLog::log(
            'password_reset_failed',
            'authentication',
            "Password reset failed for email: {$request->email}",
            $user,
            [
                'error' => $response,
                'token_valid' => $response !== Password::INVALID_TOKEN,
            ],
            'high'
        );

        return back()->withErrors(['email' => __($response)]);
    }

    /**
     * Force password change for user.
     */
    public function forceChange(Request $request)
    {
        $user = auth()->user();

        if (!$user->mustChangePassword()) {
            return redirect()->route('dashboard');
        }

        return view('auth.passwords.force-change');
    }

    /**
     * Update password when forced to change.
     */
    public function updateForcedPassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', new SecurePassword(auth()->id())],
        ]);

        $user = auth()->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The provided password is incorrect.']);
        }

        // Check if new password has been used before
        if (PasswordHistory::hasBeenUsed($user, $request->password)) {
            return back()->withErrors(['password' => 'This password has been used recently. Please choose a different password.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ]);

        // Add to password history
        PasswordHistory::addPassword($user, $user->password);

        // Log password change
        AuditLog::log(
            'password_changed_forced',
            'authentication',
            "Forced password change completed for user: {$user->email}",
            $user,
            [],
            'medium'
        );

        return redirect()->route('dashboard')
            ->with('success', 'Your password has been updated successfully.');
    }
}
