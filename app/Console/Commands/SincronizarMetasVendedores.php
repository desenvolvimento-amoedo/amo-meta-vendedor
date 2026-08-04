<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MetaService;
use Illuminate\Support\Facades\Log;

class SincronizarMetasVendedores extends Command
{
    // O nome que usaremos para chamar esse comando
    protected $signature = 'metas:sincronizar';

    // A descrição do que ele faz
    protected $description = 'Verifica novos vendedores no GEMCO e gera as metas sugeridas automaticamente na tabela AMO_META';

    public function handle(MetaService $metaService)
    {
        $this->info('Iniciando sincronização automática de metas...');
        Log::info('CRON: Iniciando sincronização automática de metas.');

        $anoAtual = (int) date('Y');
        $mesAtual = (int) date('m');

        // Lógica do mês seguinte
        $mesSeguinte = $mesAtual === 12 ? 1 : $mesAtual + 1;
        $anoSeguinte = $mesAtual === 12 ? $anoAtual + 1 : $anoAtual;

        try {
            // Chama a mesma função que a sua tela usa, mas sem filtrar por filial (passando null).
            // A procedure vai rodar para a empresa inteira e carregar quem estiver faltando.
            
            // 1. Roda para o mês atual
            $metaService->gerarMetasSugeridas($anoAtual, $mesAtual, null, null);
            
            // 2. Roda para o mês seguinte 
            $metaService->gerarMetasSugeridas($anoSeguinte, $mesSeguinte, null, null);

            $this->info('Sincronização concluída com sucesso!');
            Log::info('CRON: Sincronização automática finalizada.');

        } catch (\Exception $e) {
            $this->error('Erro na sincronização: ' . $e->getMessage());
            Log::error('CRON Erro na sincronização de metas: ' . $e->getMessage());
        }
    }
}