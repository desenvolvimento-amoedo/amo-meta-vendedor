<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MetaService;

class MetaController extends Controller
{
    // O Laravel injeta automaticamente o MetaService aqui
    public function __construct(private MetaService $metaService) {}


    // Exibe a tela principal com os filtros e a listagem de vendedores

    public function index(Request $request)
    {
        // Recupera os dados do usuário de rede que o Middleware injetou
        $userContext = $request->attributes->get('corporate_user');

        // Captura os filtros vindos da URL/Formulário (com fallbacks para o ano/mês atual)
        $filtros = [
            'ano'    => $request->input('ano', date('Y')),
            'mes'    => $request->input('mes', date('m')),
            'codfil' => $request->input('codfil'),
            'codsup' => $request->input('codsup'),
        ];

        // Busca as listas que vão preencher os selects (Filiais e Gerentes permitidos)
        $listas = $this->metaService->obterFiltrosDeAcesso($userContext);
        
        // Regra de Fluxo: Só busca os vendedores se houver algum filtro de busca selecionado,
        // OU se o usuário for um gerente comum (que já entra com o filtro do seu próprio CODSUP)
        $vendedores = [];
        if ($filtros['codfil'] || $filtros['codsup'] || !$userContext['is_admin']) {
            $vendedores = $this->metaService->listarVendedores($userContext, $filtros);
        }
  
        // Retorna a view enviando o contexto do usuário, os filtros aplicados e as listagens
        return view('metas.index', compact('userContext', 'filtros', 'listas', 'vendedores'));
    }

    /**
     * Processa o salvamento em lote das metas digitadas
     */
    public function store(Request $request)
    {
        // Validação básica
        $request->validate([
            'ano'   => 'required|integer',
            'mes'   => 'required|integer',
            'meta' => 'required|array',
            'meta.*.codgerente' => 'required|integer'
        ]);

        // TRAVA DE SEGURANÇA: Impede alteração de meses anteriores
        $anoAtual = (int) date('Y');
        $mesAtual = (int) date('m');
        $anoReq = (int) $request->input('ano');
        $mesReq = (int) $request->input('mes');

        if ($anoReq < $anoAtual || ($anoReq == $anoAtual && $mesReq < $mesAtual)) {
            return redirect()->back()->with('error', 'Operação negada: Não é permitido alterar metas de meses que já passaram.');
        }

        try {
            // Tenta salvar as metas
            $this->metaService->salvarMetasEmLote(
                $request->input('meta'),
                $request->input('ano'),
                $request->input('mes'),
            );

            // Se der tudo certo, redireciona com sucesso
            return redirect()->route('metas.index', $request->only('ano', 'mes', 'codfil', 'codsup'))
                ->with('success', 'Metas processadas e salvas com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Falha ao salvar metas: ' . $e->getMessage());
        }
    }
}
