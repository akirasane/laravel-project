<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlatformConfiguration>
 */
class PlatformConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['shopee', 'lazada', 'shopify', 'tiktok'];
        $platform = $this->faker->randomElement($platforms);

        return [
            'platform_type' => $platform,
            'credentials' => [
                'api_key' => $this->faker->uuid(),
                'api_secret' => $this->faker->sha256(),
                'access_token' => $this->faker->optional()->sha256(),
                'refresh_token' => $this->faker->optional()->sha256(),
            ],
            'sync_interval' => $this->faker->randomElement([300, 600, 900, 1800]), // 5min to 30min
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'last_sync' => $this->faker->optional()->dateTimeBetween('-1 day', 'now'),
            'settings' => [
                'webhook_url' => $this->faker->optional()->url(),
                'sandbox_mode' => $this->faker->boolean(30),
                'auto_sync' => $this->faker->boolean(70),
            ],
        ];
    }
}
