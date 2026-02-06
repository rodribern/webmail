<?php

namespace App\Services;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Illuminate\Support\Collection;

class ImapService
{
    private ?Client $client = null;
    private string $host;
    private int $port;
    private string $encryption;
    private bool $validateCert;

    public function __construct()
    {
        $this->host = config('imap.accounts.default.host', '127.0.0.1');
        $this->port = (int) config('imap.accounts.default.port', 993);
        $this->encryption = config('imap.accounts.default.encryption', 'ssl');
        $this->validateCert = config('imap.accounts.default.validate_cert', true);
    }

    /**
     * Conecta ao servidor IMAP usando credenciais da sessão
     */
    public function connect(): bool
    {
        $credentials = session('imap_credentials');

        if (!$credentials) {
            return false;
        }

        try {
            $clientManager = new ClientManager();

            $this->client = $clientManager->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption,
                'validate_cert' => $this->validateCert,
                'username' => $credentials['email'],
                'password' => decrypt($credentials['password']),
                'protocol' => 'imap',
                'authentication' => null,
            ]);

            $this->client->connect();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Desconecta do servidor IMAP
     */
    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
    }

    /**
     * Lista todas as pastas do usuário
     */
    public function getFolders(): array
    {
        if (!$this->client) {
            return [];
        }

        try {
            $folders = $this->client->getFolders(false);
            $result = [];

            foreach ($folders as $folder) {
                $result[] = $this->formatFolder($folder);
            }

            // Ordena as pastas: sistema primeiro, depois alfabética
            usort($result, function ($a, $b) {
                $orderA = $this->getFolderSortOrder($a['name']);
                $orderB = $this->getFolderSortOrder($b['name']);
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }
                return strcasecmp($a['name'], $b['name']);
            });

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Retorna a ordem de classificação de uma pasta
     */
    private function getFolderSortOrder(string $name): int
    {
        $nameLower = strtolower($name);

        // Ordem padrão das pastas
        $order = [
            'inbox' => 1,
            'sent' => 2,
            'drafts' => 3,
            'trash' => 4,
            'spam' => 5,
            'junk' => 5,
        ];

        foreach ($order as $key => $value) {
            if (str_contains($nameLower, $key)) {
                return $value;
            }
        }

        // Outras pastas no final, em ordem alfabética
        return 100;
    }

    /**
     * Formata uma pasta para array
     */
    private function formatFolder(Folder $folder): array
    {
        $status = $folder->examine();

        return [
            'name' => $folder->name,
            'full_name' => $folder->full_name,
            'path' => $folder->path,
            'total' => $status['exists'] ?? 0,
            'unseen' => $status['recent'] ?? 0,
            'icon' => $this->getFolderIcon($folder->name),
        ];
    }

    /**
     * Retorna ícone baseado no nome da pasta
     */
    private function getFolderIcon(string $name): string
    {
        $name = strtolower($name);

        return match (true) {
            str_contains($name, 'inbox') => 'inbox',
            str_contains($name, 'sent') => 'paper-airplane',
            str_contains($name, 'draft') => 'pencil',
            str_contains($name, 'trash') || str_contains($name, 'lixo') => 'trash',
            str_contains($name, 'spam') || str_contains($name, 'junk') => 'exclamation-circle',
            str_contains($name, 'archive') => 'archive',
            default => 'folder',
        };
    }

    /**
     * Lista mensagens de uma pasta
     */
    public function getMessages(string $folderPath, int $page = 1, int $perPage = 50): array
    {
        if (!$this->client) {
            \Log::error('IMAP: Client not connected');
            return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        try {
            $folder = $this->client->getFolder($folderPath);

            if (!$folder) {
                \Log::error('IMAP: Folder not found: ' . $folderPath);
                return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
            }

            $status = $folder->examine();
            $total = $status['exists'] ?? 0;

            \Log::info('IMAP: Folder ' . $folderPath . ' has ' . $total . ' messages');

            if ($total === 0) {
                return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
            }

            // Busca as mensagens da pasta
            $messages = $folder->messages()
                ->all()
                ->setFetchOrder('desc')
                ->limit($perPage)
                ->get();

            \Log::info('IMAP: Fetched ' . count($messages) . ' messages');

            $result = [];
            foreach ($messages as $message) {
                $result[] = $this->formatMessagePreview($message);
            }

            // Ordena por data decrescente (mais recentes primeiro)
            usort($result, function ($a, $b) {
                return strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01');
            });

            return [
                'messages' => $result,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ];
        } catch (\Exception $e) {
            \Log::error('IMAP getMessages error: ' . $e->getMessage());
            return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'error' => $e->getMessage()];
        }
    }

    /**
     * Formata preview de mensagem para listagem
     */
    private function formatMessagePreview(Message $message): array
    {
        $from = $message->getFrom();
        $fromAddress = $from->first();

        // Extrai preview do texto (primeiros 150 caracteres)
        $textBody = $message->getTextBody() ?? '';
        $preview = '';
        if ($textBody) {
            $preview = $this->decodeMime($textBody);
            $preview = preg_replace('/\s+/', ' ', trim($preview));
            $preview = mb_substr($preview, 0, 150);
        }

        // Obtém a data como Carbon
        $date = $message->getDate()?->toDate();

        // Decodifica o assunto
        $subject = $message->getSubject();
        $subjectText = $subject ? $this->decodeMime($subject->toString()) : '(Sem assunto)';

        return [
            'uid' => $message->getUid(),
            'message_id' => $message->getMessageId()?->first(),
            'subject' => $subjectText,
            'from' => [
                'name' => $fromAddress?->personal ? $this->decodeMime(trim($fromAddress->personal, '"')) : ($fromAddress?->mail ?? 'Desconhecido'),
                'email' => $fromAddress?->mail ?? '',
            ],
            'date' => $date?->format('Y-m-d H:i:s'),
            'date_human' => $this->humanDate($date),
            'seen' => $message->hasFlag('Seen'),
            'flagged' => $message->hasFlag('Flagged'),
            'has_attachments' => $this->hasRealAttachments($message),
            'preview' => $preview,
        ];
    }

    /**
     * Decodifica strings MIME encoded
     */
    private function decodeMime(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Decodifica MIME encoded-word
        $decoded = iconv_mime_decode($text, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        if ($decoded === false) {
            return $text;
        }

        return $decoded;
    }

    /**
     * Formata data para exibição humana
     */
    private function humanDate(?\Carbon\Carbon $date): string
    {
        if (!$date) {
            return '';
        }

        $now = now();

        if ($date->isToday()) {
            return $date->format('H:i');
        }

        if ($date->isYesterday()) {
            return 'Ontem';
        }

        if ($date->year === $now->year) {
            return $date->format('d/m');
        }

        return $date->format('d/m/Y');
    }

    /**
     * Busca uma mensagem específica
     */
    public function getMessage(string $folderPath, int $uid): ?array
    {
        if (!$this->client) {
            return null;
        }

        try {
            $folder = $this->client->getFolder($folderPath);

            if (!$folder) {
                return null;
            }

            $message = $folder->messages()->getMessageByUid($uid);

            if (!$message) {
                return null;
            }

            // Marca como lida
            $message->setFlag('Seen');

            return $this->formatFullMessage($message);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formata mensagem completa para visualização
     */
    private function formatFullMessage(Message $message): array
    {
        $from = $message->getFrom();
        $to = $message->getTo();
        $cc = $message->getCc();
        $replyTo = $message->getReplyTo();
        $date = $message->getDate()?->toDate();

        // Decodifica o assunto
        $subject = $message->getSubject();
        $subjectText = $subject ? $this->decodeMime($subject->toString()) : '(Sem assunto)';

        // Processa HTML e imagens inline
        $bodyHtml = $message->getHTMLBody();
        $bodyText = $message->getTextBody();
        $inlineImages = $this->getInlineImages($message);

        // Substitui cid: por data URLs
        if ($bodyHtml && !empty($inlineImages)) {
            foreach ($inlineImages as $cid => $dataUrl) {
                $bodyHtml = str_replace("cid:$cid", $dataUrl, $bodyHtml);
            }
        }

        return [
            'uid' => $message->getUid(),
            'message_id' => $message->getMessageId()?->first(),
            'subject' => $subjectText,
            'from' => $this->formatAddresses($from),
            'to' => $this->formatAddresses($to),
            'cc' => $this->formatAddresses($cc),
            'reply_to' => $this->formatAddresses($replyTo),
            'date' => $date?->format('Y-m-d H:i:s'),
            'date_formatted' => $date?->format('d/m/Y \à\s H:i'),
            'seen' => $message->hasFlag('Seen'),
            'flagged' => $message->hasFlag('Flagged'),
            'has_attachments' => $this->hasRealAttachments($message),
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'attachments' => $this->formatAttachments($message, true),
        ];
    }

    /**
     * Extrai imagens inline (com Content-ID) como data URLs
     */
    private function getInlineImages(Message $message): array
    {
        $result = [];

        try {
            $attachments = $message->getAttachments();

            foreach ($attachments as $attachment) {
                $contentId = $attachment->getContentId();

                if ($contentId) {
                    // Remove < e > do Content-ID se presente
                    $cid = trim($contentId, '<>');
                    $mime = $attachment->getMimeType() ?? 'image/png';
                    $content = $attachment->getContent();

                    if ($content) {
                        $base64 = base64_encode($content);
                        $result[$cid] = "data:{$mime};base64,{$base64}";
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error getting inline images: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Verifica se tem anexos reais (não inline)
     */
    private function hasRealAttachments(Message $message): bool
    {
        try {
            $attachments = $message->getAttachments();

            foreach ($attachments as $attachment) {
                $contentId = $attachment->getContentId();
                $disposition = $attachment->getDisposition();

                // Anexo real = sem Content-ID ou disposition = attachment
                if (!$contentId || $disposition === 'attachment') {
                    return true;
                }
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * Formata lista de endereços
     */
    private function formatAddresses($addresses): array
    {
        if (!$addresses || ($addresses instanceof \Webklex\PHPIMAP\Attribute && $addresses->count() === 0)) {
            return [];
        }

        $result = [];
        // Attribute não implementa Traversable, então usamos all() para iterar
        $items = ($addresses instanceof \Webklex\PHPIMAP\Attribute) ? $addresses->all() : (is_array($addresses) ? $addresses : []);

        foreach ($items as $address) {
            $name = $address->personal ?? $address->mail ?? '';
            if ($name) {
                $name = trim($name, '"');
            }
            $result[] = [
                'name' => $name ? $this->decodeMime($name) : '',
                'email' => $address->mail ?? '',
            ];
        }

        return $result;
    }

    /**
     * Formata lista de anexos (filtra inline se solicitado)
     */
    private function formatAttachments(Message $message, bool $excludeInline = false): array
    {
        if (!$message->hasAttachments()) {
            return [];
        }

        $attachments = $message->getAttachments();
        $result = [];

        foreach ($attachments as $index => $attachment) {
            // Pula anexos inline se solicitado
            if ($excludeInline) {
                $contentId = $attachment->getContentId();
                $disposition = $attachment->getDisposition();

                // É inline se tem Content-ID e não é explicitamente attachment
                if ($contentId && $disposition !== 'attachment') {
                    continue;
                }
            }

            $result[] = [
                'index' => $index,
                'name' => $attachment->getName() ?? 'anexo',
                'mime' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
                'size_human' => $this->humanFileSize($attachment->getSize()),
            ];
        }

        return $result;
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

    /**
     * Marca mensagem como lida/não lida
     */
    public function toggleSeen(string $folderPath, int $uid, bool $seen): bool
    {
        if (!$this->client) {
            \Log::error('toggleSeen: no client');
            return false;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            if (!$folder) {
                \Log::error('toggleSeen: folder not found', ['folder' => $folderPath]);
                return false;
            }

            $message = $folder->messages()->getMessageByUid($uid);

            if (!$message) {
                \Log::error('toggleSeen: message not found', ['uid' => $uid]);
                return false;
            }

            if ($seen) {
                $message->setFlag('Seen');
            } else {
                $message->unsetFlag('Seen');
            }

            \Log::debug('toggleSeen: flag set', ['seen' => $seen, 'uid' => $uid]);
            return true;
        } catch (\Exception $e) {
            \Log::error('toggleSeen error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Move mensagem para outra pasta
     */
    public function moveMessage(string $folderPath, int $uid, string $targetFolder): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            $message = $folder->messages()->getMessageByUid($uid);

            if (!$message) {
                return false;
            }

            $message->move($targetFolder);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Exclui mensagem (move para lixeira ou expunge)
     */
    public function deleteMessage(string $folderPath, int $uid): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            $message = $folder->messages()->getMessageByUid($uid);

            if (!$message) {
                return false;
            }

            // Tenta mover para Trash primeiro
            $trashFolders = ['Trash', 'INBOX.Trash', 'Lixeira', 'INBOX.Lixeira'];
            $moved = false;

            foreach ($trashFolders as $trash) {
                try {
                    $trashFolder = $this->client->getFolder($trash);
                    if ($trashFolder) {
                        $message->move($trash);
                        $moved = true;
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Se não conseguiu mover, marca como deleted
            if (!$moved) {
                $message->delete();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Marca múltiplas mensagens como lida/não lida
     */
    public function batchToggleSeen(string $folderPath, array $uids, bool $seen): int
    {
        if (!$this->client) {
            return 0;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            if (!$folder) {
                return 0;
            }

            $count = 0;
            foreach ($uids as $uid) {
                try {
                    $message = $folder->messages()->getMessageByUid($uid);
                    if (!$message) continue;

                    if ($seen) {
                        $message->setFlag('Seen');
                    } else {
                        $message->unsetFlag('Seen');
                    }
                    $count++;
                } catch (\Exception $e) {
                    \Log::warning('batchToggleSeen: failed for uid', ['uid' => $uid, 'error' => $e->getMessage()]);
                }
            }

            return $count;
        } catch (\Exception $e) {
            \Log::error('batchToggleSeen error', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Exclui múltiplas mensagens (move para lixeira ou expunge)
     */
    public function batchDelete(string $folderPath, array $uids): int
    {
        if (!$this->client) {
            return 0;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            if (!$folder) {
                return 0;
            }

            // Descobre pasta Trash uma vez
            $trashFolder = null;
            $trashFolders = ['Trash', 'INBOX.Trash', 'Lixeira', 'INBOX.Lixeira'];
            foreach ($trashFolders as $trash) {
                try {
                    $trashFolder = $this->client->getFolder($trash);
                    if ($trashFolder) break;
                } catch (\Exception $e) {
                    $trashFolder = null;
                }
            }

            $count = 0;
            foreach ($uids as $uid) {
                try {
                    $message = $folder->messages()->getMessageByUid($uid);
                    if (!$message) continue;

                    if ($trashFolder && $folderPath !== $trashFolder->path) {
                        $message->move($trashFolder->path);
                    } else {
                        $message->delete();
                    }
                    $count++;
                } catch (\Exception $e) {
                    \Log::warning('batchDelete: failed for uid', ['uid' => $uid, 'error' => $e->getMessage()]);
                }
            }

            return $count;
        } catch (\Exception $e) {
            \Log::error('batchDelete error', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Move múltiplas mensagens para outra pasta
     */
    public function batchMove(string $folderPath, array $uids, string $targetFolder): int
    {
        if (!$this->client) {
            return 0;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            if (!$folder) {
                return 0;
            }

            $count = 0;
            foreach ($uids as $uid) {
                try {
                    $message = $folder->messages()->getMessageByUid($uid);
                    if (!$message) continue;

                    $message->move($targetFolder);
                    $count++;
                } catch (\Exception $e) {
                    \Log::warning('batchMove: failed for uid', ['uid' => $uid, 'error' => $e->getMessage()]);
                }
            }

            return $count;
        } catch (\Exception $e) {
            \Log::error('batchMove error', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Busca um anexo específico de uma mensagem
     *
     * @return array{name: string, mime: string, content: string}|null
     */
    public function getAttachment(string $folderPath, int $uid, int $index): ?array
    {
        if (!$this->client) {
            return null;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            if (!$folder) {
                return null;
            }

            $message = $folder->messages()->getMessageByUid($uid);
            if (!$message) {
                return null;
            }

            $attachments = $message->getAttachments();
            $current = 0;

            foreach ($attachments as $attachment) {
                if ($current === $index) {
                    return [
                        'name' => $attachment->getName() ?? 'anexo',
                        'mime' => $attachment->getMimeType() ?? 'application/octet-stream',
                        'content' => $attachment->getContent(),
                        'size' => $attachment->getSize(),
                    ];
                }
                $current++;
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('IMAP getAttachment error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca mensagens por termo de pesquisa
     */
    public function searchMessages(string $folderPath, string $query, int $page = 1, int $perPage = 50): array
    {
        if (!$this->client) {
            return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }

        // Sanitiza a query para IMAP SEARCH
        $query = substr(str_replace(['"', '\\'], '', $query), 0, 200);

        if (empty(trim($query))) {
            return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            if (!$folder) {
                return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
            }

            // Busca por assunto OR remetente OR corpo
            // IMAP OR opera em pares: OR (crit1) (crit2)
            // Para 3 critérios: OR (SUBJECT x) (OR (FROM x) (TEXT x))
            $messages = $folder->messages()
                ->orWhere(function ($q) use ($query) {
                    $q->where('SUBJECT', $query);
                    $q->orWhere(function ($q2) use ($query) {
                        $q2->where('FROM', $query);
                        $q2->where('TEXT', $query);
                    });
                })
                ->setFetchOrder('desc')
                ->get();

            $total = count($messages);
            $totalPages = $total > 0 ? ceil($total / $perPage) : 0;

            // Paginação manual
            $offset = ($page - 1) * $perPage;
            $paged = $messages->slice($offset, $perPage);

            $result = [];
            foreach ($paged as $message) {
                $result[] = $this->formatMessagePreview($message);
            }

            usort($result, function ($a, $b) {
                return strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01');
            });

            return [
                'messages' => $result,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ];
        } catch (\Exception $e) {
            \Log::error('IMAP searchMessages error: ' . $e->getMessage());
            return ['messages' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }

    /**
     * Cria uma nova pasta IMAP
     */
    public function createFolder(string $name): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $this->client->createFolder($name);
            return true;
        } catch (\Exception $e) {
            // Alguns servidores (Dovecot) criam a pasta mas falham no subscribe/select.
            // Verifica se a pasta realmente existe após a tentativa.
            try {
                $folder = $this->client->getFolder($name);
                if ($folder) {
                    return true;
                }
            } catch (\Exception $e2) {
                // Ignora — pasta realmente não foi criada
            }

            \Log::error('IMAP createFolder error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Renomeia uma pasta IMAP
     */
    public function renameFolder(string $path, string $newName): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $folder = $this->client->getFolder($path);
            if (!$folder) {
                return false;
            }

            $folder->rename($newName);
            return true;
        } catch (\Exception $e) {
            // Verifica se a renomeação aconteceu apesar da exceção
            try {
                $renamed = $this->client->getFolder($newName);
                if ($renamed) {
                    return true;
                }
            } catch (\Exception $e2) {
                // Ignora
            }

            \Log::error('IMAP renameFolder error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui uma pasta IMAP
     */
    public function deleteFolder(string $path): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $folder = $this->client->getFolder($path);
            if (!$folder) {
                return false;
            }

            $folder->delete();
            return true;
        } catch (\Exception $e) {
            // Verifica se a exclusão aconteceu apesar da exceção
            try {
                $still = $this->client->getFolder($path);
                if (!$still) {
                    return true;
                }
            } catch (\Exception $e2) {
                // Pasta não encontrada = exclusão funcionou
                return true;
            }

            \Log::error('IMAP deleteFolder error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Adiciona uma mensagem raw (RFC822) a uma pasta IMAP
     */
    public function appendToFolder(string $folderPath, string $rawMessage, array $flags = []): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $folder = $this->client->getFolder($folderPath);
            if (!$folder) {
                // Tenta nomes alternativos para pastas comuns
                $alternatives = [
                    'Sent' => ['Sent', 'INBOX.Sent', 'Sent Messages', 'Enviados'],
                    'Drafts' => ['Drafts', 'INBOX.Drafts', 'Rascunhos'],
                ];

                foreach ($alternatives as $candidates) {
                    foreach ($candidates as $candidate) {
                        if ($candidate === $folderPath) {
                            continue;
                        }
                        try {
                            $folder = $this->client->getFolder($candidate);
                            if ($folder) {
                                break 2;
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }

            if (!$folder) {
                \Log::warning("IMAP: Pasta '{$folderPath}' não encontrada para append");
                return false;
            }

            $folder->appendMessage($rawMessage, $flags);
            return true;
        } catch (\Exception $e) {
            \Log::error('IMAP appendToFolder error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Colhe endereços de e-mail das mensagens recentes para autocomplete
     */
    public function harvestContacts(int $limit = 100): array
    {
        if (!$this->client) {
            return [];
        }

        $contacts = [];
        $seen = [];

        // Colhe do Sent primeiro, depois INBOX
        $foldersToScan = ['Sent', 'INBOX.Sent', 'INBOX'];

        foreach ($foldersToScan as $folderPath) {
            try {
                $folder = $this->client->getFolder($folderPath);
                if (!$folder) {
                    continue;
                }

                $messages = $folder->messages()
                    ->all()
                    ->setFetchOrder('desc')
                    ->limit(min($limit, 100))
                    ->get();

                foreach ($messages as $message) {
                    // Colhe To, From, Cc
                    $addressLists = [
                        $message->getTo(),
                        $message->getFrom(),
                        $message->getCc(),
                    ];

                    foreach ($addressLists as $addresses) {
                        if (!$addresses) {
                            continue;
                        }
                        foreach ($addresses as $address) {
                            $email = $address->mail ?? '';
                            if (!$email || isset($seen[$email])) {
                                continue;
                            }
                            $seen[$email] = true;
                            $name = $address->personal ?? '';
                            $contacts[] = [
                                'email' => $email,
                                'name' => $name ? $this->decodeMime($name) : '',
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Remove o próprio usuário da lista
        $userEmail = session('imap_credentials.email');
        $contacts = array_values(array_filter($contacts, function ($c) use ($userEmail) {
            return $c['email'] !== $userEmail;
        }));

        return array_slice($contacts, 0, 500);
    }

    /**
     * Verifica se uma pasta é do sistema (não pode ser renomeada/excluída)
     */
    public static function isSystemFolder(string $name): bool
    {
        $systemFolders = [
            'inbox', 'sent', 'drafts', 'trash', 'spam', 'junk',
            'inbox.sent', 'inbox.drafts', 'inbox.trash', 'inbox.spam', 'inbox.junk',
            'sent messages', 'lixeira', 'rascunhos', 'enviados', 'lixo eletrônico',
        ];

        return in_array(strtolower($name), $systemFolders);
    }
}
