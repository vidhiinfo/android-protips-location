<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    protected $table = 'message_outbox';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    public const STATUSES = ['QUEUED', 'SENDING', 'SENT', 'FAILED', 'SKIPPED'];

    public function maskedMobile(): string { return \App\Services\Messaging\MessagingService::mask($this->mobile); }
}
