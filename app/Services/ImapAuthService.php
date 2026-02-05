<?php

namespace App\Services;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;

class ImapAuthService
{
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
     * Tenta autenticar o usuário via IMAP
     *
     * @return array{success: bool, message: string, client?: Client}
     */
    public function authenticate(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'E-mail e senha são obrigatórios.',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'E-mail inválido.',
            ];
        }

        try {
            $clientManager = new ClientManager();

            $client = $clientManager->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption,
                'validate_cert' => $this->validateCert,
                'username' => $email,
                'password' => $password,
                'protocol' => 'imap',
                'authentication' => null,
            ]);

            $client->connect();

            return [
                'success' => true,
                'message' => 'Autenticação bem-sucedida.',
                'client' => $client,
            ];
        } catch (AuthFailedException $e) {
            return [
                'success' => false,
                'message' => 'E-mail ou senha incorretos.',
            ];
        } catch (ConnectionFailedException $e) {
            return [
                'success' => false,
                'message' => 'Não foi possível conectar ao servidor de e-mail.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao autenticar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cria um cliente IMAP a partir das credenciais da sessão
     */
    public function createClientFromSession(): ?Client
    {
        $credentials = session('imap_credentials');

        if (!$credentials) {
            return null;
        }

        try {
            $clientManager = new ClientManager();

            $client = $clientManager->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption,
                'validate_cert' => $this->validateCert,
                'username' => $credentials['email'],
                'password' => decrypt($credentials['password']),
                'protocol' => 'imap',
                'authentication' => null,
            ]);

            $client->connect();

            return $client;
        } catch (\Exception $e) {
            return null;
        }
    }
}
