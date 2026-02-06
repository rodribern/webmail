<?php

namespace App\Http\Controllers;

use App\Services\ImapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class MailController extends Controller
{
    public function __construct(
        private ImapService $imapService
    ) {}

    /**
     * Exibe a caixa de entrada
     */
    public function inbox(Request $request): Response
    {
        return $this->showFolder($request, 'INBOX');
    }

    /**
     * Exibe uma pasta específica
     */
    public function folder(Request $request, string $folder): Response
    {
        return $this->showFolder($request, $folder);
    }

    /**
     * Renderiza a view de uma pasta
     */
    private function showFolder(Request $request, string $folderPath): Response
    {
        $page = (int) $request->get('page', 1);

        if (!$this->imapService->connect()) {
            return Inertia::render('Mail/Inbox', [
                'user' => session('user'),
                'branding' => session('branding'),
                'folders' => [],
                'messages' => [],
                'currentFolder' => $folderPath,
                'error' => 'Não foi possível conectar ao servidor de e-mail.',
            ]);
        }

        $folders = $this->imapService->getFolders();
        $messagesData = $this->imapService->getMessages($folderPath, $page);

        $this->imapService->disconnect();

        return Inertia::render('Mail/Inbox', [
            'user' => session('user'),
            'branding' => session('branding'),
            'folders' => $folders,
            'messages' => $messagesData['messages'],
            'pagination' => [
                'total' => $messagesData['total'],
                'page' => $messagesData['page'],
                'per_page' => $messagesData['per_page'],
                'total_pages' => $messagesData['total_pages'] ?? 1,
            ],
            'currentFolder' => $folderPath,
        ]);
    }

    /**
     * API: Lista pastas
     */
    public function getFolders(): JsonResponse
    {
        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $folders = $this->imapService->getFolders();
        $this->imapService->disconnect();

        return response()->json($folders);
    }

    /**
     * API: Lista mensagens de uma pasta
     */
    public function getMessages(Request $request, string $folder): JsonResponse
    {
        $page = (int) $request->get('page', 1);

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $data = $this->imapService->getMessages($folder, $page);
        $this->imapService->disconnect();

        return response()->json($data);
    }

    /**
     * Exibe uma mensagem específica
     */
    public function show(Request $request, string $folder, int $uid): Response
    {
        if (!$this->imapService->connect()) {
            return Inertia::render('Mail/Message', [
                'user' => session('user'),
                'branding' => session('branding'),
                'message' => null,
                'currentFolder' => $folder,
                'error' => 'Não foi possível conectar ao servidor de e-mail.',
            ]);
        }

        $folders = $this->imapService->getFolders();
        $message = $this->imapService->getMessage($folder, $uid);

        $this->imapService->disconnect();

        if (!$message) {
            return Inertia::render('Mail/Message', [
                'user' => session('user'),
                'branding' => session('branding'),
                'folders' => $folders,
                'message' => null,
                'currentFolder' => $folder,
                'error' => 'Mensagem não encontrada.',
            ]);
        }

        // Sanitiza o HTML da mensagem
        if ($message['body_html']) {
            $message['body_html'] = $this->sanitizeHtml($message['body_html']);
        }

        return Inertia::render('Mail/Message', [
            'user' => session('user'),
            'branding' => session('branding'),
            'folders' => $folders,
            'message' => $message,
            'currentFolder' => $folder,
        ]);
    }

    /**
     * API: Busca mensagem específica
     */
    public function getMessage(string $folder, int $uid): JsonResponse
    {
        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $message = $this->imapService->getMessage($folder, $uid);
        $this->imapService->disconnect();

        if (!$message) {
            return response()->json(['error' => 'Mensagem não encontrada'], 404);
        }

        // Sanitiza o HTML
        if ($message['body_html']) {
            $message['body_html'] = $this->sanitizeHtml($message['body_html']);
        }

        return response()->json($message);
    }

    /**
     * Sanitiza HTML de e-mail
     *
     * Nota: O HTML é renderizado em um iframe com sandbox no frontend,
     * o que bloqueia a execução de scripts. Fazemos apenas uma limpeza
     * básica removendo tags de script explícitas.
     */
    private function sanitizeHtml(string $html): string
    {
        // Remove tags de script e event handlers como camada extra de segurança
        // O iframe sandbox já bloqueia scripts, mas isso é uma precaução adicional
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]*/i', '', $html);

        // Remove javascript: URLs
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $html);

        return $html;
    }

    /**
     * API: Marca mensagem como lida/não lida
     */
    public function toggleSeen(Request $request, string $folder, int $uid): JsonResponse
    {
        \Log::debug('toggleSeen called', ['folder' => $folder, 'uid' => $uid, 'seen' => $request->input('seen')]);

        $seen = $request->boolean('seen', true);

        if (!$this->imapService->connect()) {
            \Log::error('toggleSeen: IMAP connection failed');
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $result = $this->imapService->toggleSeen($folder, $uid, $seen);
        \Log::debug('toggleSeen result', ['result' => $result]);
        $this->imapService->disconnect();

        if (!$result) {
            return response()->json(['error' => 'Falha ao alterar flag'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Move mensagem para outra pasta
     */
    public function move(Request $request, string $folder, int $uid): JsonResponse
    {
        $request->validate([
            'target_folder' => 'required|string',
        ]);

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $result = $this->imapService->moveMessage($folder, $uid, $request->target_folder);
        $this->imapService->disconnect();

        return response()->json(['success' => $result]);
    }

    /**
     * API: Exclui mensagem
     */
    public function delete(string $folder, int $uid): JsonResponse
    {
        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $result = $this->imapService->deleteMessage($folder, $uid);
        $this->imapService->disconnect();

        return response()->json(['success' => $result]);
    }

    /**
     * API: Marca múltiplas mensagens como lida/não lida
     */
    public function batchToggleSeen(Request $request, string $folder): JsonResponse
    {
        \Log::info('batchToggleSeen called', [
            'folder' => $folder,
            'input' => $request->all(),
        ]);

        $request->validate([
            'uids' => 'required|array|min:1',
            'uids.*' => 'integer',
        ]);

        $seen = $request->boolean('seen');

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão IMAP.'], 500);
        }

        try {
            $count = $this->imapService->batchToggleSeen($folder, $request->input('uids'), $seen);
            $this->imapService->disconnect();

            return response()->json(['success' => true, 'count' => $count]);
        } catch (\Exception $e) {
            $this->imapService->disconnect();
            \Log::error('batchToggleSeen exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao alterar flags. Tente novamente.'], 500);
        }
    }

    /**
     * API: Exclui múltiplas mensagens
     */
    public function batchDelete(Request $request, string $folder): JsonResponse
    {
        $request->validate([
            'uids' => 'required|array|min:1',
            'uids.*' => 'integer',
        ]);

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $count = $this->imapService->batchDelete($folder, $request->input('uids'));
        $this->imapService->disconnect();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * API: Move múltiplas mensagens para outra pasta
     */
    public function batchMove(Request $request, string $folder): JsonResponse
    {
        $request->validate([
            'uids' => 'required|array|min:1',
            'uids.*' => 'integer',
            'target' => 'required|string|max:255',
        ]);

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $count = $this->imapService->batchMove($folder, $request->input('uids'), $request->input('target'));
        $this->imapService->disconnect();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * API: Download de anexo
     */
    public function downloadAttachment(string $folder, int $uid, int $index): StreamedResponse|JsonResponse
    {
        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $attachment = $this->imapService->getAttachment($folder, $uid, $index);
        $this->imapService->disconnect();

        if (!$attachment) {
            return response()->json(['error' => 'Anexo não encontrado'], 404);
        }

        // Sanitiza o nome do arquivo para o header Content-Disposition
        $filename = preg_replace('/[^\w\-. ]/', '_', $attachment['name']);
        $mime = $attachment['mime'] ?: 'application/octet-stream';

        // Tipos que o navegador pode exibir inline (SVG removido — pode conter JavaScript)
        $inlineTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $disposition = in_array($mime, $inlineTypes) ? 'inline' : 'attachment';

        return response()->stream(function () use ($attachment) {
            echo $attachment['content'];
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Content-Length' => strlen($attachment['content']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ]);
    }

    /**
     * API: Busca mensagens por termo
     */
    public function searchMessages(Request $request, string $folder): JsonResponse
    {
        $query = $request->get('q', '');
        $page = (int) $request->get('page', 1);

        if (empty(trim($query))) {
            return response()->json(['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => 50, 'total_pages' => 0]);
        }

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $data = $this->imapService->searchMessages($folder, $query, $page);
        $this->imapService->disconnect();

        return response()->json($data);
    }

    /**
     * API: Cria pasta
     */
    public function createFolder(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[\w\s\-\.]+$/u'],
        ]);

        $name = $request->input('name');

        if (ImapService::isSystemFolder($name)) {
            return response()->json(['error' => 'Não é possível criar pasta com nome reservado.'], 422);
        }

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $result = $this->imapService->createFolder($name);
        $this->imapService->disconnect();

        if (!$result) {
            return response()->json(['error' => 'Falha ao criar pasta.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Renomeia pasta
     */
    public function renameFolder(Request $request, string $folder): JsonResponse
    {
        $request->validate([
            'new_name' => ['required', 'string', 'max:50', 'regex:/^[\w\s\-\.]+$/u'],
        ]);

        if (ImapService::isSystemFolder($folder)) {
            return response()->json(['error' => 'Não é possível renomear pastas do sistema.'], 422);
        }

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $result = $this->imapService->renameFolder($folder, $request->input('new_name'));
        $this->imapService->disconnect();

        if (!$result) {
            return response()->json(['error' => 'Falha ao renomear pasta.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Exclui pasta
     */
    public function deleteFolder(string $folder): JsonResponse
    {
        if (ImapService::isSystemFolder($folder)) {
            return response()->json(['error' => 'Não é possível excluir pastas do sistema.'], 422);
        }

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $result = $this->imapService->deleteFolder($folder);
        $this->imapService->disconnect();

        if (!$result) {
            return response()->json(['error' => 'Falha ao excluir pasta.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Sugere contatos baseado no histórico
     */
    public function suggestContacts(): JsonResponse
    {
        if (!$this->imapService->connect()) {
            return response()->json([]);
        }

        $contacts = $this->imapService->harvestContacts();
        $this->imapService->disconnect();

        return response()->json($contacts);
    }
}
