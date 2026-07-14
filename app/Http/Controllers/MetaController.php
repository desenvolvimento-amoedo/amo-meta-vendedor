<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MetaService;

class MetaController extends Controller
{
    public function __construct(private MetaService $metaService) {}

    // Exibe a tela principal com os filtros e a listagem de vendedores
    public function index(Request $request, $usuario)
    {
        // dd($usuario);
        $userContext = $request->attributes->get('corporate_user');
        $isAdmin = $userContext['is_admin'] ?? false;
        $permissoes = $userContext['permissoes'] ?? [];
        
        // Verifica se a permissão 'meta-vendedor' está atrelada a ele
        $restritoASuaFilial = in_array('meta-vendedor', $permissoes);

        $filtros = [
            'ano'    => $request->input('ano', date('Y')),
            'mes'    => $request->input('mes', date('m')),
            'codfil' => $request->input('codfil'),
            'codsup' => $request->input('codsup'),
        ];
        
        // Se ele não é admin e está restrito, forçamos a busca só na equipe dele
        if (!$isAdmin && $restritoASuaFilial) {
            $filtros['codsup'] = $userContext['codsup'];
        }

        $codfil = null;
        if ($filtros['codsup']) {
            $codfil = $this->metaService->obterFilialDoGerente((int) $filtros['codsup']);
        }

        $listas = $this->metaService->obterFiltrosDeAcesso($userContext);
        
        $vendedores = [];

        if ($filtros['codfil'] || $filtros['codsup'] || !$userContext['is_admin']) {
            $vendedores = $this->metaService->listarVendedores(
                $userContext,
                $filtros
            );
        }


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
            compact('userContext', 'filtros', 'listas', 'vendedores', 'restritoASuaFilial', 'usuario')
        );
    }

  public function store(Request $request)
    {
        $ano = (int) $request->input('ano');
        $mes = (int) $request->input('mes');
        $metasDigitadas = $request->input('metas', []);

        $userContext = $request->attributes->get('corporate_user');

        try {

            $this->metaService->salvarMetasEmLote($ano, $mes, $metasDigitadas, $userContext);
            
            return redirect()
                ->back()
                ->with('success', 'Metas e histórico gravados com sucesso!');

        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Erro interno ao salvar: ' . $e->getMessage());
        }
    }
}