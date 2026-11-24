{{-- @include('partials.badge', ['group' => 'petition_status', 'value' => $p->status]) --}}
@if (filled($value ?? null))
    <span class="badge-{{ \App\Models\Lookup::color($group, $value) }}">{{ lookup_label($group, $value) }}</span>
@endif
