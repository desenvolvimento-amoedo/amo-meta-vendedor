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
        if (!$userContext['is_admin'] && empty($userContext['codsup'])) {
            abort(403, 'Acesso negado: Caso precise, entrar em contato com o TI.');
        }

        $codsup = $userContext['is_admin'] ? ($filtros['codsup'] ?? null) : $userContext['codsup'];

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

                    $codgerente = DB::connection('sqlsrv_gemco')
                        ->table('VEN_VEND')
                        ->where('CODVENDR', $codvendr)
                        ->value('CODSUP');

                    // --- APLICAÇÃO DAS REGRAS DE EXCEÇÃO ---

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
                            'DESCRICAO'  => $motivo ?? 'Sugestão de meta automática',
                        ]
                    );
                }
            }
        });
    }

    /**
     * MÉTODO 5: SALVAR METAS
     */
    public function salvarMetasEmLote(int $ano, int $mes, array $metasDigitadas, array $userContext): void
    {
        // Pega a lista de vendedores que o usuário tem permissão para editar
        $vendedoresPermitidos = [];
        
        if (!$userContext['is_admin']) {
            $vendedoresPermitidos = $this->vendedorRepo->getVendedoresComMetas($ano, $mes, null, $userContext['codsup'])
                ->pluck('CODVENDR')
                ->toArray();
        }

        DB::connection('sqlsrv_desenvolvimento')->transaction(function () use ($ano, $mes, $metasDigitadas, $userContext, $vendedoresPermitidos) {
            foreach ($metasDigitadas as $codvendr => $dados) {
              
                // --- TRAVA DE SEGURANÇA ---
                // Se não for admin e o vendedor não estiver na lista permitida, barra a alteração
                if (!$userContext['is_admin'] && !in_array($codvendr, $vendedoresPermitidos)) {
                    Log::warning("Tentativa de alteração de meta negada. Usuário: {$userContext['username']} | Vendedor Alvo: {$codvendr}");
                    continue; // Pula para o próximo sem salvar

                }

                $novaMeta = isset($dados['meta']) ? (float) str_replace(['.', ','], ['', '.'], $dados['meta']) : 0.00;
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

                
               // Só grava no Log e atualiza o banco se o valor for realmente DIFERENTE

                // 1. Primeiro, descobre quem é o gerente
                $codgerente = DB::connection('sqlsrv_gemco')
                    ->table('VEN_VEND')
                    ->where('CODVENDR', $codvendr)
                    ->value('CODSUP');

                // 2. Aplica as regras de exceção 
                if ($codfil == 14) {
                    $codgerente = 896;
                } elseif ($codfil == 5 && is_null($codgerente)) {
                    $codgerente = $codvendr;
                }

                // 3. Salva passando todas as colunas obrigatórias na tabela principal
                DB::connection('sqlsrv_desenvolvimento')->table('portal.dbo.AMO_META')->updateOrInsert(
                    [
                        'ANO' => $ano, 
                        'MES' => $mes, 
                        'CODVENDR' => $codvendr
                    ],
                    [
                        'META' => $novaMeta, 
                        'DESCRICAO' => $motivo,
                        'CODFILRH' => $codfil,
                        'CODGERENTE' => $codgerente ?? 0
                    ]
                );

                // 4. Grava o Log de Alteração
                // Verifica se existia uma meta anterior para caracterizar como uma alteração
                if ($metaAnterior !== null) {
                    DB::connection('sqlsrv_desenvolvimento')->table('portal.dbo.AMO_META_LOG')->insert([
                        'ANO'               => $ano,
                        'MES'               => $mes,
                        'CODFILRH'          => $codfil,
                        'CODVENDR'          => $codvendr,
                        'META_ANTIGA'       => $metaAnterior,
                        'MOTIVO'            => $motivo,
                        'USUARIO_ALTERACAO' => $userContext['username'],
                        'DATA_ALTERACAO'    => now(),
                        'META_NOVA'         => $novaMeta
                    ]);
                }
            }
        });
    }
}
