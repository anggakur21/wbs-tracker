<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityStatusController;
use App\Http\Controllers\Admin\DeliverableController as AdminDeliverableController;
use App\Http\Controllers\Admin\GoogleDriveController;
use App\Http\Controllers\Admin\PartnerAccountController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\WbsItemController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::middleware(['auth', RequirePasswordChange::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Own password (forced after an admin creates or resets the account)
    Route::get('/account/password', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    // Admin and partner
    Route::get('/', DashboardController::class)->name('home');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/deliverables/{deliverable}', [DeliverableController::class, 'show'])->name('deliverables.show');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');

    // Step status: the partner's Project Manager starts and submits; TIF admins accept or return
    Route::post('/activities/{activity}/start', [ActivityStatusController::class, 'start'])->name('activities.start');
    Route::post('/activities/{activity}/submit', [ActivityStatusController::class, 'submit'])->name('activities.submit');
    Route::post('/activities/{activity}/accept', [ActivityStatusController::class, 'accept'])->name('activities.accept');
    Route::post('/activities/{activity}/reject', [ActivityStatusController::class, 'reject'])->name('activities.reject');

    // In-app notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/activities/{activity}/evidences', [EvidenceController::class, 'store'])->name('evidences.store');
    Route::get('/evidences/{evidence}', [EvidenceController::class, 'download'])->name('evidences.download');
    Route::delete('/evidences/{evidence}', [EvidenceController::class, 'destroy'])->name('evidences.destroy');

    // TIF admin only
    Route::middleware(EnsureRole::class.':admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/projects/create', [AdminProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [AdminProjectController::class, 'store'])->name('projects.store');
        Route::delete('/projects/{project}', [AdminProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('/projects/{project}/deliverables', [AdminDeliverableController::class, 'store'])->name('deliverables.store');
        Route::delete('/deliverables/{deliverable}', [AdminDeliverableController::class, 'destroy'])->name('deliverables.destroy');
        Route::put('/activities/{activity}/plan', [ActivityController::class, 'updatePlan'])->name('activities.plan');

        // Partner companies and their people's accounts
        Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::get('/partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');
        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->name('partners.destroy');
        Route::post('/partners/{partner}/accounts', [PartnerAccountController::class, 'store'])->name('accounts.store');
        Route::put('/accounts/{account}', [PartnerAccountController::class, 'update'])->name('accounts.update');
        Route::post('/accounts/{account}/reset-password', [PartnerAccountController::class, 'resetPassword'])->name('accounts.reset');
        Route::delete('/accounts/{account}', [PartnerAccountController::class, 'destroy'])->name('accounts.destroy');

        // Standard WBS items (mandatory / optional) that new deliverables start from
        Route::get('/wbs-items', [WbsItemController::class, 'index'])->name('wbs-items.index');
        Route::put('/wbs-items', [WbsItemController::class, 'update'])->name('wbs-items.update');

        // Google Drive storage for evidence
        Route::get('/google-drive', [GoogleDriveController::class, 'show'])->name('drive.show');
        Route::post('/google-drive/connect', [GoogleDriveController::class, 'connect'])->name('drive.connect');
        Route::get('/google-drive/callback', [GoogleDriveController::class, 'callback'])->name('drive.callback');
        Route::delete('/google-drive', [GoogleDriveController::class, 'disconnect'])->name('drive.disconnect');
        Route::post('/google-drive/sync', [GoogleDriveController::class, 'sync'])->name('drive.sync');
    });
});
