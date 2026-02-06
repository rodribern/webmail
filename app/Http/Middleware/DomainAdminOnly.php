<?php

namespace App\Http\Middleware;

use App\Services\ModoboaAdminService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DomainAdminOnly
{
    /**
     * Verifica se o usuário autenticado é administrador do domínio.
     * Re-valida contra o Modoboa a cada 15 minutos.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = session('user');

        // Re-valida status de admin a cada 15 minutos
        if ($user && !empty($user['email'])) {
            $lastCheck = session('last_admin_check');
            if (!$lastCheck || now()->diffInMinutes($lastCheck) >= 15) {
                $isAdmin = app(ModoboaAdminService::class)->isDomainAdmin(
                    $user['email'],
                    $user['domain'] ?? ''
                );
                session([
                    'user.is_admin' => $isAdmin,
                    'last_admin_check' => now(),
                ]);
                $user['is_admin'] = $isAdmin;
            }
        }

        if (!$user || empty($user['is_admin'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Acesso negado. Apenas administradores do domínio podem acessar esta área.'
                ], 403);
            }

            return redirect()->route('mail.inbox')
                ->with('error', 'Acesso negado. Apenas administradores do domínio podem acessar esta área.');
        }

        return $next($request);
    }
}
