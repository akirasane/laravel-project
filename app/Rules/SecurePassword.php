<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SecurePassword implements ValidationRule
{
    private ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        $config = config('security.password');
        
        // Check minimum length
        if (strlen($value) < $config['min_length']) {
            $fail("The :attribute must be at least {$config['min_length']} characters long.");
            return;
        }

        // Check for uppercase letters
        if ($config['require_uppercase'] && !preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
            return;
        }

        // Check for lowercase letters
        if ($config['require_lowercase'] && !preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
            return;
        }

        // Check for numbers
        if ($config['require_numbers'] && !preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
            return;
        }

        // Check for symbols
        if ($config['require_symbols'] && !preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The :attribute must contain at least one special character.');
            return;
        }

        // Check against common passwords
        if ($config['prevent_common'] && $this->isCommonPassword($value)) {
            $fail('The :attribute is too common. Please choose a more secure password.');
            return;
        }

        // Check against personal information (if user provided)
        if ($config['prevent_personal_info'] && $this->userId && $this->containsPersonalInfo($value)) {
            $fail('The :attribute must not contain personal information.');
            return;
        }

        // Check password history (if user provided)
        if ($this->userId && $this->hasBeenUsedBefore($value)) {
            $fail('The :attribute has been used recently. Please choose a different password.');
            return;
        }
    }

    /**
     * Check if password is in common passwords list.
     */
    private function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123', 'password123',
            'admin', 'letmein', 'welcome', 'monkey', '1234567890', 'password1',
            'qwerty123', 'admin123', 'root', 'toor', 'pass', 'test', 'guest',
            'user', 'login', 'changeme', 'default', 'secret', 'master'
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Check if password contains personal information.
     */
    private function containsPersonalInfo(string $password): bool
    {
        if (!$this->userId) {
            return false;
        }

        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            return false;
        }

        $personalInfo = [
            strtolower($user->name),
            strtolower($user->email),
            strtolower(explode('@', $user->email)[0]),
        ];

        $lowerPassword = strtolower($password);
        
        foreach ($personalInfo as $info) {
            if (str_contains($lowerPassword, $info) || str_contains($info, $lowerPassword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if password has been used before.
     */
    private function hasBeenUsedBefore(string $password): bool
    {
        if (!$this->userId) {
            return false;
        }

        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            return false;
        }

        return \App\Models\PasswordHistory::hasBeenUsed($user, $password);
    }
}
