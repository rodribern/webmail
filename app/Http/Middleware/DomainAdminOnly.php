<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DomainAdminOnly
{
    /**
     * Verifica se o usuário autenticado é administrador do domínio.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = session('user');

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
