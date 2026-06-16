<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_subscription_id',
        'user_id',
        'client_id',
        'type',
        'location',
        'condition_value',
        'threshold',
        'message',
        'channels',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'channels' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function alertSubscription(): BelongsTo
    {
        return $this->belongsTo(AlertSubscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
