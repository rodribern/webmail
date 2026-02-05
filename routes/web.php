<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\Admin\BrandingController;
use Illuminate\Support\Facades\Route;

// Redireciona raiz para login ou mail
Route::get('/', function () {
    if (session()->has('imap_credentials')) {
        return redirect()->route('mail.inbox');
    }
    return redirect()->route('login');
});

// Rotas de autenticação (públicas)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Logout
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rotas protegidas (requer autenticação IMAP)
Route::middleware('imap.auth')->group(function () {
    // Redireciona /mail para inbox
    Route::get('/mail', function () {
        return redirect()->route('mail.inbox');
    })->name('mail');

    // Inbox (pasta padrão)
    Route::get('/mail/inbox', [MailController::class, 'inbox'])->name('mail.inbox');

    // Pasta específica
    Route::get('/mail/folder/{folder}', [MailController::class, 'folder'])
        ->where('folder', '.*')
        ->name('mail.folder');

    // Visualizar mensagem
    Route::get('/mail/{folder}/{uid}', [MailController::class, 'show'])
        ->where('folder', '.*')
        ->where('uid', '[0-9]+')
        ->name('mail.show');

    // API routes
    Route::prefix('api/mail')->group(function () {
        Route::get('/folders', [MailController::class, 'getFolders']);
        Route::get('/messages/{folder}', [MailController::class, 'getMessages'])
            ->where('folder', '.*');
        Route::get('/message/{folder}/{uid}', [MailController::class, 'getMessage'])
            ->where('folder', '.*');
        Route::patch('/message/{folder}/{uid}/seen', [MailController::class, 'toggleSeen'])
            ->where('folder', '.*');
        Route::patch('/message/{folder}/{uid}/move', [MailController::class, 'move'])
            ->where('folder', '.*');
        Route::delete('/message/{folder}/{uid}', [MailController::class, 'delete'])
            ->where('folder', '.*');
    });

    // Rotas de Admin (requer ser admin do domínio)
    Route::middleware('domain.admin')->prefix('admin')->group(function () {
        Route::get('/branding', [BrandingController::class, 'index'])->name('admin.branding');
        Route::put('/branding', [BrandingController::class, 'update']);
        Route::post('/branding/logo', [BrandingController::class, 'uploadLogo']);
        Route::post('/branding/favicon', [BrandingController::class, 'uploadFavicon']);
        Route::delete('/branding/logo', [BrandingController::class, 'removeLogo']);
        Route::delete('/branding/favicon', [BrandingController::class, 'removeFavicon']);
    });
});
