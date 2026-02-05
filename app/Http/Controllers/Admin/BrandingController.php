<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandingRequest;
use App\Models\Domain;
use App\Models\DomainBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class BrandingController extends Controller
{
    /**
     * Exibe a página de configuração de branding.
     */
    public function index(): Response
    {
        $user = session('user');
        $domainId = $user['domain_id'];

        $domain = Domain::with('branding')->find($domainId);
        $branding = $domain?->branding;

        // Dados atuais ou padrão
        $currentBranding = [
            'logo' => $branding?->logo_path ? asset('storage/' . $branding->logo_path) : null,
            'logo_path' => $branding?->logo_path,
            'favicon' => $branding?->favicon_path ? asset('storage/' . $branding->favicon_path) : null,
            'favicon_path' => $branding?->favicon_path,
            'primary_color' => $branding?->primary_color ?? '#3B82F6',
            'secondary_color' => $branding?->secondary_color ?? '#1E40AF',
            'background_color' => $branding?->background_color ?? '#F9FAFB',
            'sidebar_color' => $branding?->sidebar_color ?? '#FFFFFF',
            'custom_css' => $branding?->custom_css ?? '',
        ];

        return Inertia::render('Admin/Branding', [
            'user' => $user,
            'branding' => session('branding'),
            'domain' => [
                'id' => $domain->id,
                'name' => $domain->name,
                'display_name' => $domain->display_name,
            ],
            'currentBranding' => $currentBranding,
        ]);
    }

    /**
     * Atualiza as configurações de branding (cores e CSS).
     */
    public function update(BrandingRequest $request): JsonResponse
    {
        $user = session('user');
        $domainId = $user['domain_id'];

        $branding = DomainBranding::firstOrNew(['domain_id' => $domainId]);

        $branding->fill([
            'domain_id' => $domainId,
            'primary_color' => $request->primary_color,
            'secondary_color' => $request->secondary_color,
            'background_color' => $request->background_color,
            'sidebar_color' => $request->sidebar_color,
            'custom_css' => $request->custom_css,
        ]);

        $branding->save();

        // Atualiza a sessão com o novo branding
        $this->updateSessionBranding($branding);

        return response()->json([
            'success' => true,
            'message' => 'Configurações salvas com sucesso.',
            'branding' => $branding->toArray(),
        ]);
    }

    /**
     * Upload de logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,svg,webp',
                'max:2048', // 2MB
            ],
        ], [
            'logo.required' => 'Selecione um arquivo de logo.',
            'logo.mimes' => 'O logo deve ser PNG, JPG, SVG ou WebP.',
            'logo.max' => 'O logo não pode exceder 2MB.',
        ]);

        $user = session('user');
        $domainId = $user['domain_id'];
        $domain = Domain::find($domainId);

        $branding = DomainBranding::firstOrNew(['domain_id' => $domainId]);

        // Remove logo anterior se existir
        if ($branding->logo_path) {
            Storage::disk('public')->delete($branding->logo_path);
        }

        $file = $request->file('logo');
        $extension = $file->getClientOriginalExtension();

        // Gera nome único baseado no domínio
        $filename = 'logos/' . $domain->name . '_' . time() . '.' . $extension;

        // Se não for SVG, redimensiona
        if (!in_array(strtolower($extension), ['svg'])) {
            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($file);

            // Redimensiona mantendo proporção, máximo 400px de largura
            $image->scaleDown(width: 400);

            // Converte para PNG e salva
            $pngFilename = str_replace('.' . $extension, '.png', $filename);
            Storage::disk('public')->put($pngFilename, $image->toPng()->toString());
            $filename = $pngFilename;
        } else {
            // SVG: salva diretamente
            Storage::disk('public')->put($filename, file_get_contents($file));
        }

        $branding->domain_id = $domainId;
        $branding->logo_path = $filename;
        $branding->save();

        // Atualiza a sessão
        $this->updateSessionBranding($branding);

        return response()->json([
            'success' => true,
            'message' => 'Logo enviado com sucesso.',
            'logo' => asset('storage/' . $filename),
            'logo_path' => $filename,
        ]);
    }

    /**
     * Upload de favicon.
     */
    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => [
                'required',
                'file',
                'mimes:ico,png',
                'max:512', // 512KB
            ],
        ], [
            'favicon.required' => 'Selecione um arquivo de favicon.',
            'favicon.mimes' => 'O favicon deve ser ICO ou PNG.',
            'favicon.max' => 'O favicon não pode exceder 512KB.',
        ]);

        $user = session('user');
        $domainId = $user['domain_id'];
        $domain = Domain::find($domainId);

        $branding = DomainBranding::firstOrNew(['domain_id' => $domainId]);

        // Remove favicon anterior se existir
        if ($branding->favicon_path) {
            Storage::disk('public')->delete($branding->favicon_path);
        }

        $file = $request->file('favicon');
        $extension = $file->getClientOriginalExtension();

        $filename = 'favicons/' . $domain->name . '_' . time() . '.' . $extension;

        Storage::disk('public')->put($filename, file_get_contents($file));

        $branding->domain_id = $domainId;
        $branding->favicon_path = $filename;
        $branding->save();

        // Atualiza a sessão
        $this->updateSessionBranding($branding);

        return response()->json([
            'success' => true,
            'message' => 'Favicon enviado com sucesso.',
            'favicon' => asset('storage/' . $filename),
            'favicon_path' => $filename,
        ]);
    }

    /**
     * Remove o logo.
     */
    public function removeLogo(): JsonResponse
    {
        $user = session('user');
        $domainId = $user['domain_id'];

        $branding = DomainBranding::where('domain_id', $domainId)->first();

        if ($branding && $branding->logo_path) {
            Storage::disk('public')->delete($branding->logo_path);
            $branding->logo_path = null;
            $branding->save();

            $this->updateSessionBranding($branding);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logo removido com sucesso.',
        ]);
    }

    /**
     * Remove o favicon.
     */
    public function removeFavicon(): JsonResponse
    {
        $user = session('user');
        $domainId = $user['domain_id'];

        $branding = DomainBranding::where('domain_id', $domainId)->first();

        if ($branding && $branding->favicon_path) {
            Storage::disk('public')->delete($branding->favicon_path);
            $branding->favicon_path = null;
            $branding->save();

            $this->updateSessionBranding($branding);
        }

        return response()->json([
            'success' => true,
            'message' => 'Favicon removido com sucesso.',
        ]);
    }

    /**
     * Atualiza o branding na sessão do usuário.
     */
    private function updateSessionBranding(DomainBranding $branding): void
    {
        session(['branding' => $branding->toArray()]);
    }
}
