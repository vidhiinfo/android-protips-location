<?php

use App\Http\Controllers\{AccountController, AdminController, AuthController, DashboardController, MasterController, MessagingController,
    ReportController, ResultController, VoterController};
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout']);

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
        Route::get('/admin/roles', [AdminController::class, 'roles']);
        Route::get('/admin/roles/new', [AdminController::class, 'roleForm']);
        Route::get('/admin/roles/{id}/edit', [AdminController::class, 'roleForm'])->whereNumber('id');
        Route::post('/admin/roles', [AdminController::class, 'saveRole']);
        Route::post('/admin/roles/{id}/delete', [AdminController::class, 'deleteRole'])->whereNumber('id');
    });
    Route::get('/admin/audit', [AdminController::class, 'auditLog'])->middleware('perm:AUDIT_VIEW');
    Route::get('/admin/system', [AdminController::class, 'system'])->middleware('perm:SYSTEM_VIEW');

    Route::get('/account/password', [AccountController::class, 'form']);
    Route::post('/account/password', [AccountController::class, 'change']);
});
