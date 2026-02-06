<?php

namespace App\Http\Controllers;

use App\Models\UserSignature;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SignatureController extends Controller
{
    /**
     * Exibe a página de edição de assinatura
     */
    public function index(): Response
    {
        $userEmail = session('user.email');
        $signature = $userEmail ? UserSignature::findByEmail($userEmail) : null;

        return Inertia::render('Settings/Signature', [
            'user' => session('user'),
            'branding' => session('branding'),
            'signature' => $signature?->signature_html ?? '',
            'displayName' => $signature?->display_name ?? '',
        ]);
    }

    /**
     * Salva a assinatura do usuário
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'signature_html' => 'nullable|string|max:50000',
            'display_name' => 'nullable|string|max:100',
        ]);

        $userEmail = session('user.email');
        if (!$userEmail) {
            return response()->json(['error' => 'Sessão expirada.'], 401);
        }

        $html = $request->input('signature_html', '');

        // Sanitiza o HTML com HTMLPurifier
        if ($html) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,s,a[href|target],img[src|alt|width|height|style],span[style],div[style],table[style|border|cellpadding|cellspacing|width],tr,td[style|width|colspan|rowspan],th[style|width|colspan|rowspan],thead,tbody,h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,hr');
            $config->set('HTML.AllowedAttributes', 'style,href,target,src,alt,width,height,border,cellpadding,cellspacing,colspan,rowspan');
            $config->set('CSS.AllowedProperties', 'color,background-color,font-size,font-family,font-weight,font-style,text-decoration,text-align,padding,margin,border,border-color,border-width,border-style,width,height,max-width,line-height');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'data' => true]);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);

            $purifier = new \HTMLPurifier($config);
            $html = $purifier->purify($html);
        }

        $displayName = trim($request->input('display_name', ''));

        UserSignature::updateOrCreate(
            ['email' => $userEmail],
            [
                'signature_html' => $html,
                'display_name' => $displayName ?: null,
            ],
        );

        return response()->json(['success' => true, 'message' => 'Assinatura salva com sucesso.']);
    }
}
