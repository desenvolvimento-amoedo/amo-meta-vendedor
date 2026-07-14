<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\VendedorRepositoryInterface;
use Illuminate\Support\Facades\DB;

class VendedorRepository implements VendedorRepositoryInterface
{
    public function getFiliaisDisponiveis(bool $isAdmin, ?int $codgerente)
    {
        $query = DB::connection('sqlsrv_gemco')->table('CAD_FILIAL')
            ->select('CAD_FILIAL.CODFIL', 'CAD_FILIAL.FANTASIA')
            ->whereNotIn('CAD_FILIAL.CODFIL', [7, 9, 11, 15, 18, 19, 21, 23, 24, 25, 26, 27, 28, 29, 34, 35, 38])
            ->distinct();

        if (!$isAdmin && $codgerente) {
            $query->join('VEN_VEND', 'CAD_FILIAL.CODFIL', '=', 'VEN_VEND.CODFILRH')
                  ->where('VEN_VEND.CODVENDR', (int) $codgerente);
        }
          
        return $query->orderBy('CAD_FILIAL.CODFIL')->get();
    }

    public function getGerentesDisponiveis(bool $isAdmin, ?int $codvendr)
    {
        $query = DB::connection('sqlsrv_gemco')->table('VEN_VEND as v')
            ->join('SEG_USER as s', 'v.CODVENDR', '=', 's.CODVENDR')
            ->select(
                'v.CODVENDR as CODSUP',
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
    
    public function getVendedoresComMetas(int $ano, int $mes, ?int $codfil, ?int $codvendr)
    {
        $query = DB::connection('sqlsrv_gemco')->table('VEN_VEND as v')
            ->select( 
                'v.CODVENDR',
                'v.NOME',        
                'v.CODFILRH as CODFIL',  
                'v.CODSUP',
                'v.TPVENDR'
            )
            ->distinct()
            ->where('v.STATUS', 0)
            ->whereNotIn('v.CODFILRH', [9]); 

            // vendedores ativos
        $query->where(function ($q) {
            $q->where(function($sub) {
                $sub->where('v.TPVENDR', 4)
                    ->whereNotNull('v.CODSUP');
            })
            ->orWhere('v.CODFILRH', 5);
        });
            
        // Traz os vendedores caso seja filtrada a filial
        if ($codfil) {
            $query->where('v.CODFILRH', $codfil);
        }

        // Traz os vendedores caso seja filtrado o supervisor
        if ($codvendr) {
            $gerente = DB::connection('sqlsrv_gemco')->table('VEN_VEND')
                ->where('CODVENDR', (int) $codvendr)
                ->first(['CODFILRH', 'CODVENDR']);

            if ($gerente) {
                $query->where('v.CODFILRH', $gerente->CODFILRH)
                      ->where('v.CODVENDR', '<>', $gerente->CODVENDR); 
            }
        }

        $vendedores = $query->orderBy('v.CODVENDR')->get();

        if ($vendedores->isEmpty()) {
            return $vendedores;
        }

        // Carregar somente o código dos vendedores dentro de um array
        $codigosVendedores = $vendedores->pluck('CODVENDR')->toArray();

        // Busca as metas dos vendedores resultantes do filtro final
        $metas = DB::connection('sqlsrv_desenvolvimento')
            ->table('portal.dbo.AMO_META')
            ->where([
                ['ANO', $ano],
                ['MES', $mes]
            ])
            ->whereIn('CODVENDR', $codigosVendedores)
            ->pluck('META', 'CODVENDR') 
            ->toArray();

        foreach ($vendedores as $vendedor) {
            $vendedor->META = $metas[$vendedor->CODVENDR] ?? null;
        }
        
        return $vendedores;
    }
}