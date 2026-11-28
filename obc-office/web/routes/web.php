<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BiodataController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CircularController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GreetingController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MobileAppController;
use App\Http\Controllers\PetitionController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Route;

// Logo / leader photo (public: used on the login page)
Route::get('/branding/{type}', BrandingController::class)->whereIn('type', ['logo', 'leader', 'login-bg'])->name('branding.image');

// Mobile app: public install page + direct APK link (can be switched off in Administration → Mobile App)
Route::get('/app', [MobileAppController::class, 'publicPage'])->name('app.public');
Route::get('/app/download', [MobileAppController::class, 'download'])->name('app.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // Organisation
    Route::get('/directory', [MemberController::class, 'directory'])->name('directory');
    Route::get('/members/import', [ImportController::class, 'form'])->name('import.form');
    Route::post('/members/import', [ImportController::class, 'import'])->name('import.run');
    Route::get('/members/import/template', [ImportController::class, 'template'])->name('import.template');
    Route::get('/members/export', [ImportController::class, 'export'])->name('members.export');
    Route::get('/members/{member}/photo', [MemberController::class, 'photo'])->name('members.photo');
    Route::delete('/members/{member}/photo', [MemberController::class, 'removePhoto'])->name('members.photo.remove');
    Route::post('/members/{member}/approve', [MemberController::class, 'approve'])->name('members.approve');
    Route::resource('members', MemberController::class);
    Route::get('/states/{state}/districts', [MemberController::class, 'districts'])->name('states.districts');

    Route::resource('circulars', CircularController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::get('/circulars/{circular}/attachment', [CircularController::class, 'attachment'])->name('circulars.attachment');

    Route::resource('programs', ProgramController::class);
    Route::post('/programs/{program}/reports', [ProgramController::class, 'storeReport'])->name('programs.reports.store');
    Route::get('/program-reports/{report}/photos/{index}', [ProgramController::class, 'reportPhoto'])->name('programs.reports.photo');

    // President's office
    Route::resource('schedule', ScheduleController::class)->parameters(['schedule' => 'id']);
    Route::resource('visitors', VisitorController::class)->parameters(['visitors' => 'id']);
    Route::resource('contacts', ContactController::class)->parameters(['contacts' => 'id']);
    Route::resource('letters', LetterController::class)->parameters(['letters' => 'id']);
    Route::resource('tasks', TaskController::class)->parameters(['tasks' => 'id']);
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');

    Route::resource('petitions', PetitionController::class);
    Route::post('/petitions/{petition}/updates', [PetitionController::class, 'addUpdate'])->name('petitions.updates.store');
    Route::get('/petitions/{petition}/acknowledgement', [PetitionController::class, 'acknowledgement'])->name('petitions.ack');
    Route::get('/petitions/{petition}/attachment', [PetitionController::class, 'attachment'])->name('petitions.attachment');

    Route::get('/greetings', GreetingController::class)->name('greetings');

    Route::get('/biodata/export', [BiodataController::class, 'export'])->name('biodata.export');
    Route::resource('biodata', BiodataController::class);
    Route::patch('/biodata/{biodatum}/status', [BiodataController::class, 'updateStatus'])->name('biodata.status');
    Route::get('/biodata/{biodatum}/print', [BiodataController::class, 'print'])->name('biodata.print');
    Route::get('/biodata/{biodatum}/photo', [BiodataController::class, 'photo'])->name('biodata.photo');
    Route::delete('/biodata/{biodatum}/photo', [BiodataController::class, 'removePhoto'])->name('biodata.photo.remove');
    Route::post('/biodata/{biodatum}/documents', [BiodataController::class, 'addDocuments'])->name('biodata.documents.store');
    Route::get('/biodata-documents/{document}', [BiodataController::class, 'document'])->name('biodata.documents.show');
    Route::delete('/biodata-documents/{document}', [BiodataController::class, 'destroyDocument'])->name('biodata.documents.destroy');

    // Administration
    Route::resource('users', UserController::class)->except(['show']);

    Route::prefix('masters')->name('masters.')->controller(MasterController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/states', 'storeState')->name('states.store');
        Route::post('/states/{state}/districts', 'storeDistricts')->name('districts.store');
        Route::put('/districts/{district}', 'updateDistrict')->name('districts.update');
        Route::delete('/districts/{district}', 'destroyDistrict')->name('districts.destroy');
        Route::post('/designations', 'storeDesignation')->name('designations.store');
        Route::put('/designations/{designation}', 'updateDesignation')->name('designations.update');
        Route::delete('/designations/{designation}', 'destroyDesignation')->name('designations.destroy');
    });

    Route::prefix('settings')->name('settings.')->controller(SettingsController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/general', 'saveGeneral')->name('general');
        Route::post('/access', 'saveAccess')->name('access');
        Route::post('/lookups', 'storeLookup')->name('lookups.store');
        Route::put('/lookups/{lookup}', 'updateLookup')->name('lookups.update');
        Route::delete('/lookups/{lookup}', 'destroyLookup')->name('lookups.destroy');
        Route::post('/fields', 'storeField')->name('fields.store');
        Route::put('/fields/{field}', 'updateField')->name('fields.update');
        Route::delete('/fields/{field}', 'destroyField')->name('fields.destroy');
    });

    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

    Route::get('/mobile-app', [MobileAppController::class, 'index'])->name('mobile-app.index');
    Route::post('/mobile-app/upload', [MobileAppController::class, 'upload'])->name('mobile-app.upload');
    Route::post('/mobile-app/settings', [MobileAppController::class, 'saveSettings'])->name('mobile-app.settings');
});
