<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A party worker (booth president, booth agent, panna pramukh, volunteer) with the voters assigned to them. */
class Worker extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean', 'created_at' => 'datetime'];
    public const ROLES = ['BOOTH_PRESIDENT', 'BOOTH_AGENT', 'PANNA_PRAMUKH', 'VOLUNTEER'];

    public function voters() { return $this->hasMany(Voter::class); }
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
