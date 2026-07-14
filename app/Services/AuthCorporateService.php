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
        $loginRede = $request->server('REMOTE_USER') 
            ?? $request->server('AUTH_USER') 
            ?? $request->header('X-User-Login');
        
        if (!$loginRede) {
            $loginRede = $request->route('usuario') ?? $request->input('usuario');
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

            if ($usuarioBanco) {
                
               // View nova gerentes_amo_meta 
                $gerenteGemco = DB::connection('sqlsrv_desenvolvimento')
                    ->table('portal.dbo.gerentes_amo_meta') 
                    ->where('username', $username)
                    ->first();

                $codsup = $gerenteGemco ? $gerenteGemco->codvendr : null;
                
                $listaPermissoes = DB::connection('sqlsrv_desenvolvimento')
                    ->table('portal.dbo.permissions as p') 
                    ->join('portal.dbo.permission_user as pu', 'p.id', '=', 'pu.permission_id')
                    ->where('pu.user_id', $usuarioBanco->id)
                    ->where('pu.user_type', 'App\User') 
                    ->pluck('p.name')
                    ->toArray();

                //  Verifica se faz parte do TI
                if (in_array('TI', $listaPermissoes)) {
                    $isAdmin = true; 
                    $codsup = null;  
                }
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