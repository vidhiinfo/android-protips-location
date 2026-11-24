<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Birthdays and anniversaries of bearers and VIP contacts for the coming days. */
class GreetingService
{
    public function __construct(private User $user) {}

    public function upcoming(?int $days = null): Collection
    {
        $days ??= (int) setting('greeting_days_ahead', 7);
        $dates = collect(range(0, $days))->mapWithKeys(fn ($i) => [today()->addDays($i)->format('m-d') => $i]);
        $md = fn (string $col) => DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%m-%d', $col)" : "DATE_FORMAT($col, '%m-%d')";

        $items = collect();

        $members = $this->user->applyGeoScope(Member::query()->with(['designation', 'state', 'district']))
            ->where('status', 'active')
            ->whereRaw($md('dob').' IN ('.$dates->keys()->map(fn () => '?')->implode(',').')', $dates->keys()->all())
            ->get();
        foreach ($members as $m) {
            $items->push($this->item('birthday', $m->name, $m->dob, $m->designation?->title, $m->area(), $m->whatsapp ?: $m->mobile, $m->photo_url, $dates) + ['member_id' => $m->id]);
        }

        if ($this->user->canView('contacts')) {
            foreach (['dob' => 'birthday', 'anniversary' => 'anniversary'] as $col => $kind) {
                $contacts = Contact::with('state')
                    ->whereRaw($md($col).' IN ('.$dates->keys()->map(fn () => '?')->implode(',').')', $dates->keys()->all())->get();
                foreach ($contacts as $c) {
                    $items->push($this->item($kind, $c->name, $c->{$col}, $c->designation ?: lookup_label('contact_category', $c->category), $c->state?->name, $c->mobile, null, $dates));
                }
            }
        }

        return $items->sortBy('in_days')->values();
    }

    private function item(string $kind, string $name, Carbon $date, ?string $designation, ?string $area, ?string $mobile, ?string $photo, Collection $dates): array
    {
        $template = setting($kind === 'birthday' ? 'birthday_template' : 'anniversary_template', '');
        $message = render_template($template, ['name' => $name, 'designation' => $designation]);

        return [
            'kind' => $kind,
            'name' => $name,
            'designation' => $designation,
            'area' => $area,
            'mobile' => $mobile,
            'photo_url' => $photo,
            'date' => $date->format('d M'),
            'in_days' => $dates[$date->format('m-d')] ?? 0,
            'message' => $message,
            'whatsapp_url' => wa_link($mobile, $message),
        ];
    }
}
