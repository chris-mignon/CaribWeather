<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'location',
        'latitude',
        'longitude',
        'type',
        'threshold',
        'quiet_hours',
        'channels',
        'enabled',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'channels' => 'array',
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
