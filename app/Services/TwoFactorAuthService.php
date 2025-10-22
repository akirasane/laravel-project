<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class TwoFactorAuthService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate a secret key for 2FA.
     */
    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Enable 2FA for a user.
     */
    public function enableTwoFactor(User $user, string $secret, string $code): bool
    {
        // Verify the provided code
        if (!$this->verifyCode($secret, $code)) {
            return false;
        }

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();

        // Save 2FA settings
        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => $backupCodes,
            'two_factor_confirmed_at' => now(),
        ]);

        // Log the 2FA enablement
        AuditLog::log(
            'two_factor_enabled',
            'authentication',
            "Two-factor authentication enabled for user: {$user->email}",
            $user,
            [
                'backup_codes_generated' => count($backupCodes),
            ],
            'medium'
        );

        return true;
    }

    /**
     * Disable 2FA for a user.
     */
    public function disableTwoFactor(User $user): bool
    {
        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        // Log the 2FA disablement
        AuditLog::log(
            'two_factor_disabled',
            'authentication',
            "Two-factor authentication disabled for user: {$user->email}",
            $user,
            [],
            'medium'
        );

        return true;
    }

    /**
     * Verify a 2FA code.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $window = config('security.two_factor.totp_window', 1);
        return $this->google2fa->verifyKey($secret, $code, $window);
    }

    /**
     * Verify a 2FA code for a user.
     */
    public function verifyUserCode(User $user, string $code): bool
    {
        if (!$user->hasTwoFactorEnabled()) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        $isValid = $this->verifyCode($secret, $code);

        // Log the verification attempt
        AuditLog::log(
            $isValid ? 'two_factor_verified' : 'two_factor_verification_failed',
            'authentication',
            "Two-factor authentication verification " . ($isValid ? 'successful' : 'failed') . " for user: {$user->email}",
            $user,
            [
                'verification_result' => $isValid ? 'success' : 'failure',
            ],
            $isValid ? 'low' : 'high'
        );

        return $isValid;
    }

    /**
     * Verify a backup code for a user.
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        if (!$user->hasTwoFactorEnabled() || !$user->two_factor_recovery_codes) {
            return false;
        }

        $backupCodes = $user->two_factor_recovery_codes;
        $hashedCode = hash('sha256', $code);

        // Check if the code exists in backup codes
        $codeIndex = array_search($hashedCode, $backupCodes);
        
        if ($codeIndex === false) {
            // Log failed backup code attempt
            AuditLog::log(
                'two_factor_backup_code_failed',
                'authentication',
                "Invalid backup code attempt for user: {$user->email}",
                $user,
                [],
                'high'
            );
            
            return false;
        }

        // Remove the used backup code
        unset($backupCodes[$codeIndex]);
        $user->update([
            'two_factor_recovery_codes' => array_values($backupCodes)
        ]);

        // Log successful backup code usage
        AuditLog::log(
            'two_factor_backup_code_used',
            'authentication',
            "Backup code used for user: {$user->email}",
            $user,
            [
                'remaining_codes' => count($backupCodes) - 1,
            ],
            'medium'
        );

        return true;
    }

    /**
     * Generate backup codes.
     */
    public function generateBackupCodes(): array
    {
        $count = config('security.two_factor.backup_codes_count', 8);
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = Str::random(10);
            $codes[] = hash('sha256', $code);
        }

        return $codes;
    }

    /**
     * Regenerate backup codes for a user.
     */
    public function regenerateBackupCodes(User $user): array
    {
        if (!$user->hasTwoFactorEnabled()) {
            return [];
        }

        $backupCodes = $this->generateBackupCodes();
        
        $user->update([
            'two_factor_recovery_codes' => $backupCodes
        ]);

        // Log backup codes regeneration
        AuditLog::log(
            'two_factor_backup_codes_regenerated',
            'authentication',
            "Backup codes regenerated for user: {$user->email}",
            $user,
            [
                'new_codes_count' => count($backupCodes),
            ],
            'medium'
        );

        return $backupCodes;
    }

    /**
     * Get QR code URL for 2FA setup.
     */
    public function getQRCodeUrl(User $user, string $secret): string
    {
        $appName = config('app.name', 'Order Management System');
        
        return $this->google2fa->getQRCodeUrl(
            $appName,
            $user->email,
            $secret
        );
    }

    /**
     * Check if 2FA is required for a user.
     */
    public function isTwoFactorRequired(User $user): bool
    {
        // Check if 2FA is globally enabled
        if (!config('security.two_factor.enabled', true)) {
            return false;
        }

        // Check if 2FA is required for admin users
        if (config('security.two_factor.required_for_admin', true) && 
            $user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Check if user has 2FA enabled
        return $user->hasTwoFactorEnabled();
    }

    /**
     * Check if device should be remembered.
     */
    public function shouldRememberDevice(User $user): bool
    {
        $rememberDays = config('security.two_factor.remember_device_days', 30);
        return $rememberDays > 0;
    }

    /**
     * Remember device for 2FA.
     */
    public function rememberDevice(User $user): string
    {
        $token = Str::random(60);
        $rememberDays = config('security.two_factor.remember_device_days', 30);
        
        // Store the remember token in session or cache
        session([
            'two_factor_remember_token' => $token,
            'two_factor_remember_expires' => now()->addDays($rememberDays),
            'two_factor_remember_user' => $user->id,
        ]);

        // Log device remembering
        AuditLog::log(
            'two_factor_device_remembered',
            'authentication',
            "Device remembered for 2FA for user: {$user->email}",
            $user,
            [
                'remember_days' => $rememberDays,
                'device_fingerprint' => $this->getDeviceFingerprint(),
            ],
            'low'
        );

        return $token;
    }

    /**
     * Check if device is remembered.
     */
    public function isDeviceRemembered(User $user): bool
    {
        $token = session('two_factor_remember_token');
        $expires = session('two_factor_remember_expires');
        $userId = session('two_factor_remember_user');

        if (!$token || !$expires || !$userId || $userId != $user->id) {
            return false;
        }

        if (now()->isAfter($expires)) {
            $this->forgetDevice();
            return false;
        }

        return true;
    }

    /**
     * Forget remembered device.
     */
    public function forgetDevice(): void
    {
        session()->forget([
            'two_factor_remember_token',
            'two_factor_remember_expires',
            'two_factor_remember_user',
        ]);
    }

    /**
     * Get device fingerprint for security.
     */
    protected function getDeviceFingerprint(): string
    {
        return hash('sha256', 
            request()->userAgent() . 
            request()->ip() . 
            request()->header('Accept-Language', '')
        );
    }

    /**
     * Get remaining backup codes count.
     */
    public function getRemainingBackupCodesCount(User $user): int
    {
        if (!$user->hasTwoFactorEnabled() || !$user->two_factor_recovery_codes) {
            return 0;
        }

        return count($user->two_factor_recovery_codes);
    }
}