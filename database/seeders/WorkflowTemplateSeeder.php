<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Workflow\WorkflowTemplateService;
use Illuminate\Database\Seeder;

class WorkflowTemplateSeeder extends Seeder
{
    public function run(WorkflowTemplateService $templateService): void
    {
        User::query()->each(function (User $user) use ($templateService) {
            $templateService->seedAllForUser($user->id);
        });

        $this->command?->info('Workflow templates seeded for all users.');
    }
}
