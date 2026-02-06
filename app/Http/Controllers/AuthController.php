<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\DomainBranding;
use App\Services\ImapAuthService;
use App\Services\ModoboaAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(
        private ImapAuthService $imapAuthService,
        private ModoboaAdminService $modoboaAdminService,
    ) {}

    public function showLogin(Request $request): Response
    {
        // Extrai o domínio de email a partir do hostname (ex: webmail-dev.ista.com.br → ista.com.br)
        $host = $request->getHost();
        $domain = Domain::whereRaw("? LIKE CONCAT('%.', name)", [$host])->first();

        $branding = $domain?->branding?->toArray() ?? DomainBranding::getDefault();

        return Inertia::render('Auth/Login', [
            'branding' => $branding,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'A senha é obrigatória.',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        // Rate limiting: 5 tentativas por minuto por IP
        $rateLimitKey = 'login:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return back()->withErrors([
                'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        // Rate limiting: 10 tentativas por 5 minutos por email (protege contra brute force distribuído)
        $rateLimitKeyEmail = 'login:email:' . strtolower($email);

        if (RateLimiter::tooManyAttempts($rateLimitKeyEmail, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKeyEmail);
            return back()->withErrors([
                'email' => "Muitas tentativas para esta conta. Tente novamente em {$seconds} segundos.",
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);
        RateLimiter::hit($rateLimitKeyEmail, 300);

        // Tenta autenticar via IMAP
        $result = $this->imapAuthService->authenticate($email, $password);

        if (!$result['success']) {
            return back()->withErrors([
                'email' => $result['message'],
            ]);
        }

        // Fecha a conexão de teste
        if (isset($result['client'])) {
            $result['client']->disconnect();
        }

        // Limpa rate limits em caso de sucesso
        RateLimiter::clear($rateLimitKey);
        RateLimiter::clear($rateLimitKeyEmail);

        // Regenera a sessão para prevenir session fixation
        $request->session()->regenerate();

        // Extrai o domínio do email
        $domainName = Domain::extractFromEmail($email);

        // Busca ou cria o domínio
        $domain = Domain::firstOrCreate(
            ['name' => $domainName],
            ['display_name' => $domainName]
        );

        // Carrega o branding do domínio
        $branding = $domain->branding?->toArray() ?? DomainBranding::getDefault();

        // Verifica se é admin do domínio (consulta o Modoboa)
        $isAdmin = $this->modoboaAdminService->isDomainAdmin($email, $domainName);

        // Armazena credenciais na sessão (senha criptografada)
        session([
            'imap_credentials' => [
                'email' => $email,
                'password' => encrypt($password),
            ],
            'user' => [
                'email' => $email,
                'domain' => $domainName,
                'domain_id' => $domain->id,
                'is_admin' => $isAdmin,
            ],
            'branding' => $branding,
            'login_at' => now(),
        ]);

        return redirect()->intended('/mail');
    }

    public function logout(Request $request)
    {
        // Limpa a sessão
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
