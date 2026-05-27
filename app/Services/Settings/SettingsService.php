<?php

namespace App\Services\Settings;

use App\Models\UserSetting;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    private const ENCRYPTED_KEYS = [
        'openrouter_api_key',
        'openai_api_key',
    ];

    public function get(int $userId, string $key, ?string $default = null): ?string
    {
        $setting = UserSetting::where('user_id', $userId)->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        if ($setting->is_encrypted && $setting->value) {
            return Crypt::decryptString($setting->value);
        }

        return $setting->value ?? $default;
    }

    public function set(int $userId, string $key, ?string $value): UserSetting
    {
        $isEncrypted = in_array($key, self::ENCRYPTED_KEYS, true);
        $storedValue = $value;

        if ($isEncrypted && $value) {
            $storedValue = Crypt::encryptString($value);
        }

        return UserSetting::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $storedValue, 'is_encrypted' => $isEncrypted]
        );
    }

    public function getMany(int $userId, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($userId, $key);
        }

        return $result;
    }
}
