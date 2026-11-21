<div class="list-group small mb-3">
    @foreach($calls as $c)
    <div class="list-group-item d-flex flex-wrap gap-2"><span class="text-muted">{{ substr($c->at, 0, 16) }}</span>
        <span class="badge {{ $c->outcome === 'ANSWERED' ? 'text-bg-success' : 'text-bg-light border' }}">{{ t('pb.o.' . $c->outcome) }}</span>
        @if($c->leaning)<span class="badge text-bg-primary">{{ t(['S' => 'an.support', 'N' => 'an.neutral', 'O' => 'an.opposition'][$c->leaning]) }}</span>@endif
        <span class="me-auto" dir="auto">{{ $c->notes }}</span><span class="text-muted">{{ $c->by_user }}</span></div>
    @endforeach
</div>
