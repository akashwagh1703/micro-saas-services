<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WhatsAppAccountController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::get('/webhook/whatsapp/{userId}', [WebhookController::class, 'verify']);
Route::post('/webhook/whatsapp/{userId}', [WebhookController::class, 'receive']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/activity', [DashboardController::class, 'recentActivity']);

    Route::get('/whatsapp', [WhatsAppAccountController::class, 'show']);
    Route::put('/whatsapp', [WhatsAppAccountController::class, 'update']);
    Route::post('/whatsapp/test', [WhatsAppAccountController::class, 'test']);
    Route::post('/whatsapp/disconnect', [WhatsAppAccountController::class, 'disconnect']);

    Route::apiResource('contacts', ContactController::class);

    Route::get('/inbox/conversations', [InboxController::class, 'conversations']);
    Route::get('/inbox/conversations/{conversationId}/messages', [InboxController::class, 'messages']);
    Route::post('/inbox/conversations/{conversationId}/send', [InboxController::class, 'send']);

    Route::get('/workflows/templates/list', [WorkflowController::class, 'templates']);
    Route::post('/workflows/templates/seed-all', [WorkflowController::class, 'seedAllTemplates']);
    Route::post('/workflows/templates/{slug}/clone', [WorkflowController::class, 'cloneTemplate']);
    Route::post('/workflows/validate', [WorkflowController::class, 'validateDefinition']);
    Route::post('/workflows/{id}/publish', [WorkflowController::class, 'publish']);
    Route::post('/workflows/{id}/unpublish', [WorkflowController::class, 'unpublish']);
    Route::get('/workflows/{id}/executions', [WorkflowController::class, 'executions']);
    Route::apiResource('workflows', WorkflowController::class);

    Route::get('/settings/profile', [SettingsController::class, 'profile']);
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile']);
    Route::put('/settings/password', [SettingsController::class, 'changePassword']);
    Route::get('/settings/integrations', [SettingsController::class, 'getIntegrations']);
    Route::put('/settings/integrations', [SettingsController::class, 'updateIntegrations']);
});
