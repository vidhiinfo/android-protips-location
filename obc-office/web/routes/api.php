<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BiodataController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\OfficeController;
use Illuminate\Support\Facades\Route;

/*
| REST API for the Android / iOS apps. Authenticate with POST /api/v1/login to get a
| Sanctum bearer token, then send "Authorization: Bearer <token>" on every request.
| Permissions and jurisdiction are exactly the same as in the web app.
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
    // Public: shown on the app's login screen before sign-in
    Route::get('/branding', [AuthController::class, 'branding'])->middleware('throttle:60,1')->name('branding');

    Route::middleware(['auth:sanctum', 'active', 'throttle:120,1'])->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/meta', MetaController::class)->name('meta');

        Route::get('/dashboard', [OfficeController::class, 'dashboard'])->name('dashboard');

        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::post('/members/{member}', [MemberController::class, 'update'])->name('members.update'); // POST: multipart with photo
        Route::get('/members/{member}/photo', [MemberController::class, 'photo'])->name('members.photo');

        Route::get('/biodata', [BiodataController::class, 'index'])->name('biodata.index');
        Route::post('/biodata', [BiodataController::class, 'store'])->name('biodata.store');
        Route::get('/biodata/{biodatum}', [BiodataController::class, 'show'])->name('biodata.show');
        Route::post('/biodata/{biodatum}/documents', [BiodataController::class, 'addDocuments'])->name('biodata.documents.store');
        Route::get('/biodata/{biodatum}/photo', [BiodataController::class, 'photo'])->name('biodata.photo');
        Route::get('/biodata-documents/{document}', [BiodataController::class, 'document'])->name('biodata.documents.show');

        Route::get('/schedule', [OfficeController::class, 'schedule'])->name('schedule');
        Route::get('/greetings', [OfficeController::class, 'greetings'])->name('greetings');
        Route::get('/circulars', [OfficeController::class, 'circulars'])->name('circulars');
        Route::get('/circulars/{circular}/attachment', [OfficeController::class, 'circularAttachment'])->name('circulars.attachment');
        Route::post('/circulars/{circular}/read', [OfficeController::class, 'readCircular'])->name('circulars.read');
        Route::get('/tasks', [OfficeController::class, 'tasks'])->name('tasks');
        Route::patch('/tasks/{task}', [OfficeController::class, 'updateTask'])->name('tasks.update');
        Route::get('/petitions', [OfficeController::class, 'petitions'])->name('petitions');
        Route::get('/petitions/{petition}', [OfficeController::class, 'petition'])->name('petitions.show');
        Route::post('/petitions/{petition}/updates', [OfficeController::class, 'petitionUpdate'])->name('petitions.updates');
        Route::get('/programs', [OfficeController::class, 'programs'])->name('programs');
        Route::post('/programs/{program}/reports', [OfficeController::class, 'submitProgramReport'])->name('programs.reports');
    });
});
