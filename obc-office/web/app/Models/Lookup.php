<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Lookup extends Model
{
    public $timestamps = false;

    protected $fillable = ['group', 'value', 'label', 'color', 'sort', 'active', 'locked'];

    protected $casts = ['active' => 'boolean', 'locked' => 'boolean'];

    /** Groups shown on the Settings → Dropdown lists screen. */
    public const GROUPS = [
        'level' => 'Organisation levels',
        'member_type' => 'Member types',
        'member_status' => 'Member statuses',
        'gender' => 'Gender',
        'profession' => 'Professions',
        'education' => 'Education',
        'petition_category' => 'Petition categories',
        'petition_status' => 'Petition statuses',
        'priority' => 'Priorities',
        'schedule_kind' => 'Schedule entry types',
        'schedule_status' => 'Schedule statuses',
        'visitor_status' => 'Visitor statuses',
        'task_status' => 'Task statuses',
        'contact_category' => 'VIP contact categories',
        'letter_status' => 'Letter statuses',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('office.lookups'));
        static::deleted(fn () => Cache::forget('office.lookups'));
    }

    /** All lookups grouped, as plain arrays (cache-safe). */
    private static function cached(): array
    {
        return Cache::rememberForever('office.lookups', fn () => static::query()
            ->orderBy('sort')->orderBy('label')->get(['group', 'value', 'label', 'color', 'active'])
            ->map(fn ($l) => ['group' => $l->group, 'value' => $l->value, 'label' => $l->label, 'color' => $l->color, 'active' => (bool) $l->active])
            ->groupBy('group')->map(fn ($g) => $g->values()->all())->all());
    }

    /** @return array<string,string> value => label, active only, in configured order */
    public static function options(string $group, bool $includeInactive = false): array
    {
        $all = static::cached();

        return collect($all[$group] ?? [])
            ->filter(fn ($l) => $includeInactive || $l['active'])
            ->pluck('label', 'value')->all();
    }

    public static function color(string $group, ?string $value): string
    {
        $all = static::cached();
        $c = collect($all[$group] ?? [])->firstWhere('value', $value)['color'] ?? null;

        return in_array($c, ['gray', 'blue', 'green', 'amber', 'red'], true) ? $c : 'gray';
    }

    public static function label(string $group, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return static::options($group, true)[$value] ?? $value;
    }
}
