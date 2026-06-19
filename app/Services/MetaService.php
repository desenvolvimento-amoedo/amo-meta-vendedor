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

    // Adicionado o ?int $codfil para segmentar a procedure por filial
    public function gerarMetasSugeridas(int $ano, int $mes, ?int $codfil = null): void
    {
        // 1. Instância do PDO para rodar a procedure ignorando os retornos das tabelas temporárias
        $pdo = DB::connection('sqlsrv_desenvolvimento')->getPdo();
        
        // Agora passamos o terceiro parâmetro dinamicamente para o EXEC
        $stmt = $pdo->prepare("EXEC SPU_AMO_META_SUGERIDO ?, ?, ?");
        $stmt->execute([$ano, $mes, $codfil]);

        // Avança os ponteiros até encontrar o SELECT com as colunas reais do layout final
        while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
            // Ignora os alertas "X rows affected" das tabelas temporárias
        }

        $dados = $stmt->fetchAll(\PDO::FETCH_OBJ);
        
    // Avança os ponteiros até encontrar o SELECT com as colunas reais do layout final
    while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
        // Ignora os alertas "X rows affected" das tabelas temporárias
    }

    $dados = $stmt->fetchAll(\PDO::FETCH_OBJ);

    if (empty($dados)) {
        Log::info("Nenhum dado retornado pela procedure para o ano $ano e mês $mes.");
        return;
    }

        
        DB::transaction(function () use ($dados, $ano, $mes) {
            foreach ($dados as $item) {
                $codvendr = $item->CODVENDR ?? $item->codvendr ?? null;
                $codfilrh = $item->CODFILRH ?? $item->codfilrh ?? null;
                $sugerido = $item->SUGERIDO ?? $item->sugerido ?? null;

                if ($codvendr) {
                    // Busca o supervisor original no Gemco
                    $codgerente = DB::connection('sqlsrv_gemco')
                        ->table('VEN_VEND')
                        ->where('CODVENDR', $codvendr)
                        ->value('CODSUP');

                    // --- APLICAÇÃO ESTRITA DAS REGRAS DE EXCEÇÃO ---

                    // Regra 1: Filial 14 (AMOEDO.COM) - Amarrar sempre ao vendedor 896
                    if ($codfilrh == 14) {
                        $codgerente = 896;
                    } 
                    // Regra 2: Filial 5 (VPJ) - Se o gerente não tem CODSUP, ele responde por ele mesmo
                    elseif ($codfilrh == 5 && is_null($codgerente)) {
                        $codgerente = $codvendr;
                    } 
                    // Regra Geral: Se não for filial 5 ou 14 e o CODSUP continuar nulo, ignoramos o registro
                    elseif (is_null($codgerente)) {
                        Log::info("Vendedor {$codvendr} da filial {$codfilrh} ignorado por não possuir CODSUP (Regra Geral).");
                        continue; 

                
                    }
                        
                    // -----------------------------------------------

                    // 4. Salva ou atualiza a meta sugerida no banco
                    \App\Models\AMO_META::firstOrCreate(
                        [
                            'CODVENDR' => $codvendr,
                            'ANO'      => $ano,
                            'MES'      => $mes,
                        ],
                        [
                            'CODFILRH'   => $codfilrh,
                            'META'       => $sugerido,
                            'CODGERENTE' => $codgerente, 
                            'DESCRICAO'  => $motivo ?? 'Meta sugerida automaticamente',
                        ]
                    );
                }
            }
        });
    }

    /**
     * MÉTODO 5: SALVAR METAS EM LOTE COM AUDITORIA 
     */
    public function salvarMetasEmLote(int $ano, int $mes, array $metasDigitadas, string $usuarioLogado): void
    {
        foreach ($metasDigitadas as $codvendr => $dados) {
            $novaMeta = isset($dados['meta']) ? (float) $dados['meta'] : 0.00;
            $motivo = !empty($dados['motivo']) ? trim($dados['motivo']) : 'Alteração via sistema';
            $codfil = isset($dados['codfil']) ? (int) $dados['codfil'] : 0;

            // Busca a meta atual antes de alterar
            $metaAnterior = DB::connection('sqlsrv')
                ->table('estagio.dbo.AMO_META')
                ->where('ANO', $ano)
                ->where('MES', $mes)
                ->where('CODVENDR', $codvendr)
                ->value('META');

            // Se a meta digitada for exatamente igual à anterior, ignora!
            // Converte ambos para string ou float com 2 casas para evitar divergências de ponto flutuante
            if ($metaAnterior !== null && number_format((float)$metaAnterior, 2, '.', '') === number_format($novaMeta, 2, '.', '')) {
                continue; 
            }

            // Só grava no Log e atualiza o banco se o valor for realmente DIFERENTE
            DB::connection('sqlsrv')->table('estagio.dbo.AMO_META_LOG')->insert([
                'ANO' => $ano,
                'MES' => $mes,
                'CODFILRH' => $codfil,
                'CODVENDR' => $codvendr,
                'META_ANTIGA' => $metaAnterior,
                'META_NOVA' => $novaMeta,
                'MOTIVO' => $motivo,
                'USUARIO_ALTERACAO' => $usuarioLogado,
                'DATA_ALTERACAO' => now() 
            ]);

            DB::connection('sqlsrv')->table('estagio.dbo.AMO_META')->updateOrInsert(
                ['ANO' => $ano, 'MES' => $mes, 'CODVENDR' => $codvendr],
                ['META' => $novaMeta, 'DESCRICAO' => $motivo]
            );
        }
    }
}