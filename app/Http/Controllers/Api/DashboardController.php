<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ExecutionLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $account = WhatsAppAccount::where('user_id', $userId)->first();

        return response()->json([
            'total_messages' => Message::where('user_id', $userId)->count(),
            'active_workflows' => Workflow::where('user_id', $userId)->where('is_active', true)->count(),
            'inbox_conversations' => Conversation::where('user_id', $userId)->count(),
            'contacts_count' => Contact::where('user_id', $userId)->count(),
            'whatsapp_connected' => (bool) ($account?->is_connected),
            'whatsapp_display' => $account?->display_phone_number,
            'ai_usage' => ExecutionLog::whereHas('execution', fn ($q) => $q->where('user_id', $userId))
                ->where('node_type', 'ai')
                ->count(),
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $activities = Activity::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json(['activities' => $activities]);
    }
}
