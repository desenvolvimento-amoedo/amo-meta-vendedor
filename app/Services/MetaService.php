<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;


class MetaService
{
    // O Laravel injeta automaticamente o repositório configurado através do construtor
    public function __construct(private \App\Repositories\Contracts\VendedorRepositoryInterface $vendedorRepo) {}

    
    // Retorna os dados que vão preencher os selects de filtros da tela inicial
    
    public function obterFiltrosDeAcesso(array $userContext): array
    {
        return [
            'filiais' => $this->vendedorRepo->getFiliaisDisponiveis($userContext['is_admin'], $userContext['codsup']),
            'gerentes' => $this->vendedorRepo->getGerentesDisponiveis($userContext['is_admin'], $userContext['codsup'])
        ];
    }

    
     // Regra para listar os vendedores: Se for gerente, força o CODSUP dele ignorando fraudes de tela
     
    public function listarVendedores(array $userContext, array $filtros)
    {
        $codsup = $userContext['is_admin'] ? $filtros['codsup'] : $userContext['codsup'];
        
        return $this->vendedorRepo->getVendedoresComMetas(
            $filtros['ano'],
            $filtros['mes'],
            $filtros['codfil'],
            $codsup 
        );
    }

    
    // Salva ou atualiza as metas usando updateOrCreate 
    public function salvarMetasEmLote(array $dados, int $ano, int $mes): void
    {
        
        DB::transaction(function () use ($dados, $ano, $mes) {
            foreach ($dados as $codvendr => $valores) {

                // Converte para o formato de moeda BR (Ex: 1.500,50 para 1500.50) se necessário
                $metaFormatada = isset($valores['meta']) ? str_replace(['.', ','], ['', '.'], $valores['meta']) : null;

                \App\Models\AMO_META::updateOrCreate(
                    [
                        'CODVENDR' => $codvendr,
                        'ANO' => $ano,
                        'MES' => $mes,
                    ],
                    [
                        'CODFILRH' => $valores['codfil'],
                        'META' => $metaFormatada ? (float) $metaFormatada : null, // Converte para float ou seta null se vazio
                        'CODGERENTE' => $valores['codgerente'],
                    ]
                );
            }
        });
    }
}

