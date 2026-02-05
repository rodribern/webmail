<?php

namespace App\Http\Controllers;

use App\Services\ImapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
        $seen = $request->boolean('seen', true);

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão'], 500);
        }

        $result = $this->imapService->toggleSeen($folder, $uid, $seen);
        $this->imapService->disconnect();

        return response()->json(['success' => $result]);
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
}
