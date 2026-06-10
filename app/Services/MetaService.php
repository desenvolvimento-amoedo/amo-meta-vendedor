<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaService
{
    public function __construct(private \App\Repositories\Contracts\VendedorRepositoryInterface $vendedorRepo) {}

    // Retorna os dados que vão preencher os selects de filtros da tela inicial
    public function obterFiltrosDeAcesso(array $userContext): array
    {
        return [
            'filiais' => $this->vendedorRepo->getFiliaisDisponiveis($userContext['is_admin'], $userContext['codsup']),
            'gerentes' => $this->vendedorRepo->getGerentesDisponiveis($userContext['is_admin'], $userContext['codsup']),
        ];
    }

    public function obterFilialDoGerente(int $codsup): ?int
    {
        return DB::connection('sqlsrv_gemco')
            ->table('VEN_VEND')
            ->where('CODVENDR', $codsup)
            ->value('CODFILRH');
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

    // Gera as metas sugeridas para um determinado mês/ano/filial, usando a procedure armazenada
   // Gera as metas sugeridas para um determinado mês/ano/filial, usando a procedure armazenada
// Gera as metas sugeridas para um determinado mês/ano, usando a procedure armazenada
public function gerarMetasSugeridas(int $ano, int $mes): void
{
    // 1. Instância do PDO para rodar a procedure ignorando os retornos das tabelas temporárias
    $pdo = DB::connection('sqlsrv_desenvolvimento')->getPdo();
    $stmt = $pdo->prepare("EXEC SPU_AMO_META_SUGERIDO ?, ?, NULL");
    $stmt->execute([$ano, $mes]);

    // Avança os ponteiros até encontrar o SELECT com as colunas reais do layout final
    while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
        // Ignora os alertas "X rows affected" das tabelas temporárias
    }

    $dados = $stmt->fetchAll(\PDO::FETCH_OBJ);

    if (empty($dados)) {
        Log::info("Nenhum dado retornado pela procedure para o ano $ano e mês $mes.");
        return;
    }

    // 2. Agrupamos a execução em uma única Transação para máxima performance
    DB::transaction(function () use ($dados, $ano, $mes) {
        foreach ($dados as $item) {
            $codvendr = $item->CODVENDR ?? $item->codvendr ?? null;
            $codfilrh = $item->CODFILRH ?? $item->codfilrh ?? null;
            $sugerido = $item->SUGERIDO ?? $item->sugerido ?? null;

            if ($codvendr) {
                // 3. BUSCA O GERENTE (CODSUP) DIRETAMENTE DA TABELA DE VENDEDORES (IGUAL AO SEU REPOSITORY)
                $codgerente = DB::connection('sqlsrv_gemco')
                    ->table('VEN_VEND')
                    ->where('CODVENDR', $codvendr)
                    ->value('CODSUP');

                // Se o vendedor for o "topo" e não tiver supervisor (CODSUP nulo), 
                // definimos como 0 ou outro ID padrão para o banco aceitar o NOT NULL
                $codgerente = $codgerente ?? 0;

                // 4. Salva ou atualiza os registros sem violar a constraint do banco
                \App\Models\AMO_META::firstOrCreate(
                    [
                        'CODVENDR' => $codvendr,
                        'ANO'      => $ano,
                        'MES'      => $mes,
                    ],
                    [
                        'CODFILRH'   => $codfilrh,
                        'META'       => $sugerido,
                        'CODGERENTE' => $codgerente, // Coluna obrigatória preenchida com sucesso!
                        'DESCRICAO'  => 'Meta sugerida automaticamente',
                    ]
                );
            }
        }
    });
}

    // Salva ou atualiza as metas usando updateOrCreate
    public function salvarMetasEmLote(array $dados, int $ano, int $mes): void
    {
        DB::transaction(function () use ($dados, $ano, $mes) {
            foreach ($dados as $codvendr => $valores) {
                $metaFormatada = isset($valores['meta'])
                    ? str_replace(['.', ','], ['', '.'], $valores['meta'])
                    : null;

                \App\Models\AMO_META::updateOrCreate(
                    [
                        'CODVENDR' => $codvendr,
                        'ANO' => $ano,
                        'MES' => $mes,
                    ],
                    [
                        'CODFILRH' => $valores['codfil'],
                        'META' => $metaFormatada ? (float) $metaFormatada : null,
                        'CODGERENTE' => $valores['codgerente'] ?? null,
                        'DESCRICAO' => $valores['motivo'] ?? null,
                    ]
                );
            }
        });
    }
}
