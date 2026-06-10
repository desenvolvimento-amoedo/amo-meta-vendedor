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
                            $codfil = $this->metaService->obterFilialDoGerente(
                                (int) $filtros['codsup']
                            );
                        }
            $listas = $this->metaService->obterFiltrosDeAcesso($userContext);
        
            // Gera metas sugeridas apenas se ainda não existirem para o período/filial
          
            if ($filtros['codsup'] || !$userContext['is_admin']) {

                
               $existeMeta = \App\Models\AMO_META::where('ANO', $filtros['ano'])
                ->where('MES', $filtros['mes'])
                ->where('CODFILRH', $codfil)
                ->exists();
        
                if (!$existeMeta) {
                   
                
                  $this->metaService->gerarMetasSugeridas(
                    (int) $filtros['ano'],
                    (int) $filtros['mes']
                );
                                    
                    
                }
            }

            $vendedores = [];
        
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
     * Processa o salvamento em lote das metas digitadas
     */
        public function store(Request $request)
        {
            $request->validate([
                'ano'   => 'required|integer',
                'mes'   => 'required|integer',
                'metas' => 'required|array',
            ]);

            $anoAtual = (int) date('Y');
            $mesAtual = (int) date('m');

            $anoReq = (int) $request->input('ano');
            $mesReq = (int) $request->input('mes');

            if (
                $anoReq < $anoAtual ||
                ($anoReq == $anoAtual && $mesReq < $mesAtual)
            ) {
                return redirect()
                    ->back()
                    ->with(
                        'error',
                        'Operação negada: Não é permitido alterar metas de meses que já passaram.'
                    );
            }

            try {

                $this->metaService->salvarMetasEmLote(
                    $request->input('metas'),
                    $anoReq,
                    $mesReq
                );

                return redirect()
                    ->route(
                        'metas.index',
                        $request->only('ano', 'mes', 'codfilrh', 'codsup')
                    )
                    ->with(
                        'success',
                        'Metas processadas e salvas com sucesso!'
                    );

            } catch (\Exception $e) {

                return redirect()
                    ->back()
                    ->with(
                        'error',
                        'Falha ao salvar metas: ' . $e->getMessage()
                    );
            }
        }
}