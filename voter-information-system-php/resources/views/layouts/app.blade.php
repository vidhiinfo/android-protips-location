<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>@include('layouts.head', ['title' => trim($__env->yieldContent('title'))])</head>
<body class="@yield('bodyClass')" @yield('bodyAttrs')>
@unless(trim($__env->yieldContent('bare')))
<nav class="navbar navbar-expand-xl navbar-dark bg-primary mb-3 no-print">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ url($me->landingPage()) }}"><i class="bi bi-person-vcard"></i> <span class="brand-text">{{ t('app.name') }}</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                @if(can('DASHBOARD_VIEW'))<li class="nav-item"><a class="nav-link" href="{{ url('/dashboard') }}"><i class="bi bi-speedometer2"></i> {{ t('nav.dashboard') }}</a></li>@endif
                @if(can('VOTER_VIEW'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-people"></i> {{ t('nav.voters') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/voters') }}">{{ t('nav.voterList') }}</a></li>
                        @if(can('VOTER_EDIT'))<li><a class="dropdown-item" href="{{ url('/voters/new') }}">{{ t('nav.addVoter') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/survey') }}"><i class="bi bi-clipboard2-check"></i> {{ t('fs.title') }}</a></li>@endif
                        @if(can('WORKER_MANAGE'))<li><a class="dropdown-item" href="{{ url('/workers') }}"><i class="bi bi-person-badge"></i> {{ t('worker.title') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/workers/tasks') }}"><i class="bi bi-list-check"></i> {{ t('task.title') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/workers/attendance') }}"><i class="bi bi-calendar-check"></i> {{ t('att.title') }}</a></li>@endif
                        @if(can('PHONE_BANK'))<li><a class="dropdown-item" href="{{ url('/phonebank') }}"><i class="bi bi-telephone"></i> {{ t('pb.title') }}</a></li>@endif
                        @if(can('VOTER_IMPORT'))<li><a class="dropdown-item" href="{{ url('/voters/import') }}">{{ t('nav.import') }}</a></li>@endif
                        @if(can('ROLL_UPDATE'))<li><a class="dropdown-item" href="{{ url('/roll-update') }}">{{ t('nav.rollUpdate') }}</a></li>@endif
                        @if(can('VOTER_EDIT'))<li><a class="dropdown-item" href="{{ url('/voters/bulk-changes') }}">{{ t('bulkUndo.title') }}</a></li>@endif
                        @if(can('VOTER_EDIT') && can('VOTER_DELETE'))<li><a class="dropdown-item" href="{{ url('/voters/duplicates') }}">{{ t('dup.title') }}</a></li>@endif
                    </ul></li>
                @endif
                @if(can('MASTER_VIEW'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-database"></i> {{ t('nav.masters') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/masters/assemblies') }}">{{ t('master.assemblies') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/booths') }}">{{ t('master.booths') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/parties') }}">{{ t('master.parties') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/elections') }}">{{ t('master.elections') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/candidates') }}">{{ t('master.candidates') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/castes') }}">{{ t('master.castes') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/subcastes') }}">{{ t('master.subCastes') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/socialstatuses') }}">{{ t('master.socialStatuses') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/politicalstatuses') }}">{{ t('master.politicalStatuses') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/orgstatuses') }}">{{ t('master.orgStatuses') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/masters/beneficiaries') }}">{{ t('master.beneficiaries') }}</a></li>
                    </ul></li>
                @endif
                @if(can('RESULT_VIEW'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-trophy"></i> {{ t('nav.results') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/results') }}">{{ t('result.overview') }}</a></li>
                        @if(can('RESULT_ENTRY'))<li><a class="dropdown-item" href="{{ url('/results/entry') }}">{{ t('result.entry') }}</a></li>@endif
                    </ul></li>
                @endif
                @if(can('REPORT_VIEW'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-bar-chart"></i> {{ t('nav.reports') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/reports') }}">{{ t('report.all') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        @foreach($reportIds as $r)<li><a class="dropdown-item" href="{{ url('/reports/'.$r) }}">{{ t('report.'.$r) }}</a></li>@endforeach
                    </ul></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-graph-up-arrow"></i> {{ t('nav.analytics') }}</a>
                    <ul class="dropdown-menu">
                        @if(can('STATE_VIEW') && \App\Services\State\StateSummaryService::enabled())<li><a class="dropdown-item fw-semibold" href="{{ url('/state') }}"><i class="bi bi-map"></i> {{ t('state.title') }}</a></li>@endif
                        <li><a class="dropdown-item" href="{{ url('/analytics') }}">{{ t('an.home') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        @foreach($analyticsIds as $r)<li><a class="dropdown-item" href="{{ url('/reports/'.$r) }}">{{ t('report.'.$r) }}</a></li>@endforeach
                    </ul></li>
                @endif
                @if(can('POLL_DAY'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-check2-square"></i> {{ t('poll.menu') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/pollday') }}">{{ t('poll.title') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/pollday/grid') }}">{{ t('poll.grid') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/pollday/live') }}"><i class="bi bi-broadcast text-danger"></i> {{ t('poll.live') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/transport') }}"><i class="bi bi-truck"></i> {{ t('tr.title') }}</a></li>
                    </ul></li>
                @endif
                @if(can('GRIEVANCE'))<li class="nav-item"><a class="nav-link" href="{{ url('/grievances') }}"><i class="bi bi-megaphone"></i> {{ t('gr.title') }}</a></li>@endif
                @if(can('MESSAGE_SEND'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-chat-dots"></i> {{ t('nav.messages') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/messages/campaigns') }}">{{ t('msg.campaigns') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/messages/compose') }}">{{ t('msg.new') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/messages/inbox') }}"><i class="bi bi-inbox"></i> {{ t('inbox.title') }}</a></li>
                        @if(can('MESSAGE_ADMIN'))
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url('/messages/templates') }}">{{ t('msg.templates') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/messages/greetings') }}"><i class="bi bi-gift"></i> {{ t('greet.title') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/messages/optouts') }}">{{ t('msg.optouts') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/messages/settings') }}">{{ t('msg.settings') }}</a></li>
                        @endif
                    </ul></li>
                @endif
                @if(canAny('USER_ADMIN', 'AUDIT_VIEW', 'SYSTEM_VIEW'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> {{ t('nav.admin') }}</a>
                    <ul class="dropdown-menu">
                        @if(can('USER_ADMIN'))<li><a class="dropdown-item" href="{{ url('/admin/users') }}">{{ t('admin.users') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/admin/roles') }}">{{ t('admin.roles') }}</a></li>@endif
                        @if(can('AUDIT_VIEW'))<li><a class="dropdown-item" href="{{ url('/admin/audit') }}">{{ t('admin.audit') }}</a></li>@endif
                        @if(can('SYSTEM_VIEW'))<li><a class="dropdown-item" href="{{ url('/admin/system') }}">{{ t('admin.system') }}</a></li>@endif
                        @if(can('USER_ADMIN'))<li><a class="dropdown-item" href="{{ url('/admin/settings') }}"><i class="bi bi-toggles"></i> {{ t('settings.title') }}</a></li>
                        <li><a class="dropdown-item" href="{{ url('/admin/backups') }}">{{ t('backup.title') }}</a></li>@endif
                        @if(can('PRIVACY_ADMIN'))<li><a class="dropdown-item" href="{{ url('/admin/privacy') }}">{{ t('privacy.title') }}</a></li>@endif
                    </ul></li>
                @endif
            </ul>
            <span class="badge text-bg-light me-2 mb-2 mb-xl-0" title="{{ t('login.assembly') }}"><i class="bi bi-geo-alt"></i>
                {{ $me->scoped() ? $me->workAssembly() . ' - ' . $me->workAssemblyName() : t('login.allAssemblies') }}@if($areaLabel = \App\Support\Area::label(\App\Support\Area::current())) · {{ $areaLabel }}@endif</span>
            @include('partials.lang', ['cls' => 'w-auto me-2 mb-2 mb-xl-0'])
            <div class="dropdown">
                @php($dueTasks = app(\App\Services\Workers\TaskService::class)->dueFor($me->username()))
                <a class="btn btn-sm btn-outline-light dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> {{ $me->username() }}@if($dueTasks) <span class="badge rounded-pill text-bg-danger" title="{{ t('task.dueBadge') }}">{{ $dueTasks }}</span>@endif</a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ url('/my/tasks') }}"><i class="bi bi-list-check"></i> {{ t('task.my') }}@if($dueTasks) <span class="badge text-bg-danger">{{ $dueTasks }}</span>@endif</a></li>
                    <li><a class="dropdown-item" href="{{ url('/my/attendance') }}"><i class="bi bi-calendar-check"></i> {{ t('att.my') }}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ url('/account/password') }}">{{ t('account.changePassword') }}</a></li>
                    @if(config('voters.security.user_source') !== 'external')<li><a class="dropdown-item" href="{{ url('/account/security') }}"><i class="bi bi-shield-lock"></i> {{ t('otp.account') }}</a></li>@endif
                    <li class="native-hide"><a class="dropdown-item" href="{{ url('/mobile-app') }}"><i class="bi bi-phone"></i> {{ t('app.install.link') }}</a></li>
                    <li><form action="{{ url('/logout') }}" method="post" data-logout data-l-unsent="{{ t('off.unsentLogout') }}">@csrf<button class="dropdown-item">{{ t('nav.logout') }}</button></form></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
@endunless
@yield('content')
@if(config('voters.branding.owner'))
<footer class="text-center small text-muted py-3 no-print">{{ t('app.name') }} · {{ t('brand.copyright', date('Y'), config('voters.branding.owner')) }}</footer>
@endif
@stack('modals')
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ versioned_asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
