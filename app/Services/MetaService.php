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
    public function gerarMetasSugeridas(int $ano, int $mes, ?int $codfil = null): void
    {

        $anoAtual = (int) date('Y');
        $mesAtual = (int) date('m');

        // Calcula qual é o próximo mês/ano real
        $mesSeguinte = $mesAtual === 12 ? 1 : $mesAtual + 1;
        $anoSeguinte = $mesAtual === 12 ? $anoAtual + 1 : $anoAtual;

        $isMesAtual = ($ano === $anoAtual && $mes === $mesAtual);
        $isMesSeguinte = ($ano === $anoSeguinte && $mes === $mesSeguinte);

        // Se NÃO for o mês atual E NÃO for o mês seguinte, não popula a tabela!
        if (!$isMesAtual && !$isMesSeguinte) {
            Log::info("Geração de metas ignorada para $mes/$ano: Fora da janela permitida (apenas mês atual ou seguinte).");
            return; // Sai da função sem rodar a procedure e sem gravar nada!
        }


        // 1. Instância do PDO 
        $pdo = DB::connection('sqlsrv_desenvolvimento')->getPdo();
        
        $stmt = $pdo->prepare("EXEC SPU_AMO_META_SUGERIDO ?, ?, NULL");
        $stmt->execute([$ano, $mes]);
        
        while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
        }

        $dados = [];
        if ($stmt->columnCount() > 0) {
            $dados = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }

        $stmt->closeCursor();
        
        if (empty($dados)) {
            Log::info("Nenhum dado retornado pela procedure para o ano $ano e mês $mes.");
            return; 
        }
     
        DB::connection('sqlsrv_desenvolvimento')->transaction(function () use ($dados, $ano, $mes) {
            foreach ($dados as $item) {
                $codvendr = $item->CODVENDR ?? $item->codvendr ?? null;
                $codfilrh = $item->CODFILRH ?? $item->codfilrh ?? null;
                $sugerido = $item->SUGERIDO ?? $item->sugerido ?? null;

                if ($codvendr) {
                    
                    // 1. Se for das filiais bloqueadas ou vendedores específicos (ex: E-commerce, Televendas, Departamentos), pula na hora!
                    if (in_array($codfilrh, [9, 14]) || in_array($codvendr, [896, 300])) {
                        continue; 
                    }

                    $codgerente = DB::connection('sqlsrv_gemco')
                        ->table('VEN_VEND')
                        ->where('CODVENDR', $codvendr)
                        ->whereNotNull('CODSUP')
                        ->value('CODSUP');

                    if ($codvendr == 1905) {
                        Log::info("Espião - Valor lido do Gemco: " . json_encode($codgerente));
                    }
    

                    // --- APLICAÇÃO ESTRITA DAS REGRAS DE EXCEÇÃO ---
                    
                    // Regra 1: Filial 5 (VPJ) - Se o gerente não tem CODSUP, ele responde por ele mesmo
                    if ($codfilrh == 5 && is_null($codgerente)) {
                        $codgerente = $codvendr;
                    } 
                    // Regra Geral: Se o CODSUP continuar nulo ou zerado (departamentos), ignoramos o registro
                    elseif (is_null($codgerente) || $codgerente == 0) {
                        continue; 
                    }
                    
                    // -----------------------------------------------

                 // 4. Salva ou atualiza a meta sugerida no banco
                    
                    // Em vez de usar ->exists(), nós buscamos o registro para ver se a META está vazia
                    $metaExistente = DB::connection('sqlsrv_desenvolvimento')
                        ->table('portal.dbo.AMO_META')
                        ->where('CODVENDR', $codvendr)
                        ->where('ANO', $ano)
                        ->where('MES', $mes)
                        ->first();

                    if (!$metaExistente) {
                        // PRIMEIRA VEZ: Insere a meta nova. A 'META' e a 'SUGESTAO' recebem o mesmo valor.
                        DB::connection('sqlsrv_desenvolvimento')->table('portal.dbo.AMO_META')->insert([
                            'CODVENDR'   => $codvendr,
                            'ANO'        => $ano,
                            'MES'        => $mes,
                            'CODFILRH'   => $codfilrh,
                            'META'       => $sugerido, 
                            'SUGESTAO'   => $sugerido, 
                            'CODGERENTE' => $codgerente, 
                            'DESCRICAO'  => 'Sugestão de meta automática',
                        ]);
                    } else {
                        // Prepara os dados básicos que sempre atualizam
                        $dadosAtualizacao = [
                            'CODFILRH'   => $codfilrh,
                            'SUGESTAO'   => $sugerido,
                            'CODGERENTE' => $codgerente, 
                        ];

                        // Se a META estiver nula no banco, nós atualizamos ela também para não travar o gerente!
                        if (is_null($metaExistente->META)) {
                            $dadosAtualizacao['META'] = $sugerido;
                        }

                        DB::connection('sqlsrv_desenvolvimento')->table('portal.dbo.AMO_META')
                            ->where('CODVENDR', $codvendr)
                            ->where('ANO', $ano)
                            ->where('MES', $mes)
                            ->update($dadosAtualizacao);
                    }
                }
            }
        });
    }
    /**
     * MÉTODO 5: SALVAR METAS
     */
    public function salvarMetasEmLote(int $ano, int $mes, array $metasDigitadas, string $usuarioLogado): void
    {
        DB::connection('sqlsrv_desenvolvimento')->transaction(function () use ($ano, $mes, $metasDigitadas, $usuarioLogado) {
            foreach ($metasDigitadas as $codvendr => $dados) {
             
                if (!isset($dados['meta'])) {
                    continue;
                }
                
                $novaMeta = (float) str_replace(['.', ','], ['', '.'], $dados['meta']);
                $motivo = !empty($dados['motivo']) ? trim($dados['motivo']) : 'Alteração via sistema';
                $codfil = isset($dados['codfil']) ? (int) $dados['codfil'] : 0;

                // Busca a meta atual antes de alterar
                $metaAnterior = DB::connection('sqlsrv_desenvolvimento')
                    ->table('portal.dbo.AMO_META')
                    ->where('ANO', $ano)
                    ->where('MES', $mes)
                    ->where('CODVENDR', $codvendr)
                    ->value('META');

                // Se a meta digitada for exatamente igual à anterior, ignora!
                if ($metaAnterior !== null && number_format((float)$metaAnterior, 2, '.', '') === number_format($novaMeta, 2, '.', '')) {
                    continue; 
                }

                $tpAlteracao = isset($dados['tp_alteracao']) && $dados['tp_alteracao'] !== '' ? $dados['tp_alteracao'] : null;
                $qtAlteracao = isset($dados['qt_alteracao']) && $dados['qt_alteracao'] !== '' ? (float) $dados['qt_alteracao'] : null;

                // 1. Grava no LOG incluindo as duas colunas novas (TPALTERACAO e QTALTERACAO)
                DB::connection('sqlsrv_desenvolvimento')->table('portal.dbo.AMO_META_LOG')->insert([
                    'ANO' => $ano,
                    'MES' => $mes,
                    'CODFILRH' => $codfil,
                    'CODVENDR' => $codvendr,
                    'META_ANTIGA' => $metaAnterior,
                    'META_NOVA' => $novaMeta,
                    'MOTIVO' => $motivo,
                    'USUARIO_ALTERACAO' => $usuarioLogado,
                    'DATA_ALTERACAO' => now(),
                    'TPALTERACAO' => $tpAlteracao, 
                    'QTALTERACAO' => $qtAlteracao 
                ]);

                // 2. Atualiza a AMO_META principal (Apenas a coluna Meta e a Descrição do motivo)
                DB::connection('sqlsrv_desenvolvimento')->table('portal.dbo.AMO_META')->updateOrInsert(
                    ['ANO' => $ano, 'MES' => $mes, 'CODVENDR' => $codvendr],
                    ['META' => $novaMeta, 'DESCRICAO' => $motivo]
                );
            }
        });
    }
}