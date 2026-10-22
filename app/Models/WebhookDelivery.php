<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'event_type' => WebhookEventType::class,
            'status' => WebhookDeliveryStatus::class,
            'last_attempted_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
