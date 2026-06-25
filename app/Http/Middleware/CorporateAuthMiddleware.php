<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuthCorporateService;
use Symfony\Component\HttpFoundation\Response;

class CorporateAuthMiddleware
{
    // O Laravel injeta automaticamente o serviço de autenticação corporativa
    public function __construct(private AuthCorporateService $authService) {}

    /**
     * Intercepta a requisição e valida o usuário de rede.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtém o mapeamento do usuário logado (se é admin, gerente, etc.)
        $userContext = $this->authService->getUserContext($request);

        // Se por algum motivo a rede não identificou o usuário, barra o acesso
        if (!$userContext['username']) {
            abort(403, 'Acesso não autorizado. Usuário não identificado na rede corporativa.');
        }

        // Adiciona o contexto do usuário dentro dos atributos do Request.
        // Assim, o Controller e as Views vão conseguir ler esses dados facilmente.
        $request->attributes->set('corporate_user', $userContext);

        return $next($request);
    }
}
