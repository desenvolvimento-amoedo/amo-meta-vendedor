<?php

namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use App\Repositories\Contracts\VendedorRepositoryInterface;

class VendedorRepository implements VendedorRepositoryInterface
{
    /**
     * Busca as filiais disponíveis no Gemco.
     */
    public function getFiliaisDisponiveis(bool $isAdmin, ?int $codgerente)
    {
        $query = DB::connection('sqlsrv_gemco')->table('CAD_FILIAL')
            ->select('CAD_FILIAL.CODFIL', 'CAD_FILIAL.FANTASIA')
            ->distinct();

        if (!$isAdmin && $codgerente) {
            $query->join('VEN_VEND', 'CAD_FILIAL.CODFIL', '=', 'VEN_VEND.CODFILRH')
                  ->where('VEN_VEND.CODVENDR', (int) $codgerente);
        }
          
        return $query->orderBy('CAD_FILIAL.FANTASIA')->get();
    }

    /**
     * Busca os gerentes 
     */
    public function getGerentesDisponiveis(bool $isAdmin, ?int $codvendr)
    {
        $query = DB::connection('sqlsrv_gemco')->table('VEN_VEND as v')
            ->join('SEG_USER as s', 'v.CODVENDR', '=', 's.CODVENDR')
            ->select(
                'v.CODVENDR as CODSUP', // Apelidado para a View encontrar como $gerente->CODSUP
                'v.NOME as NOME',       
                'v.CODFILRH as CODFIL'  
            )
            ->distinct()
            ->whereNull('v.CODSUP')
            ->where('v.STATUS', '<>', 9)
            ->whereNotIn('v.CODFILRH', [9, 14])
            ->where('s.CODSETOR', 105);

        if (!$isAdmin && $codvendr) {
            $query->where('v.CODVENDR', (int) $codvendr);
        }

        return $query->orderBy('v.NOME')->get();
    }
    
    /**
     * Busca os vendedores baseando-se na Filial de RH do gerente selecionado.
     */
    public function getVendedoresComMetas(int $ano, int $mes, ?int $codfil, ?int $codvendr)
    {
        // 1. Busca os vendedores aplicando estritamente as regras da query pura
        $query = DB::connection('sqlsrv_gemco')->table('VEN_VEND as v')
            ->select( 
                'v.CODVENDR',
                'v.NOME as NOME',        
                'v.CODFILRH as CODFIL',  
                'v.CODSUP'
            )
            ->distinct()
            ->whereNotNull('v.CODSUP')
            ->where('v.STATUS', '<>', 9)
            ->where('v.TPVENDR', 4) // Mantém o filtro para isolar os ATD
            ->whereNotIn('v.CODFILRH', [9, 14]);
            
        // Se o usuário selecionou uma Filial diretamente no combo de filiais
        if ($codfil) {
            $query->where('v.CODFILRH', $codfil);
        }

        // Se o usuário selecionar um Gerente, descobrimos a Filial de RH desse gerente
        // e filtramos os vendedores que pertencem a ela.
        if ($codvendr) {
            $filialDoGerente = DB::connection('sqlsrv_gemco')->table('VEN_VEND')
                ->where('CODVENDR', (int) $codvendr)
                ->value('CODFILRH');

            if ($filialDoGerente) {
                $query->where('v.CODFILRH', $filialDoGerente);
            }
        }

        // Executa a busca dos vendedores com os filtros aplicados
        $vendedores = $query->orderBy('v.CODVENDR')->get();

        if ($vendedores->isEmpty()) {
            return $vendedores;
        }

        // 2. Extrai os IDs dos vendedores para buscar as metas
        $codigosVendedores = $vendedores->pluck('CODVENDR')->toArray();

        // 3. Busca as metas usando a conexão do seu .env (sqlsrv)
        $metas = DB::connection('sqlsrv')->table('AMO_META')
            ->where('ANO', $ano)
            ->where('MES', $mes)
            ->whereIn('CODVENDR', $codigosVendedores)
            ->pluck('META', 'CODVENDR') 
            ->toArray();

        // 4. Une os dados manualmente antes de enviar para a View
        foreach ($vendedores as $vendedor) {
            $vendedor->META = $metas[$vendedor->CODVENDR] ?? null;
        }

        return $vendedores;
    }
}