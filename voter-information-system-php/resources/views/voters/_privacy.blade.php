{{-- consent and privacy box on the voter page --}}
@if(\App\Services\Privacy\PrivacyService::enabled() && (can('VOTER_EDIT') || can('PRIVACY_ADMIN')))
@php($cv = $v->consent_contact)
<div class="card mb-3 no-print"><div class="card-header fw-semibold d-flex align-items-center gap-2"><i class="bi bi-shield-check"></i> {{ t('privacy.box') }}
    <span class="ms-auto badge {{ $cv === 'Y' ? 'text-bg-success' : ($cv === 'N' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ t('privacy.consent.' . ($cv ?? 'U')) }}</span></div>
<div class="card-body">
    @if($v->consent_at)<p class="small text-muted mb-2">{{ t('privacy.consentWhen', $v->consent_at->format('d-m-Y H:i'), $v->consent_by, t('privacy.source.' . ($v->consent_source ?: 'other'))) }}</p>@endif
    @if($v->erased_at)<div class="alert alert-secondary py-2 small"><i class="bi bi-eraser"></i> {{ t('privacy.erasedOn', $v->erased_at->format('d-m-Y')) }}</div>@endif
    @if(can('VOTER_EDIT'))
    <form method="post" action="{{ url('/voters/' . $v->id . '/consent') }}" class="row g-2 align-items-end mb-2">@csrf
        <div class="col-md-4"><label class="form-label small mb-0">{{ t('privacy.consentLabel') }}</label>
            <select name="consent" class="form-select form-select-sm">@foreach(['Y', 'N', ''] as $c)<option value="{{ $c }}" @selected(($cv ?? '') === $c)>{{ t('privacy.consent.' . ($c ?: 'U')) }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label small mb-0">{{ t('privacy.how') }}</label>
            <select name="source" class="form-select form-select-sm">@foreach(\App\Services\Privacy\PrivacyService::SOURCES as $s)<option value="{{ $s }}">{{ t('privacy.source.' . $s) }}</option>@endforeach</select></div>
        <div class="col-md-3"><input name="note" class="form-control form-control-sm" maxlength="300" placeholder="{{ t('privacy.note') }}"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">{{ t('common.save') }}</button></div>
    </form>
    @endif
    @if(can('PRIVACY_ADMIN'))
    <div class="d-flex flex-wrap gap-2 align-items-start border-top pt-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/voters/' . $v->id . '/privacy/export') }}"><i class="bi bi-download"></i> {{ t('privacy.export') }}</a>
        <details class="flex-grow-1"><summary class="btn btn-sm btn-outline-danger"><i class="bi bi-eraser"></i> {{ t('privacy.erase') }}</summary>
            <form method="post" action="{{ url('/voters/' . $v->id . '/privacy/erase') }}" class="mt-2">@csrf
                <p class="small mb-1">{{ t('privacy.eraseHelp') }}</p>
                <div class="input-group input-group-sm"><input name="confirm" class="form-control" placeholder="{{ t('privacy.typeEpic', $v->epic_no) }}" required>
                    <input name="reason" class="form-control" placeholder="{{ t('privacy.reason') }}"><button class="btn btn-danger">{{ t('privacy.eraseBtn') }}</button></div></form>
        </details>
    </div>
    @endif
</div></div>
@endif
