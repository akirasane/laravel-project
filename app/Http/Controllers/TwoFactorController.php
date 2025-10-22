<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TwoFactorAuthService;
use App\Rules\SecurePassword;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    protected TwoFactorAuthService $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Show 2FA setup page.
     */
    public function setup()
    {
        $user = auth()->user();
        
        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard')
                ->with('message', 'Two-factor authentication is already enabled.');
        }

        $secret = $this->twoFactorService->generateSecretKey();
        $qrCodeUrl = $this->twoFactorService->getQRCodeUrl($user, $secret);

        return view('auth.two-factor.setup', [
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }

    /**
     * Enable 2FA for the user.
     */
    public function enable(Request $request)
    {
        $request->validate([
            'secret' => 'required|string',
            'code' => 'required|string|size:6',
            'password' => ['required', 'string', new SecurePassword(auth()->id())],
        ]);

        $user = auth()->user();

        // Verify current password
        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'The provided password is incorrect.']);
        }

        // Enable 2FA
        if ($this->twoFactorService->enableTwoFactor($user, $request->secret, $request->code)) {
            session(['two_factor_verified' => $user->id]);
            
            return redirect()->route('dashboard')
                ->with('success', 'Two-factor authentication has been enabled successfully.');
        }

        return back()->withErrors(['code' => 'The provided two-factor authentication code is invalid.']);
    }

    /**
     * Show 2FA verification page.
     */
    public function verify()
    {
        $user = auth()->user();
        
        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor.verify');
    }

    /**
     * Verify 2FA code.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'remember_device' => 'boolean',
        ]);

        $user = auth()->user();
        $code = str_replace(' ', '', $request->code);

        // Try to verify TOTP code first
        if ($this->twoFactorService->verifyUserCode($user, $code)) {
            session(['two_factor_verified' => $user->id]);
            
            // Remember device if requested
            if ($request->remember_device && $this->twoFactorService->shouldRememberDevice($user)) {
                $this->twoFactorService->rememberDevice($user);
            }
            
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Two-factor authentication verified successfully.');
        }

        // Try backup code if TOTP failed
        if ($this->twoFactorService->verifyBackupCode($user, $code)) {
            session(['two_factor_verified' => $user->id]);
            
            $remainingCodes = $this->twoFactorService->getRemainingBackupCodesCount($user);
            $message = 'Backup code verified successfully.';
            
            if ($remainingCodes <= 2) {
                $message .= " Warning: You have only {$remainingCodes} backup codes remaining.";
            }
            
            return redirect()->intended(route('dashboard'))
                ->with('warning', $message);
        }

        return back()->withErrors(['code' => 'The provided two-factor authentication code is invalid.']);
    }

    /**
     * Show 2FA settings page.
     */
    public function settings()
    {
        $user = auth()->user();
        
        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        $remainingBackupCodes = $this->twoFactorService->getRemainingBackupCodesCount($user);

        return view('auth.two-factor.settings', [
            'remainingBackupCodes' => $remainingBackupCodes,
        ]);
    }

    /**
     * Disable 2FA for the user.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', new SecurePassword(auth()->id())],
        ]);

        $user = auth()->user();

        // Verify current password
        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'The provided password is incorrect.']);
        }

        // Disable 2FA
        $this->twoFactorService->disableTwoFactor($user);
        
        // Clear session
        session()->forget('two_factor_verified');
        $this->twoFactorService->forgetDevice();

        return redirect()->route('dashboard')
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Regenerate backup codes.
     */
    public function regenerateBackupCodes(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', new SecurePassword(auth()->id())],
        ]);

        $user = auth()->user();

        // Verify current password
        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'The provided password is incorrect.']);
        }

        // Regenerate backup codes
        $backupCodes = $this->twoFactorService->regenerateBackupCodes($user);

        return view('auth.two-factor.backup-codes', [
            'backupCodes' => $backupCodes,
        ]);
    }
}
