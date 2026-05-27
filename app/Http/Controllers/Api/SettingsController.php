<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {}

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($validated);

        return response()->json(['user' => $request->user()->fresh()]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $request->user()->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Password updated']);
    }

    public function getIntegrations(Request $request): JsonResponse
    {
        $keys = ['openrouter_api_key', 'openai_api_key', 'ai_provider', 'ai_model'];

        $settings = $this->settingsService->getMany($request->user()->id, $keys);

        return response()->json([
            'ai_provider' => $settings['ai_provider'] ?? 'openrouter',
            'ai_model' => $settings['ai_model'] ?? 'openai/gpt-4o-mini',
            'has_openrouter_key' => ! empty($settings['openrouter_api_key']),
            'has_openai_key' => ! empty($settings['openai_api_key']),
        ]);
    }

    public function updateIntegrations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'openrouter_api_key' => ['nullable', 'string'],
            'openai_api_key' => ['nullable', 'string'],
            'ai_provider' => ['nullable', 'in:openrouter,openai'],
            'ai_model' => ['nullable', 'string'],
        ]);

        $userId = $request->user()->id;

        foreach ($validated as $key => $value) {
            if ($value !== null && $value !== '') {
                $this->settingsService->set($userId, $key, $value);
            }
        }

        return response()->json(['message' => 'Settings saved']);
    }
}
