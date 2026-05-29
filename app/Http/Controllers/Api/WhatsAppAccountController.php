<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\ActivityLogger;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppAccountController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $account = WhatsAppAccount::firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json([
            'account' => [
                'phone_number_id' => $account->phone_number_id,
                'business_account_id' => $account->business_account_id,
                'display_phone_number' => $account->display_phone_number,
                'is_connected' => $account->is_connected,
                'connected_at' => $account->connected_at,
                'has_access_token' => ! empty($account->access_token),
                'has_verify_token' => ! empty($account->verify_token),
                'has_app_secret' => ! empty($account->app_secret),
            ],
            'webhook_url' => url('/api/webhook/whatsapp/'.$request->user()->id),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['nullable', 'string'],
            'phone_number_id' => ['nullable', 'string'],
            'business_account_id' => ['nullable', 'string'],
            'verify_token' => ['nullable', 'string'],
            'app_secret' => ['nullable', 'string'],
        ]);

        $account = WhatsAppAccount::firstOrCreate(['user_id' => $request->user()->id]);
        $account->fill(array_filter($validated, fn ($v) => $v !== null && $v !== ''));
        $account->save();

        ActivityLogger::log($request->user()->id, 'whatsapp_updated', 'WhatsApp credentials updated');

        return response()->json(['message' => 'Credentials saved', 'account' => $account->only([
            'phone_number_id', 'business_account_id', 'is_connected', 'display_phone_number',
        ])]);
    }

    public function test(Request $request): JsonResponse
    {
        $account = WhatsAppAccount::where('user_id', $request->user()->id)->firstOrFail();
        $result = $this->whatsAppService->testConnection($account);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $account = WhatsAppAccount::where('user_id', $request->user()->id)->firstOrFail();
        $account->update([
            'is_connected' => false,
            'access_token' => null,
            'connected_at' => null,
        ]);

        return response()->json(['message' => 'Disconnected']);
    }
}
