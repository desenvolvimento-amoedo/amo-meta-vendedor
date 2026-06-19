<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MetaService;

class MetaController extends Controller
{
    // O Laravel pega automaticamente o MetaService aqui
    public function __construct(private MetaService $metaService) {}

    // Exibe a tela principal com os filtros e a listagem de vendedores
    public function index(Request $request)
    {
        $userContext = $request->attributes->get('corporate_user');

        $filtros = [
            'ano'    => $request->input('ano', date('Y')),
            'mes'    => $request->input('mes', date('m')),
            'codfil' => $request->input('codfil'),
            'codsup' => $request->input('codsup'),
        ];
        
        $codfil = null;
        if ($filtros['codsup']) {
            $codfil = $this->metaService->obterFilialDoGerente((int) $filtros['codsup']);
        }

        $listas = $this->metaService->obterFiltrosDeAcesso($userContext);
        
        // -----------------------------------------------------
        // PASSO 1: Buscamos os vendedores PRIMEIRO para validar se a filial tem movimento
        // -----------------------------------------------------
        $vendedores = [];

        if ($filtros['codfil'] || $filtros['codsup'] || !$userContext['is_admin']) {
            $vendedores = $this->metaService->listarVendedores(
                $userContext,
                $filtros
            );
        }

        // -----------------------------------------------------
        // PASSO 2: Validação de Segurança e Processamento da Procedure
        // -----------------------------------------------------
        if ($filtros['ano'] && $filtros['mes'] && count($vendedores) > 0) {
            
            // Determinamos qual filial estamos a avaliar
            $filialParaVerificar = $codfil ?? $filtros['codfil'] ?? null;

            // Criamos a query base de existência
            $queryExiste = \App\Models\AMO_META::where('ANO', $filtros['ano'])
                ->where('MES', $filtros['mes']);

            if ($filialParaVerificar) {
                $queryExiste->where('CODFILRH', $filialParaVerificar);
            }

            $existeMeta = $queryExiste->exists();

            // Se NÃO existirem metas salvas, mas há vendedores ativos, dispara a procedure
            if (!$existeMeta) {
                $this->metaService->gerarMetasSugeridas(
                    (int) $filtros['ano'],
                    (int) $filtros['mes'],
                    $filialParaVerificar ? (int) $filialParaVerificar : null
                );
                
                // Recarrega os vendedores para exibir os dados recém-gerados na grid
                $vendedores = $this->metaService->listarVendedores($userContext, $filtros);
            }
        }

        return view(
            'metas.index',
            compact('userContext', 'filtros', 'listas', 'vendedores')
        );
    }

    /**
     * Processa o salvamento em lote enviado pelo botão "Salvar Alterações"
     */
    public function store(Request $request)
    {
        $ano = (int) $request->input('ano');
        $mes = (int) $request->input('mes');
        $metasDigitadas = $request->input('metas', []);

        $userContext = $request->attributes->get('corporate_user');
        $usuarioLogado = $userContext['username'] ?? 'sistema_metas';

        try {
            $this->metaService->salvarMetasEmLote($ano, $mes, $metasDigitadas, $usuarioLogado);
            
            // 1. Grava a mensagem de sucesso na sessão
            $request->session()->flash('success', 'Metas e histórico gravados com sucesso!');
            $request->session()->save();

            // 2. Volta para a página anterior sem aparecer na url, mas mantendo os filtros selecionados
            return redirect()->back()->withInput();

        } catch (\Exception $e) {
            $request->session()->flash('error', $e->getMessage());
            $request->session()->save();

            return redirect()->back()->withInput();
        }
    }
}