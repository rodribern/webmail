<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\ComposeController;
use App\Http\Controllers\SignatureController;
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

    // Compor e-mail
    Route::get('/mail/compose', [ComposeController::class, 'compose'])->name('mail.compose');

    // Pasta específica
    Route::get('/mail/folder/{folder}', [MailController::class, 'folder'])
        ->where('folder', '.*')
        ->name('mail.folder');

    // Visualizar mensagem
    Route::get('/mail/{folder}/{uid}', [MailController::class, 'show'])
        ->where('folder', '.*')
        ->where('uid', '[0-9]+')
        ->name('mail.show');

    // Assinatura
    Route::get('/settings/signature', [SignatureController::class, 'index'])->name('settings.signature');

    // API routes
    Route::prefix('api/mail')->group(function () {
        // Pastas
        Route::get('/folders', [MailController::class, 'getFolders']);
        Route::post('/folders', [MailController::class, 'createFolder']);
        Route::patch('/folders/{folder}', [MailController::class, 'renameFolder'])
            ->where('folder', '.*');
        Route::delete('/folders/{folder}', [MailController::class, 'deleteFolder'])
            ->where('folder', '.*');

        // Mensagens — batch operations e search ANTES de getMessages (wildcard)
        Route::post('/messages/{folder}/batch-seen', [MailController::class, 'batchToggleSeen'])
            ->where('folder', '[^/]+');
        Route::post('/messages/{folder}/batch-delete', [MailController::class, 'batchDelete'])
            ->where('folder', '[^/]+');
        Route::post('/messages/{folder}/batch-move', [MailController::class, 'batchMove'])
            ->where('folder', '[^/]+');
        Route::get('/messages/{folder}/search', [MailController::class, 'searchMessages'])
            ->where('folder', '[^/]+');
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

        // Anexos
        Route::get('/attachment/{folder}/{uid}/{index}', [MailController::class, 'downloadAttachment'])
            ->where(['folder' => '.*', 'uid' => '[0-9]+', 'index' => '[0-9]+']);

        // Composição
        Route::post('/send', [ComposeController::class, 'send']);
        Route::post('/drafts', [ComposeController::class, 'saveDraft']);
        Route::post('/attachments', [ComposeController::class, 'uploadAttachment']);
        Route::delete('/attachments/{id}', [ComposeController::class, 'removeAttachment']);

        // Contatos
        Route::get('/contacts/suggest', [MailController::class, 'suggestContacts']);
    });

    // API settings
    Route::put('/api/settings/signature', [SignatureController::class, 'update']);

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
