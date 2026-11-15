<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'message_campaigns';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['scheduled_at' => 'datetime', 'created_at' => 'datetime', 'completed_at' => 'datetime',
        'total' => 'integer', 'sent' => 'integer', 'failed' => 'integer', 'skipped' => 'integer'];

    public function template() { return $this->belongsTo(MessageTemplate::class, 'template_id'); }

    public function pending(): int { return max(0, $this->total - $this->sent - $this->failed - $this->skipped); }
    public function progress(): int { return $this->total === 0 ? 100 : (int) round(100 * ($this->total - $this->pending()) / $this->total); }
}
