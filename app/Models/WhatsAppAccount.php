<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppAccount extends Model
{
    protected $fillable = [
        'user_id',
        'access_token',
        'phone_number_id',
        'business_account_id',
        'verify_token',
        'app_secret',
        'display_phone_number',
        'is_connected',
        'connected_at',
    ];

    protected $hidden = [
        'access_token',
        'verify_token',
        'app_secret',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'verify_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'is_connected' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
