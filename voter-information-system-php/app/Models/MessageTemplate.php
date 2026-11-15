<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $table = 'message_templates';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean'];
    public const CHANNELS = ['SMS', 'WHATSAPP'];

    protected static function booted(): void
    {
        static::creating(fn ($t) => $t->created_at ??= now());
    }
}
