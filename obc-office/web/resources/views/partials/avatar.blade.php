{{-- @include('partials.avatar', ['member' => $m, 'size' => 'h-10 w-10']) --}}
<span class="avatar {{ $size ?? 'h-10 w-10 text-sm' }}">
    @if ($member->photo_url)
        <img src="{{ $member->photo_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
    @else
        {{ $member->initials() }}
    @endif
</span>
