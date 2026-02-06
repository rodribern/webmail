<?php

namespace App\Http\Controllers;

use App\Services\ImapService;
use App\Services\SmtpService;
use App\Models\UserSignature;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ComposeController extends Controller
{
    public function __construct(
        private ImapService $imapService,
        private SmtpService $smtpService,
    ) {}

    /**
     * Exibe a página de composição
     */
    public function compose(Request $request): Response
    {
        $mode = $request->get('mode', 'new');
        $folder = $request->get('folder');
        $uid = $request->get('uid');
        $original = null;

        // Se é reply/forward, busca a mensagem original
        if (in_array($mode, ['reply', 'reply_all', 'forward']) && $folder && $uid) {
            if ($this->imapService->connect()) {
                $original = $this->imapService->getMessage($folder, (int) $uid);
                $this->imapService->disconnect();
            }
        }

        // Busca pastas para a sidebar
        $folders = [];
        if ($this->imapService->connect()) {
            $folders = $this->imapService->getFolders();
            $this->imapService->disconnect();
        }

        // Busca assinatura do usuário
        $userEmail = session('user.email');
        $signature = '';
        if ($userEmail) {
            $sig = UserSignature::findByEmail($userEmail);
            $signature = $sig?->signature_html ?? '';
        }

        return Inertia::render('Mail/Compose', [
            'user' => session('user'),
            'branding' => session('branding'),
            'folders' => $folders,
            'mode' => $mode,
            'original' => $original,
            'signature' => $signature,
        ]);
    }

    /**
     * Envia um e-mail
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|array|min:1|max:50',
            'to.*.email' => 'required|email',
            'cc' => 'nullable|array|max:50',
            'cc.*.email' => 'nullable|email',
            'bcc' => 'nullable|array|max:50',
            'bcc.*.email' => 'nullable|email',
            'subject' => 'nullable|string|max:998',
            'body_html' => 'nullable|string|max:5000000',
            'attachments' => 'nullable|array',
            'attachments.*.id' => 'required|string',
            'attachments.*.name' => 'required|string|max:255',
            'in_reply_to' => 'nullable|string',
            'references' => 'nullable|string',
            'draft_uid' => 'nullable|integer',
            'draft_folder' => 'nullable|string',
        ]);

        // Coleta os paths dos anexos temporários
        $attachmentPaths = $this->resolveAttachments($request->input('attachments') ?? []);

        $result = $this->smtpService->send(
            to: $request->input('to', []),
            subject: $request->input('subject') ?? '',
            htmlBody: $request->input('body_html') ?? '',
            cc: $request->input('cc', []),
            bcc: $request->input('bcc', []),
            attachmentPaths: $attachmentPaths,
            inReplyTo: $request->input('in_reply_to'),
            references: $request->input('references'),
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        // Salva cópia na pasta Sent
        if (isset($result['raw_message'])) {
            if ($this->imapService->connect()) {
                $this->imapService->appendToFolder('Sent', $result['raw_message'], ['\\Seen']);
                $this->imapService->disconnect();
            }
        }

        // Se estava editando um rascunho, exclui o original
        $draftUid = $request->input('draft_uid');
        $draftFolder = $request->input('draft_folder', 'Drafts');
        if ($draftUid) {
            if ($this->imapService->connect()) {
                $this->imapService->deleteMessage($draftFolder, (int) $draftUid);
                $this->imapService->disconnect();
            }
        }

        // Limpa anexos temporários
        $this->cleanupAttachments();

        return response()->json(['success' => true, 'message' => 'E-mail enviado com sucesso.']);
    }

    /**
     * Salva um rascunho na pasta Drafts via IMAP
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'nullable|array',
            'to.*.email' => 'nullable|email',
            'cc' => 'nullable|array',
            'bcc' => 'nullable|array',
            'subject' => 'nullable|string|max:998',
            'body_html' => 'nullable|string|max:5000000',
            'draft_uid' => 'nullable|integer',
        ]);

        $userEmail = session('user.email');
        if (!$userEmail) {
            return response()->json(['error' => 'Sessão expirada.'], 401);
        }

        // Monta a mensagem RFC822 para o rascunho
        $to = collect($request->input('to', []))->pluck('email')->filter()->implode(', ');
        $cc = collect($request->input('cc', []))->pluck('email')->filter()->implode(', ');
        $subject = $request->input('subject', '');
        $bodyHtml = $request->input('body_html', '');
        $date = now()->format('r');

        $rawMessage = "From: {$userEmail}\r\n";
        if ($to) {
            $rawMessage .= "To: {$to}\r\n";
        }
        if ($cc) {
            $rawMessage .= "Cc: {$cc}\r\n";
        }
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "Date: {$date}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: 8bit\r\n";
        $rawMessage .= "\r\n";
        $rawMessage .= $bodyHtml;

        if (!$this->imapService->connect()) {
            return response()->json(['error' => 'Falha na conexão IMAP.'], 500);
        }

        // Se está atualizando um rascunho existente, exclui o anterior
        $draftUid = $request->input('draft_uid');
        if ($draftUid) {
            $this->imapService->deleteMessage('Drafts', (int) $draftUid);
        }

        $result = $this->imapService->appendToFolder('Drafts', $rawMessage, ['\\Draft']);
        $this->imapService->disconnect();

        if (!$result) {
            return response()->json(['error' => 'Falha ao salvar rascunho.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Rascunho salvo.']);
    }

    /**
     * Upload de anexo temporário
     */
    public function uploadAttachment(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB
                function ($attribute, $value, $fail) {
                    $dangerous = [
                        'application/x-httpd-php', 'text/x-php', 'application/x-php',
                        'application/x-executable', 'application/x-sharedlib',
                    ];
                    if (in_array($value->getMimeType(), $dangerous)) {
                        $fail('Tipo de arquivo não permitido.');
                    }
                },
            ],
        ]);

        $file = $request->file('file');
        $sessionId = session()->getId();
        $id = Str::uuid()->toString();
        $ext = $file->getClientOriginalExtension();
        $filename = $id . ($ext ? ".{$ext}" : '');

        $dir = "temp_attachments/{$sessionId}";
        $path = $file->storeAs($dir, $filename, 'local');

        return response()->json([
            'id' => $id,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'size_human' => $this->humanFileSize($file->getSize()),
            'mime' => $file->getMimeType(),
        ]);
    }

    /**
     * Remove um anexo temporário
     */
    public function removeAttachment(Request $request, string $id): JsonResponse
    {
        $sessionId = session()->getId();
        $dir = storage_path("app/private/temp_attachments/{$sessionId}");

        // Busca o arquivo pelo UUID
        if (is_dir($dir)) {
            $files = glob("{$dir}/{$id}*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Resolve attachment IDs para paths reais
     */
    private function resolveAttachments(array $attachments): array
    {
        $sessionId = session()->getId();
        $dir = storage_path("app/private/temp_attachments/{$sessionId}");
        $result = [];

        foreach ($attachments as $attachment) {
            $id = basename($attachment['id'] ?? '');
            $originalName = $attachment['name'] ?? $id;
            if (!$id) continue;

            $files = glob("{$dir}/{$id}*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    $result[] = [
                        'path' => $file,
                        'name' => $originalName,
                        'mime' => mime_content_type($file) ?: 'application/octet-stream',
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Limpa todos os anexos temporários da sessão atual
     */
    private function cleanupAttachments(): void
    {
        $sessionId = session()->getId();
        $dir = storage_path("app/private/temp_attachments/{$sessionId}");

        if (is_dir($dir)) {
            $files = glob("{$dir}/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Formata tamanho de arquivo
     */
    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
