<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthCorporateService
{
    // Usuários que possuem acesso a todas as filiais
    private array $administradores = ['flaviotostes.ps', 'elciof', 'selton.lima'];
    
    public function getUserContext(Request $request): array
    {
        // 1. Tenta pegar do servidor ou da URL 
        $loginRede = $request->server('REMOTE_USER') 
            ?? $request->server('AUTH_USER') 
            ?? $request->header('X-User-Login')
            ?? $request->route('usuario') 
            ?? $request->input('usuario');

        // Se não vier absolutamente nada, barra o acesso
        if (!$loginRede) {
            abort(401, 'Usuário não informado ou não autenticado na rede.');
        }

        $username = str_contains($loginRede, '\\') ? explode('\\', $loginRede)[1] : $loginRede;
        $isAdmin = in_array(strtolower($username), $this->administradores);
        
        $listaPermissoes = [];
        $codsup = null;

        if (!$isAdmin) {
            $usuarioBanco = DB::connection('sqlsrv_desenvolvimento')
                ->table('portal.dbo.users') 
                ->where('username', $username)
                ->first();
            
            if (!$usuarioBanco) {
                abort(403, 'Usuário sem permissão de acesso ao sistema de Metas.');
            }
                
            // Busca se ele é gerente na view
            $gerenteGemco = DB::connection('sqlsrv_desenvolvimento')
                ->table('portal.dbo.gerentes_amo_meta') 
                ->where('username', $username)
                ->first();

            $codsup = $gerenteGemco ? $gerenteGemco->codvendr : null;
            
            $permissoesDb = DB::connection('sqlsrv_desenvolvimento')
                ->table('portal.dbo.permissions as p') 
                ->join('portal.dbo.permission_user as pu', 'p.id', '=', 'pu.permission_id')
                ->where('pu.user_id', $usuarioBanco->id)
                ->where('pu.user_type', 'App\User') 
                ->select('p.id', 'p.name')
                ->get();

            $listaPermissoes = $permissoesDb->pluck('name')->toArray();
            $listaPermissoesIds = $permissoesDb->pluck('id')->toArray();

            // -----------------------------------------------------------------
            // VERIFICAÇÕES PELO ID DA PERMISSÃO
            // -----------------------------------------------------------------
            
            // ID 25 = TI (Promove a Admin e limpa o gerente para ver todas as filiais)
            if (in_array(25, $listaPermissoesIds)) {
                $isAdmin = true; 
                $codsup = null;  
            }

            // ID 36 = Gerente Amoedo
            $temPermissaoGerente = in_array(36, $listaPermissoesIds);

            // Só bloqueia se não for admin (lista, ou TI) e NÃO tiver a permissão 36
            if (!$isAdmin && !$temPermissaoGerente) {
                abort(403, 'Acesso restrito: Você não possui vínculo como gerente para acessar esta tela.');
            }
        }

        return [
            'username'   => $username,
            'is_admin'   => $isAdmin,
            'codsup'     => $codsup,
            'permissoes' => $listaPermissoes
        ];
    }
}