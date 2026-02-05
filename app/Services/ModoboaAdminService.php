<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModoboaAdminService
{
    /**
     * Verifica se um email é administrador de um domínio no Modoboa.
     *
     * Condições verificadas:
     * 1. SuperAdmin (is_superuser = true) → admin de todos os domínios
     * 2. DomainAdmin (grupo DomainAdmins) com objectaccess ao domínio
     */
    public function isDomainAdmin(string $email, string $domainName): bool
    {
        try {
            return $this->isSuperAdmin($email) || $this->hasDomainAccess($email, $domainName);
        } catch (\Exception $e) {
            Log::error('ModoboaAdminService: Erro ao verificar admin', [
                'email' => $email,
                'domain' => $domainName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verifica se o usuário é SuperAdmin do Modoboa.
     */
    private function isSuperAdmin(string $email): bool
    {
        return DB::connection('modoboa')
            ->table('core_user')
            ->where('username', $email)
            ->where('is_superuser', true)
            ->exists();
    }

    /**
     * Verifica se o usuário é DomainAdmin com acesso ao domínio específico.
     */
    private function hasDomainAccess(string $email, string $domainName): bool
    {
        return DB::connection('modoboa')
            ->table('core_user as u')
            ->join('core_user_groups as ug', 'u.id', '=', 'ug.user_id')
            ->join('auth_group as g', 'ug.group_id', '=', 'g.id')
            ->join('core_objectaccess as oa', 'u.id', '=', 'oa.user_id')
            ->join('django_content_type as ct', 'oa.content_type_id', '=', 'ct.id')
            ->join('admin_domain as d', 'oa.object_id', '=', 'd.id')
            ->where('g.name', 'DomainAdmins')
            ->where('ct.app_label', 'admin')
            ->where('ct.model', 'domain')
            ->where('u.username', $email)
            ->where('d.name', $domainName)
            ->exists();
    }
}
