<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureImapAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('imap_credentials') || !session()->has('user')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }

            return redirect()->route('login');
        }

        // Timeout absoluto de sessão: 8 horas independente de atividade
        $loginAt = session('login_at');
        if ($loginAt && now()->diffInHours($loginAt) >= 8) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sessão expirada por segurança.'], 401);
            }

            return redirect()->route('login')
                ->with('error', 'Sessão expirada por segurança. Faça login novamente.');
        }

        return $next($request);
    }
}
