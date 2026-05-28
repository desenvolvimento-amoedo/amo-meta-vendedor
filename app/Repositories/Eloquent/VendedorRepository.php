<?php

namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use App\Repositories\Contracts\VendedorRepositoryInterface;

class VendedorRepository implements VendedorRepositoryInterface
{
    
     // Busca as filiais. Se for gerente, traz apenas as filiais onde ele tem vendedores vinculados.
     
    public function getFiliaisDisponiveis(bool $isAdmin, ?int $codsup)
    {
        $query = DB::table('CAD_FILIAL')
            ->join('VEN_VEND', 'CAD_FILIAL.CODFIL', '=', 'VEN_VEND.CODFIL')
            ->select('CAD_FILIAL.CODFIL', 'CAD_FILIAL.FANTASIA')
            ->distinct();

        if (!$isAdmin && $codsup) {
            $query->where('VEN_VEND.CODSUP', $codsup);
        }

        return $query->orderBy('CAD_FILIAL.FANTASIA')->get();
    }

    
    // Busca os gerentes (CODSUP). Se for gerente, retorna apenas ele mesmo.
    public function getGerentesDisponiveis(bool $isAdmin, ?int $codsup)
    {
        $query = DB::table('VEN_VEND')
            ->select('CODSUP')
            ->whereNotNull('CODSUP')
            ->distinct();

        if (!$isAdmin && $codsup) {
            $query->where('CODSUP', $codsup);
        }

        return $query->orderBy('CODSUP')->get();
    }

    
     // Traz os vendedores aplicando os filtros de tela e trazendo junto a meta (se houver).
     
    public function getVendedoresComMetas(int $ano, int $mes, ?int $codfil, ?int $codsup)
    {
        $query = DB::table('VEN_VEND')
            ->leftJoin('AMO_META', function ($join) use ($ano, $mes) {
                $join->on('VEN_VEND.CODVENDR', '=', 'AMO_META.CODVENDR')
                     ->where('AMO_META.ANO', '=', $ano)
                     ->where('AMO_META.MES', '=', $mes);
            })
            ->select(
                'VEN_VEND.CODVENDR', 
                'VEN_VEND.NOME', 
                'VEN_VEND.CODFIL', 
                'VEN_VEND.CODSUP', 
                'AMO_META.META'
            );

        if ($codfil) {
            $query->where('VEN_VEND.CODFIL', $codfil);
        }

        if ($codsup) {
            $query->where('VEN_VEND.CODSUP', $codsup);
        }

        return $query->orderBy('VEN_VEND.NOME')->get();
    }
}