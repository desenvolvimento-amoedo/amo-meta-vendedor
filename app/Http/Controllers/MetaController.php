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
    
            if ($filtros['ano'] && $filtros['mes']) {
                
                // Determinamos qual filial estamos a avaliar: 
                // Se escolheu um gerente, usamos a filial dele ($codfil). Se escolheu a filial direto no combo, usamos essa.
                $filialParaVerificar = $codfil ?? $filtros['codfil'] ?? null;

                // Criamos a query base de existência
                $queryExiste = \App\Models\AMO_META::where('ANO', $filtros['ano'])
                    ->where('MES', $filtros['mes']);

                // Se houver uma filial selecionada nos filtros, validamos estritamente por ela
                if ($filialParaVerificar) {
                    $queryExiste->where('CODFILRH', $filialParaVerificar);
                }

                $existeMeta = $queryExiste->exists();

                // Se NÃO existirem metas para esta combinação específica, força a geração!
                if (!$existeMeta) {
                    $this->metaService->gerarMetasSugeridas(
                        (int) $filtros['ano'],
                        (int) $filtros['mes']
                    );
                }
            }
            // -----------------------------------------------------

            $vendedores = [];

            // Busca e lista os vendedores na grid se houver uma Filial ou Gerente selecionado, 
            // ou se o usuário logado não for Administrador (forçando a visão do próprio gerente)
            if ($filtros['codfil'] || $filtros['codsup'] || !$userContext['is_admin']) {
                $vendedores = $this->metaService->listarVendedores(
                    $userContext,
                    $filtros
                );
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
        $codfil = $request->input('codfil'); // Captura a filial para devolver a tela filtrada
        
        $metasDigitadas = $request->input('metas', []);

        $userContext = $request->get('userContext') ?? session('userContext');
        $usuarioLogado = $userContext['username'] ?? 'sistema_metas';

        try {
            $this->metaService->salvarMetasEmLote($ano, $mes, $metasDigitadas, $usuarioLogado);
            
            // Recarrega a página de busca enviando os mesmos filtros via GET de forma correta.

            return redirect()->route('metas.index', [
                'ano' => $ano,
                'mes' => $mes,
                'codfil' => $codfil
            ])->with('success', 'Metas e histórico gravados com sucesso!');

       } catch (\Exception $e) {
           // return redirect()->route('metas.index', [
             //   'ano' => $ano,
            //    'mes' => $mes,
           //     'codfil' => $codfil
           // ])->with('error', $e->getMessage());
        }
    }
}