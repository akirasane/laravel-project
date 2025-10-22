<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlatformConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $platformId = $this->route('platform_configuration');
        
        return [
            'platform_type' => [
                'required',
                'in:shopee,lazada,shopify,tiktok',
                $platformId ? 'unique:platform_configurations,platform_type,' . $platformId : 'unique:platform_configurations,platform_type'
            ],
            'credentials' => 'required|array',
            'credentials.api_key' => 'required|string|max:255',
            'credentials.api_secret' => 'required|string|max:255',
            'credentials.access_token' => 'nullable|string|max:500',
            'credentials.refresh_token' => 'nullable|string|max:500',
            'sync_interval' => 'required|integer|min:60|max:86400', // 1 minute to 24 hours
            'is_active' => 'required|boolean',
            'settings' => 'nullable|array',
            'settings.webhook_url' => 'nullable|url|max:500',
            'settings.sandbox_mode' => 'nullable|boolean',
            'settings.auto_sync' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'platform_type.required' => 'Platform type is required.',
            'platform_type.in' => 'Platform type must be one of: shopee, lazada, shopify, tiktok.',
            'platform_type.unique' => 'This platform is already configured.',
            'credentials.required' => 'Platform credentials are required.',
            'credentials.api_key.required' => 'API key is required.',
            'credentials.api_secret.required' => 'API secret is required.',
            'sync_interval.min' => 'Sync interval must be at least 60 seconds.',
            'sync_interval.max' => 'Sync interval cannot exceed 24 hours.',
        ];
    }
}
