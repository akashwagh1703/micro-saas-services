<?php

namespace App\Services;

use App\Models\Activity;

class ActivityLogger
{
    public static function log(int $userId, string $type, string $title, ?string $description = null, ?array $metadata = null): void
    {
        Activity::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
