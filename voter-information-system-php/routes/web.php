<?php

use App\Http\Controllers\{AccountController, AdminController, AuthController, DashboardController, MasterController, MessagingController,
    ReportController, ResultController, VoterController};
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout']);
// second login step (password already checked; see TwoStepController)
Route::get('/login/otp', [\App\Http\Controllers\TwoStepController::class, 'challenge']);
Route::post('/login/otp', [\App\Http\Controllers\TwoStepController::class, 'verify'])->middleware('throttle:10,1');
Route::post('/login/otp/resend', [\App\Http\Controllers\TwoStepController::class, 'resend'])->middleware('throttle:5,1');
Route::get('/login/otp/setup', [\App\Http\Controllers\TwoStepController::class, 'setup']);
Route::post('/login/otp/setup', [\App\Http\Controllers\TwoStepController::class, 'setupStart'])->middleware('throttle:10,1');
Route::post('/login/otp/confirm', [\App\Http\Controllers\TwoStepController::class, 'setupConfirm'])->middleware('throttle:10,1');
// fresh security token for a page that was left open (phones): see public/js/app.js
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'));
// installable-app manifest with the product name from .env (BRAND_APP_NAME), for a renamed installation
Route::get('/site.webmanifest', function () {
    $m = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
    $name = (string) config('voters.branding.app_name');
    if ($name !== '') {
        $m['name'] = $name;
        $m['short_name'] = (string) (config('voters.branding.short_name') ?: mb_substr($name, 0, 12));
    }
    $m['start_url'] = url('dashboard?source=app');
    $m['scope'] = rtrim(url('/'), '/') . '/';
    foreach ($m['icons'] ?? [] as $i => $icon) $m['icons'][$i]['src'] = asset($icon['src']);
    foreach ($m['shortcuts'] ?? [] as $i => $sc) { $m['shortcuts'][$i]['url'] = url(ltrim($sc['url'], '.')); foreach ($sc['icons'] ?? [] as $j => $ic) $m['shortcuts'][$i]['icons'][$j]['src'] = asset($ic['src']); }
    return response()->json($m, 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});
// state summary feed for the state server (token, totals only; see StateController)
Route::get('/state-feed', [\App\Http\Controllers\StateController::class, 'feed'])->middleware('throttle:30,1');
// Mobile app: how to install on Android / iPhone (no login needed, so it can be shared as a link or QR code)
Route::get('/mobile-app', fn () => view('app-install', [
    'apk' => is_file(public_path('downloads/voter-system.apk')) ? 'downloads/voter-system.apk' : null,
    'play' => config('voters.app.play_url'), 'appStore' => config('voters.app.app_store_url'),
]));

Route::middleware('login.required')->group(function () {
    Route::get('/', [AuthController::class, 'home']);

    Route::middleware('perm:DASHBOARD_VIEW')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'page']);
        Route::get('/dashboard/data', [DashboardController::class, 'data']);
    });

    // ---- voters
    Route::middleware('perm:VOTER_EDIT')->group(function () {
        Route::get('/voters/new', [VoterController::class, 'create']);
        Route::post('/voters', [VoterController::class, 'store']);
        Route::get('/voters/{voter}/edit', [VoterController::class, 'edit'])->whereNumber('voter');
        Route::post('/voters/{voter}', [VoterController::class, 'update'])->whereNumber('voter');
    });
    Route::post('/voters/{voter}/delete', [VoterController::class, 'destroy'])->whereNumber('voter')->middleware('perm:VOTER_DELETE');
    Route::middleware('perm:VOTER_IMPORT')->group(function () {
        Route::get('/voters/import', [VoterController::class, 'importForm']);
        Route::get('/voters/import/template', [VoterController::class, 'template']);
        Route::post('/voters/import', [VoterController::class, 'import']);
    });
    Route::middleware('perm:STATE_VIEW')->group(function () {
        Route::get('/state', [\App\Http\Controllers\StateController::class, 'index']);
        Route::get('/state/export', [\App\Http\Controllers\StateController::class, 'export']);
    });
    Route::middleware('perm:ROLL_UPDATE')->group(function () {
        $ru = \App\Http\Controllers\RollUpdateController::class;
        Route::get('/roll-update', [$ru, 'index']);
        Route::post('/roll-update', [$ru, 'upload']);
        Route::get('/roll-update/{id}', [$ru, 'show'])->whereNumber('id');
        Route::get('/roll-update/{id}/report', [$ru, 'report'])->whereNumber('id');
        Route::post('/roll-update/{id}/apply', [$ru, 'apply'])->whereNumber('id');
        Route::post('/roll-update/{id}/undo', [$ru, 'undo'])->whereNumber('id');
        Route::post('/roll-update/{id}/discard', [$ru, 'discard'])->whereNumber('id');
    });
    Route::get('/voters/export/slips', [VoterController::class, 'slips'])->middleware('perm:VOTER_EXPORT');
    Route::middleware('perm:WORKER_MANAGE')->group(function () {
        $w = \App\Http\Controllers\WorkerController::class;
        Route::get('/workers', [$w, 'index']);
        Route::get('/workers/new', [$w, 'form']);
        Route::post('/workers', [$w, 'save']);
        Route::post('/workers/panna', [$w, 'panna']);
        Route::get('/workers/{id}', [$w, 'show'])->whereNumber('id');
        Route::get('/workers/{id}/edit', [$w, 'form'])->whereNumber('id');
        Route::post('/workers/{id}', [$w, 'save'])->whereNumber('id');
        Route::post('/workers/{id}/assign', [$w, 'assign'])->whereNumber('id');
        Route::post('/workers/{id}/unassign', [$w, 'unassign'])->whereNumber('id');
        Route::post('/workers/{id}/delete', [$w, 'destroy'])->whereNumber('id');
    });
    Route::middleware('perm:WORKER_MANAGE')->group(function () {
        $tc = \App\Http\Controllers\TaskController::class;
        Route::get('/workers/tasks', [$tc, 'index']);
        Route::post('/workers/tasks', [$tc, 'save']);
        Route::post('/workers/tasks/{id}', [$tc, 'save'])->whereNumber('id');
        Route::post('/workers/tasks/{id}/status', [$tc, 'status'])->whereNumber('id');
        Route::post('/workers/tasks/{id}/delete', [$tc, 'destroy'])->whereNumber('id');
        Route::get('/workers/attendance', [$tc, 'attendance']);
    });
    Route::get('/my/tasks', [\App\Http\Controllers\TaskController::class, 'mine']);
    Route::post('/my/tasks/{id}', [\App\Http\Controllers\TaskController::class, 'report'])->whereNumber('id');
    Route::get('/my/attendance', [\App\Http\Controllers\TaskController::class, 'myAttendance']);
    Route::post('/my/attendance', [\App\Http\Controllers\TaskController::class, 'punch'])->middleware('throttle:10,1');
    Route::middleware('perm:POLL_DAY')->group(function () {
        $p = \App\Http\Controllers\PollDayController::class;
        Route::get('/pollday', [$p, 'index']);
        Route::post('/pollday/mark', [$p, 'mark']);
        Route::post('/pollday/unmark', [$p, 'unmark']);
        Route::get('/pollday/grid', [$p, 'grid']);
        Route::post('/pollday/toggle', [$p, 'toggle']);
        Route::get('/pollday/live', [$p, 'live']);
    });
    Route::middleware('perm:GRIEVANCE')->group(function () {
        $g = \App\Http\Controllers\GrievanceController::class;
        Route::get('/grievances', [$g, 'index']);
        Route::get('/grievances/new', [$g, 'form']);
        Route::post('/grievances', [$g, 'save']);
        Route::get('/grievances/{id}', [$g, 'show'])->whereNumber('id');
        Route::get('/grievances/{id}/edit', [$g, 'form'])->whereNumber('id');
        Route::post('/grievances/{id}', [$g, 'save'])->whereNumber('id');
        Route::post('/grievances/{id}/status', [$g, 'status'])->whereNumber('id');
    });
    Route::post('/voters/bulk', [VoterController::class, 'bulkUpdate'])->middleware('perm:VOTER_EDIT');
    Route::post('/voters/{id}/consent', [\App\Http\Controllers\PrivacyController::class, 'consent'])->whereNumber('id')->middleware('perm:VOTER_EDIT');
    Route::middleware('perm:PRIVACY_ADMIN')->group(function () {
        $pc = \App\Http\Controllers\PrivacyController::class;
        Route::get('/voters/{id}/privacy/export', [$pc, 'export'])->whereNumber('id');
        Route::post('/voters/{id}/privacy/erase', [$pc, 'erase'])->whereNumber('id');
        Route::get('/admin/privacy', [$pc, 'index']);
        Route::post('/admin/privacy', [$pc, 'store']);
        Route::post('/admin/privacy/{id}/close', [$pc, 'close'])->whereNumber('id');
        Route::post('/admin/privacy/retention', [$pc, 'retention']);
    });
    Route::middleware('perm:VOTER_EDIT')->group(function () {
        Route::get('/voters/bulk-changes', [\App\Http\Controllers\BulkChangeController::class, 'index']);
        Route::post('/voters/bulk-changes/{id}/undo', [\App\Http\Controllers\BulkChangeController::class, 'undo'])->whereNumber('id');
    });
    Route::middleware('perm:VOTER_EDIT')->group(function () {
        Route::get('/survey', [\App\Http\Controllers\FieldSurveyController::class, 'index']);
        Route::get('/survey/house/{voter}', [\App\Http\Controllers\FieldSurveyController::class, 'house'])->whereNumber('voter');
        Route::post('/survey/house/{voter}', [\App\Http\Controllers\FieldSurveyController::class, 'save'])->whereNumber('voter');
        Route::get('/survey/map', [\App\Http\Controllers\FieldSurveyController::class, 'map']);
        Route::get('/survey/offline', [\App\Http\Controllers\FieldSurveyController::class, 'offline']);
        Route::get('/survey/offline/data', [\App\Http\Controllers\FieldSurveyController::class, 'offlineData'])->middleware('throttle:20,1');
        Route::post('/survey/offline/sync', [\App\Http\Controllers\FieldSurveyController::class, 'offlineSync'])->middleware('throttle:30,1');
    });
    Route::post('/voters/{voter}/household/caste', [VoterController::class, 'householdCaste'])->whereNumber('voter')->middleware('perm:VOTER_EDIT');
    Route::middleware('perm:VOTER_EXPORT')->group(function () {
        Route::get('/voters/export/survey', [VoterController::class, 'surveySheet']);
        Route::get('/voters/export/excel', [VoterController::class, 'exportExcel']);
        Route::get('/voters/export/pdf', [VoterController::class, 'exportPdf']);
        Route::get('/voters/print', [VoterController::class, 'print']);
    });
    Route::middleware('perm:VOTER_VIEW')->group(function () {
        Route::get('/voters', [VoterController::class, 'index']);
        Route::get('/voters/{voter}', [VoterController::class, 'show'])->whereNumber('voter');
        Route::get('/voters/{voter}/household', [VoterController::class, 'household'])->whereNumber('voter');
    });

    // ---- masters
    Route::middleware('perm:MASTER_EDIT')->group(function () {
        Route::get('/masters/{type}/new', [MasterController::class, 'form']);
        Route::get('/masters/{type}/{id}/edit', [MasterController::class, 'form'])->whereNumber('id');
        Route::post('/masters/{type}', [MasterController::class, 'save']);
        Route::post('/masters/{type}/{id}/delete', [MasterController::class, 'delete'])->whereNumber('id');
    });
    Route::get('/masters/{type}', [MasterController::class, 'index'])->middleware('perm:MASTER_VIEW');

    // ---- results
    Route::middleware('perm:RESULT_ENTRY')->group(function () {
        Route::get('/results/entry', [ResultController::class, 'entry']);
        Route::post('/results/entry', [ResultController::class, 'saveEntry']);
    });
    Route::middleware('perm:RESULT_VIEW')->group(function () {
        Route::get('/results', [ResultController::class, 'overview']);
        Route::get('/results/contest', [ResultController::class, 'contest']);
        Route::get('/results/contest/export/{format}', [ResultController::class, 'exportContest']);
    });

    // ---- reports
    Route::middleware('perm:REPORT_VIEW')->group(function () {
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index']);
        Route::get('/reports/{id}', [ReportController::class, 'view']);
        Route::get('/reports/{id}/export/{format}', [ReportController::class, 'export']);
    });

    // ---- messages
    Route::middleware('perm:MESSAGE_SEND')->group(function () {
        Route::get('/messages', [MessagingController::class, 'campaigns']);
        Route::get('/messages/campaigns', [MessagingController::class, 'campaigns']);
        Route::get('/messages/compose', [MessagingController::class, 'compose']);
        Route::post('/messages/campaigns', [MessagingController::class, 'create']);
        Route::get('/messages/campaigns/{id}', [MessagingController::class, 'campaign'])->whereNumber('id');
        Route::post('/messages/campaigns/{id}/cancel', [MessagingController::class, 'cancel'])->whereNumber('id');
        Route::post('/messages/optouts', [MessagingController::class, 'addOptOut']);
    });
    Route::middleware('perm:MESSAGE_ADMIN')->group(function () {
        Route::get('/messages/templates', [MessagingController::class, 'templates']);
        Route::get('/messages/templates/new', [MessagingController::class, 'templateForm']);
        Route::get('/messages/templates/{id}/edit', [MessagingController::class, 'templateForm'])->whereNumber('id');
        Route::post('/messages/templates', [MessagingController::class, 'saveTemplate']);
        Route::post('/messages/templates/{id}/delete', [MessagingController::class, 'deleteTemplate'])->whereNumber('id');
        Route::get('/messages/optouts', [MessagingController::class, 'optOuts']);
        Route::post('/messages/optouts/{id}/delete', [MessagingController::class, 'deleteOptOut'])->whereNumber('id');
        Route::get('/messages/settings', [MessagingController::class, 'settings']);
        Route::post('/messages/settings/test', [MessagingController::class, 'test']);
    });

    // ---- admin
    Route::middleware('perm:USER_ADMIN')->group(function () {
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::get('/admin/users/new', [AdminController::class, 'userForm']);
        Route::get('/admin/users/{id}/edit', [AdminController::class, 'userForm'])->whereNumber('id');
        Route::post('/admin/users', [AdminController::class, 'saveUser']);
        Route::post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser'])->whereNumber('id');
        Route::post('/admin/users/{id}/otp-reset', [\App\Http\Controllers\TwoStepController::class, 'adminReset'])->whereNumber('id');
        Route::get('/admin/roles', [AdminController::class, 'roles']);
        Route::get('/admin/roles/new', [AdminController::class, 'roleForm']);
        Route::get('/admin/roles/{id}/edit', [AdminController::class, 'roleForm'])->whereNumber('id');
        Route::post('/admin/roles', [AdminController::class, 'saveRole']);
        Route::post('/admin/roles/{id}/delete', [AdminController::class, 'deleteRole'])->whereNumber('id');
    });
    Route::get('/admin/audit', [AdminController::class, 'auditLog'])->middleware('perm:AUDIT_VIEW');
    Route::get('/admin/system', [AdminController::class, 'system'])->middleware('perm:SYSTEM_VIEW');
    Route::middleware('perm:USER_ADMIN')->group(function () {
        $bk = \App\Http\Controllers\BackupController::class;
        Route::get('/admin/backups', [$bk, 'index']);
        Route::post('/admin/backups', [$bk, 'run']);
        Route::get('/admin/backups/{name}', [$bk, 'file'])->where('name', '[A-Za-z0-9_.-]+');
    });

    Route::get('/account/password', [AccountController::class, 'form']);
    Route::get('/account/security', [\App\Http\Controllers\TwoStepController::class, 'security']);
    Route::get('/account/security/setup', [\App\Http\Controllers\TwoStepController::class, 'setup']);
    Route::post('/account/security/setup', [\App\Http\Controllers\TwoStepController::class, 'setupStart'])->middleware('throttle:10,1');
    Route::post('/account/security/confirm', [\App\Http\Controllers\TwoStepController::class, 'setupConfirm'])->middleware('throttle:10,1');
    Route::post('/account/security/disable', [\App\Http\Controllers\TwoStepController::class, 'disable'])->middleware('throttle:10,1');
    Route::post('/account/security/recovery', [\App\Http\Controllers\TwoStepController::class, 'recovery'])->middleware('throttle:10,1');
    Route::post('/account/password', [AccountController::class, 'change']);
});
