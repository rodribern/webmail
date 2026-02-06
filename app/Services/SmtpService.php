<?php

namespace App\Services;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\UserSignature;

class SmtpService
{
    private string $host;
    private int $port;

    public function __construct()
    {
        $this->host = config('mail.mailers.smtp.host', '127.0.0.1');
        $this->port = 587;
    }

    /**
     * Envia um e-mail usando credenciais da sessão do usuário
     *
     * @return array{success: bool, error?: string, raw_message?: string}
     */
    public function send(
        array $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $cc = [],
        array $bcc = [],
        array $attachmentPaths = [],
        ?string $inReplyTo = null,
        ?string $references = null,
    ): array {
        $credentials = session('imap_credentials');

        if (!$credentials) {
            return ['success' => false, 'error' => 'Credenciais não encontradas na sessão.'];
        }

        $userEmail = $credentials['email'];

        // Rate limiting: 30 envios por hora por usuário
        $rateLimitKey = 'smtp_send:' . $userEmail;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return [
                'success' => false,
                'error' => "Limite de envio atingido. Tente novamente em {$seconds} segundos.",
            ];
        }

        try {
            $transport = new EsmtpTransport($this->host, $this->port, false);
            $transport->setUsername($userEmail);
            $transport->setPassword(decrypt($credentials['password']));

            // Conexão local (127.0.0.1) — o certificado do Postfix tem CN=scriptorium.net.br,
            // não CN=127.0.0.1, então desabilitamos verificação de peer name para conexão local.
            $stream = $transport->getStream();
            $stream->setStreamOptions([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => false,
                ],
            ]);

            // Busca nome de exibição do usuário
            $displayName = UserSignature::findByEmail($userEmail)?->display_name ?? '';

            $email = (new Email())
                ->from(new Address($userEmail, $displayName))
                ->subject($subject ?? '');

            // Destinatários To (obrigatório)
            foreach ($to as $recipient) {
                $addr = $this->parseRecipient($recipient);
                if ($addr) {
                    $email->addTo($addr);
                }
            }

            // CC
            foreach ($cc as $recipient) {
                $addr = $this->parseRecipient($recipient);
                if ($addr) {
                    $email->addCc($addr);
                }
            }

            // BCC
            foreach ($bcc as $recipient) {
                $addr = $this->parseRecipient($recipient);
                if ($addr) {
                    $email->addBcc($addr);
                }
            }

            // Corpo — garante que sempre haja conteúdo para o Symfony Mailer
            if (!$htmlBody) {
                $htmlBody = ' ';
            }
            $email->html($htmlBody);
            $email->text($textBody ?: strip_tags(html_entity_decode($htmlBody)) ?: ' ');

            // Headers de threading
            $headers = $email->getHeaders();
            if ($inReplyTo) {
                $headers->addIdHeader('In-Reply-To', $inReplyTo);
            }
            if ($references) {
                $headers->addIdHeader('References', $references);
            }

            // Anexos
            foreach ($attachmentPaths as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $email->attachFromPath(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path']),
                        $attachment['mime'] ?? null,
                    );
                }
            }

            $mailer = new Mailer($transport);
            $mailer->send($email);

            RateLimiter::hit($rateLimitKey, 3600);

            // Retorna a mensagem raw para salvar na pasta Sent
            return [
                'success' => true,
                'raw_message' => $email->toString(),
            ];
        } catch (\Exception $e) {
            \Log::error('SMTP send error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Falha ao enviar e-mail: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Converte array de destinatário para Address
     */
    private function parseRecipient(array $recipient): ?Address
    {
        $email = $recipient['email'] ?? null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $name = $recipient['name'] ?? '';
        return $name ? new Address($email, $name) : new Address($email);
    }
}
