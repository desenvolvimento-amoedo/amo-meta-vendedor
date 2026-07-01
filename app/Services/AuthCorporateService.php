<?php

namespace App\Services;

use Illuminate\Http\Request;

class AuthCorporateService
{
    // REGRA: Existem apenas 3 usuários administradores com acesso total
    // Todos os outros usuários são considerados gerentes e só veem os dados relacionados a eles
    //SUBSTITUIR 'admin1', 'admin2', 'admin3' PELOS LOGINS DE REDE DOS ADMINISTRADORES REAIS
    private array $administradores = ['admin1', 'roseane.silva', 'admin3'];

    
    //Identifica automaticamente o usuário logado na rede/portal.
    
    public function getUserContext(Request $request): array
    {
        // Captura o login automático
        $loginRede = $request->server('REMOTE_USER') 
            ?? $request->server('AUTH_USER') 
            ?? $request->header('X-User-Login');
        
        
        // Fallback apenas para o ambiente de desenvolvimento local não quebrar
        if (!$loginRede && config('app.env') === 'local') {
            $loginRede = 'admin1'; // Simula um admin para desenvolvimento
     }

        // Limpa o domínio caso o Windows envie no formato "DOMINIO\usuario"
        $username = str_contains($loginRede, '\\') ? explode('\\', $loginRede)[1] : $loginRede;
        
        // Verifica se o usuário limpo está na lista de admins
        $isAdmin = in_array(strtolower($username), $this->administradores);
        
        // Se NÃO for admin, a regra diz que ele é gerente.
        // Assumimos que o login de rede dele mapeia diretamente para o CODSUP (ex: número de matrícula)
        $codsup = $isAdmin ? null : (string) $username;

        return [
            'username' => $username,
            'is_admin' => $isAdmin,
            'codsup' => $codsup
        ];
    }
}