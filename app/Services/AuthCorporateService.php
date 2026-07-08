<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthCorporateService
{
    // Usuários no qual possuem acesso a todas as filiais
    private array $administradores = ['flaviotostes.ps', 'roseane.silva', 'elciof', 'selton.lima'];
    
    public function getUserContext(Request $request): array
    {
        $loginRede = $request->server('REMOTE_USER') 
            ?? $request->server('AUTH_USER') 
            ?? $request->header('X-User-Login');
        
        if (!$loginRede && config('app.env') === 'local') {
            $loginRede = 'roseane.silva'; // Usuário de teste para ambiente local
        }

        $username = str_contains($loginRede, '\\') ? explode('\\', $loginRede)[1] : $loginRede;
        $isAdmin = in_array(strtolower($username), $this->administradores);
        $codsup = $isAdmin ? null : (string) $username;

        $listaPermissoes = [];

        if (!$isAdmin) {
           
            $usuarioBanco = DB::connection('sqlsrv_desenvolvimento')
                ->table('portal.dbo.users') 
                ->where('username', $username)
                ->first();

            if ($usuarioBanco) {
                
                $listaPermissoes = DB::connection('sqlsrv_desenvolvimento')
                    ->table('portal.dbo.permissions as p') 
                    ->join('portal.dbo.permission_user as pu', 'p.id', '=', 'pu.permission_id')
                    ->where('pu.user_id', $usuarioBanco->id)
                    ->where('pu.user_type', 'App\User') 
                    ->pluck('p.name')
                    ->toArray();
            }
        
        }

        return [
            'username' => $username,
            'is_admin' => $isAdmin,
            'codsup'   => $codsup,
            'permissoes' => $listaPermissoes
        ];
    }
}