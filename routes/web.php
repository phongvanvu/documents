<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAttachmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentRelationController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// === KHÁCH / ĐĂNG NHẬP ===
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// === ĐĂNG XUẤT ===
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// === KHU VỰC ĐÃ ĐĂNG NHẬP ===
Route::middleware('auth')->group(function () {
    // Trang chủ & Tổng quan
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Hồ sơ cá nhân & Đổi mật khẩu
    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('change-password');
    Route::post('/change-password', [AuthController::class, 'updatePassword'])->name('change-password.update');

    // === QUẢN LÝ VĂN BẢN (CORE) ===
    Route::get('/documents/trash', [DocumentController::class, 'trash'])->name('documents.trash');
    Route::post('/documents/{id}/restore', [DocumentController::class, 'restore'])->name('documents.restore');
    Route::delete('/documents/{id}/force', [DocumentController::class, 'forceDelete'])->name('documents.force-delete');
    
    Route::get('/documents/check-duplicate', [DocumentController::class, 'checkDuplicate'])->name('documents.check-duplicate');
    Route::post('/documents/{id}/favorite', [DocumentController::class, 'toggleFavorite'])->name('documents.favorite');
    Route::post('/documents/{id}/note', [DocumentController::class, 'updateNote'])->name('documents.note');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');

    Route::resource('documents', DocumentController::class);

    // === TỆP ĐÍNH KÈM & PHIÊN BẢN & LIÊN KẾT ===
    Route::post('/documents/{id}/attachments', [DocumentAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{id}', [DocumentAttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{id}/download', [DocumentAttachmentController::class, 'download'])->name('attachments.download');
    Route::get('/documents/{id}/attachments-zip', [DocumentAttachmentController::class, 'downloadAllZip'])->name('attachments.zip');

    Route::post('/documents/{id}/versions', [DocumentVersionController::class, 'store'])->name('versions.store');
    Route::delete('/versions/{id}', [DocumentVersionController::class, 'destroy'])->name('versions.destroy');

    Route::post('/documents/{id}/relations', [DocumentRelationController::class, 'store'])->name('relations.store');
    Route::delete('/relations/{id}', [DocumentRelationController::class, 'destroy'])->name('relations.destroy');
    Route::get('/relations/search', [DocumentRelationController::class, 'search'])->name('relations.search');

    // === DANH MỤC (LOẠI VB, CƠ QUAN, GỘP) ===
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/types', [CategoryController::class, 'indexTypes'])->name('types');
        Route::post('/types', [CategoryController::class, 'storeType'])->name('types.store');
        Route::put('/types/{id}', [CategoryController::class, 'updateType'])->name('types.update');
        Route::delete('/types/{id}', [CategoryController::class, 'destroyType'])->name('types.destroy');

        Route::get('/authorities', [CategoryController::class, 'indexAuthorities'])->name('authorities');
        Route::post('/authorities', [CategoryController::class, 'storeAuthority'])->name('authorities.store');
        Route::put('/authorities/{id}', [CategoryController::class, 'updateAuthority'])->name('authorities.update');
        Route::delete('/authorities/{id}', [CategoryController::class, 'destroyAuthority'])->name('authorities.destroy');

        Route::get('/merge', [CategoryController::class, 'showMerge'])->name('merge');
        Route::post('/merge', [CategoryController::class, 'processMerge'])->name('merge.post');
    });

    // === BÁO CÁO, THỐNG KÊ & TIỆN ÍCH ===
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/print-register', [ReportController::class, 'printRegister'])->name('print-register');
        Route::get('/export', [ReportController::class, 'export'])->name('export');
        Route::get('/import', [ReportController::class, 'showImport'])->name('import');
        Route::post('/import', [ReportController::class, 'processImport'])->name('import.post');
        Route::get('/mlns', [ReportController::class, 'mlns'])->name('mlns');
    });

    // === QUẢN LÝ NGƯỜI DÙNG & PHÂN QUYỀN (ADMIN / EDITOR) ===
    Route::middleware('role:admin,editor')->group(function () {
        Route::resource('users', UserController::class);
        Route::get('/permissions', [UserController::class, 'permissions'])->name('users.permissions');
        Route::post('/permissions/toggle', [UserController::class, 'togglePermission'])->name('users.permissions.toggle');
        Route::get('/sessions', [UserController::class, 'sessions'])->name('users.sessions');
        Route::delete('/sessions/{id}', [UserController::class, 'revokeSession'])->name('users.sessions.revoke');
    });

    // === QUẢN TRỊ HỆ THỐNG (CHỈ ADMIN) ===
    Route::middleware('role:admin')->prefix('system')->name('system.')->group(function () {
        Route::get('/settings', [SystemController::class, 'settings'])->name('settings');
        Route::post('/settings', [SystemController::class, 'updateSettings'])->name('settings.update');

        Route::get('/logs', [SystemController::class, 'logs'])->name('logs');
        Route::post('/logs/clear', [SystemController::class, 'clearLogs'])->name('logs.clear');

        Route::get('/backups', [SystemController::class, 'backups'])->name('backups');
        Route::post('/backups', [SystemController::class, 'createBackup'])->name('backups.create');
        Route::get('/backups/{filename}', [SystemController::class, 'downloadBackup'])->name('backups.download');
        Route::delete('/backups/{filename}', [SystemController::class, 'deleteBackup'])->name('backups.delete');

        Route::post('/optimize-db', [SystemController::class, 'optimizeDb'])->name('optimize-db');
        Route::get('/info', [SystemController::class, 'systemInfo'])->name('info');
    });
});
