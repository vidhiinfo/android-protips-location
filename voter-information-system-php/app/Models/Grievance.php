<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A complaint / problem raised by a voter, tracked from OPEN to RESOLVED. */
class Grievance extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime', 'updated_at' => 'datetime', 'resolved_at' => 'datetime', 'due_at' => 'datetime', 'escalated_at' => 'datetime'];
    public const CATEGORIES = ['WATER', 'ELECTRICITY', 'ROAD', 'SEWER', 'GARBAGE', 'RATION', 'PENSION', 'HEALTH', 'EDUCATION', 'DOCUMENTS', 'POLICE', 'HOUSING', 'EMPLOYMENT', 'OTHER'];
    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];
    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    public function voter() { return $this->belongsTo(Voter::class); }
    public function worker() { return $this->belongsTo(Worker::class); }
    public function isOpen(): bool { return in_array($this->status, ['OPEN', 'IN_PROGRESS'], true); }
    public function overdue(): bool { return $this->isOpen() && $this->due_at && $this->due_at->isPast(); }
    public function ageDays(): int { return (int) $this->created_at?->diffInDays($this->resolved_at ?? now()); }
}
