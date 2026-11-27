<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A party worker (booth president, booth agent, panna pramukh, volunteer) with the voters assigned to them. */
class Worker extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean', 'created_at' => 'datetime'];
    public const ROLES = ['LS_INCHARGE', 'AC_INCHARGE', 'MANDAL_INCHARGE', 'BOOTH_PRESIDENT', 'BOOTH_AGENT', 'PANNA_PRAMUKH', 'VOLUNTEER'];
    /** Place in the organisation chain (0 = top). Booth agents and panna pramukhs work under the booth president. */
    public const LEVEL = ['LS_INCHARGE' => 0, 'AC_INCHARGE' => 1, 'MANDAL_INCHARGE' => 2, 'BOOTH_PRESIDENT' => 3, 'BOOTH_AGENT' => 4, 'PANNA_PRAMUKH' => 4, 'VOLUNTEER' => 5];
    /** A Lok Sabha in-charge covers every assembly: stored with assembly_no 0. */
    public const ALL_ASSEMBLIES = 0;

    public function voters() { return $this->hasMany(Voter::class); }
    public function mandal() { return $this->belongsTo(Mandal::class); }
    /** The worker's own voter record (when picked from the voter list). */
    public function voter() { return $this->belongsTo(Voter::class, 'voter_id'); }
    public function level(): int { return self::LEVEL[\App\Support\WorkerRoles::base($this->role)] ?? 5; }
    /** The built-in role this worker's role works like (own roles from Masters > Worker roles). */
    public function base(): string { return \App\Support\WorkerRoles::base($this->role); }
    public function label(): string { return $this->name . ($this->booth_no ? ' (' . t('col.boothNo') . ' ' . $this->booth_no . ')' : ''); }

    /** The worker record linked to a login, if any (that login then sees only its own voters). */
    public static function forUsername(?string $username): ?self
    {
        static $cache = [];
        if (!$username) return null;
        if (!array_key_exists($username, $cache)) {
            $cache[$username] = \Illuminate\Support\Facades\Schema::hasTable('workers') ? self::where('username', $username)->where('active', true)->first() : null;
        }
        return $cache[$username];
    }
}
