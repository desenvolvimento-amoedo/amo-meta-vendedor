{{-- resources/views/metas/index.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metas de Vendedores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/metas.css') }}" rel="stylesheet">
</head> 

<body class="bg-light">

   <nav class="navbar navbar-custom">
    <div class="container">

        <a class="brand-custom d-flex align-items-center"
           href="{{ route('metas.index', ['usuario' => $usuario]) }}">

            <img src="{{ asset('images/logo_fundo_removido.png') }}"
                 alt="Logo Amoedo"
                 class="brand-logo">

            Gestão de Metas dos Vendedores
        </a>

        <span class="user-status">
            <!-- Usuário Conectado: <strong>{{ $userContext['username'] ?? 'Usuário' }}</strong> -->
            Usuário Conectado: <strong>{{ $usuario }}</strong>
            <span class="badge badge-role">
            </span>
        </span>

    </div>
</nav>

   <div class="container mb-5">

        {{-- Bloco de Alertas --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <strong>Sucesso!</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif 

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <strong>Atenção!</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif


        {{-- Card de Filtros --}}
        <div class="card card-custom mb-4">
            <div class="card-header card-header-filters">Filtros de Seleção</div>
            <div class="card-body">
                <form method="GET" action="{{ route('metas.index', ['usuario' => $usuario]) }}" id="form-filtros">
                    <div class="row g-3">

                        <div class="col-md-2">
                            <label class="form-label fw-bold">Ano</label>
                            <select name="ano" class="form-select" onchange="bloquearEFiltrar()">
                                @for($i = date('Y') - 1; $i <= date('Y') + 1; $i++)
                                    <option value="{{ $i }}" {{ ($filtros['ano'] ?? date('Y')) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold">Mês</label>
                            <select name="mes" class="form-select" onchange="bloquearEFiltrar()">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ ($filtros['mes'] ?? date('m')) == $m ? 'selected' : '' }}>
                                        {{ str_pad($m, 2, '0', STR_PAD_LEFT) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        
                       <div class="col-md-4">
                            <label class="form-label fw-bold">Filial</label>
                            
                            @if(isset($restritoASuaFilial) && $restritoASuaFilial)
                                <!-- Bloqueado para gerentes restritos -->
                                <input type="text" class="form-control bg-light" value="Acesso restrito a gestão de vendedores da sua equipe" disabled>
                                <input type="hidden" name="codfil" value="{{ $filtros['codfil'] ?? '' }}">
                            @else
                                <!-- Aberto para Admins -->
                                <select name="codfil" class="form-select" onchange="bloquearEFiltrar()">
                                    <option value="">Selecione a Filial</option>
                                    @foreach($listas['filiais'] as $filial)
                                    <option value="{{ $filial->CODFIL }}" {{ ($filtros['codfil'] ?? '') == $filial->CODFIL ? 'selected' : '' }}>
                                        {{ $filial->CODFIL }} - {{ $filial->FANTASIA }}
                                    </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Gerente</label>
                            @if($userContext['is_admin'] ?? false)
                            <select name="codsup" class="form-select" onchange="bloquearEFiltrar()">
                                <option value="">Todos os Gerentes</option>
                                @foreach(($listas['gerentes'] ?? []) as $gerente)
                                    @php
                                        $codigoGerente = $gerente->CODSUP ?? $gerente->codsup ?? $gerente->CODGERENTE ?? $gerente->id ?? null;
                                    @endphp
                                    @if($codigoGerente)
                                        <option value="{{ $codigoGerente }}" {{ ($filtros['codsup'] ?? '') == $codigoGerente ? 'selected' : '' }}>
                                            {{ $gerente->NOME ?? $gerente->NOME_GERENTE ?? $gerente->NOMESUP ?? 'Gerente ('.$codigoGerente.')' }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @else
                            <input type="text" class="form-control bg-light" value="Supervisão: {{ $userContext['codsup'] ?? '' }}" disabled>
                            <input type="hidden" name="codsup" value="{{ $userContext['codsup'] ?? '' }}">
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Listagem de Resultados --}}
        @if(!empty($vendedores) && (is_array($vendedores) || is_object($vendedores)) && count($vendedores) > 0)
        <div class="card card-custom">
            <div class="card-header card-header-results d-flex justify-content-between align-items-center">
                <span>Vendedores Encontrados</span>
                <span class="badge bg-light text-primary">Total: {{ count($vendedores) }}</span>
            </div>

          <form method="POST" action="{{ route('metas.store') }}" id="form-salvar-metas">
            @csrf

            <input type="hidden" name="usuario" value="{{ $usuario }}">
            <input type="hidden" name="ano" value="{{ $filtros['ano'] ?? date('Y') }}">
            <input type="hidden" name="mes" value="{{ $filtros['mes'] ?? date('m') }}">
            <input type="hidden" name="codfil" value="{{ $filtros['codfil'] ?? '' }}">
            <input type="hidden" name="codsup" value="{{ $filtros['codsup'] ?? '' }}">

                    @php
                    $anoAtual = (int) date('Y');
                    $mesAtual = (int) date('m');
                    
                    // Lógica para descobrir qual é o mês e o ano seguintes
                    $mesSeguinte = $mesAtual === 12 ? 1 : $mesAtual + 1;
                    $anoSeguinte = $mesAtual === 12 ? $anoAtual + 1 : $anoAtual;

                    $anoFiltro = (int) ($filtros['ano'] ?? $anoAtual);
                    $mesFiltro = (int) ($filtros['mes'] ?? $mesAtual);
                    
                    // Verifica se a data filtrada é exatamente o mês atual ou o mês seguinte
                    $isMesAtual = ($anoFiltro === $anoAtual && $mesFiltro === $mesAtual);
                    $isMesSeguinte = ($anoFiltro === $anoSeguinte && $mesFiltro === $mesSeguinte);

                    // Se NÃO for o mês atual E NÃO for o mês seguinte, bloqueia a edição
                    $bloquearEdicao = !($isMesAtual || $isMesSeguinte);
                    @endphp


                     <div class="table-responsive">
                       <table class="table table-striped table-hover mb-1 align-middle">
                        <thead class="table-header-custom">
                            <tr>
                                <th style="width: 16%;">Vendedor</th>
                                <th style="width: 13%;">Meta Sugerida (R$)</th>
                                <th style="width: 15%;">Tipo de Alteração</th>
                                <th style="width: 11%;">Ajuste</th>
                                <th style="width: 13%;">Meta Definitiva (R$)</th>
                                <th style="width: 15%;">Motivo da Alteração</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($vendedores ?? []) as $vendedor)
                            <tr>
                               <td>
                          <span class="text-lg badge bg-black text-white ">{{ $vendedor->NOME }}</span> <br> 
                            <span class="badge bg-secondary mt-1">Codvendr: {{ $vendedor->CODVENDR }}</span> <br>
                            <span class="badge bg-secondary mt-1">Filial: {{ $vendedor->CODFIL }}</span>
                        </td>
                                
                                 {{-- 1. META SUGERIDA (AGORA FIXA) --}}
                                <td class="text-nowrap fw-bold">
                             
                                    R$ {{ number_format($vendedor->SUGESTAO ?? $vendedor->META, 2, ',', '.') }}
                                    
                                    <input type="hidden" name="metas[{{ $vendedor->CODVENDR }}][codfil]" value="{{ $vendedor->CODFIL }}">
                                    
                                    <input type="hidden" id="metaOriginal_{{ $vendedor->CODVENDR }}" value="{{ $vendedor->SUGESTAO ?? $vendedor->META }}">

                                    <input type="hidden" id="metaAtual_{{ $vendedor->CODVENDR }}" value="{{ $vendedor->META }}">

                                    <input type="hidden" name="metas[{{ $vendedor->CODVENDR }}][tp_alteracao]" id="tpAlt_{{ $vendedor->CODVENDR }}" disabled>

                                    <input type="hidden" name="metas[{{ $vendedor->CODVENDR }}][qt_alteracao]" id="qtAlt_{{ $vendedor->CODVENDR }}" disabled>

                                </td>
                               {{-- 2. CONFIGURAÇÃO DOS SWITCHES --}}
                                <td>
                                  <!--  <div class="d-flex flex-column">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input switch-comum" type="checkbox" role="switch"
                                                id="switchComum_{{ $vendedor->CODVENDR }}" data-vendedor="{{ $vendedor->CODVENDR }}" {{ $bloquearEdicao ? 'disabled' : '' }}>
                                            <label class="form-check-label text-nowrap" for="switchComum_{{ $vendedor->CODVENDR }}">Alteração Comum</label>
                                        </div>
                                        !-->
                                        <div class="form-check form-switch mb-2 fw-bold">
                                            <input class="form-check-input switch-dias" type="checkbox" role="switch"
                                                id="switchDias_{{ $vendedor->CODVENDR }}" data-vendedor="{{ $vendedor->CODVENDR }}" {{ $bloquearEdicao ? 'disabled' : '' }}>
                                            <label class="form-check-label text-nowrap" for="switchDias_{{ $vendedor->CODVENDR }}">Reduzir Dias</label>
                                        </div>
                                        <div class="form-check form-switch fw-bold">
                                            <input class="form-check-input switch-perc" type="checkbox" role="switch"
                                                id="switchPerc_{{ $vendedor->CODVENDR }}" data-vendedor="{{ $vendedor->CODVENDR }}" {{ $bloquearEdicao ? 'disabled' : '' }}>
                                            <label class="form-check-label text-nowrap" for="switchPerc_{{ $vendedor->CODVENDR }}">Aumento (%)</label>
                                        </div>
                                    </div>
                                </td>

                                {{-- 3. COLUNA DE PARÂMETROS (Abre dependendo do Switch) --}}
                                <td>
                                    <div id="colComum_{{ $vendedor->CODVENDR }}" class="d-none">
                                        <span class="badge bg-dark w-100 p-3 text-wrap fs-7">Digite a meta ao lado &#8594;</span>
                                    </div>

                                    <div id="colDias_{{ $vendedor->CODVENDR }}" class="d-none">
                                        <select class="form-select select-dias" id="selectDias_{{ $vendedor->CODVENDR }}" data-vendedor="{{ $vendedor->CODVENDR }}">
                                            <option value="">Dias...</option>
                                            @for($d = 1; $d <= 30; $d++)
                                                <option value="{{ $d }}">{{ $d }}</option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div id="colPerc_{{ $vendedor->CODVENDR }}" class="d-none">
                                        <div class="input-group">
                                        <input type="text" 
                                            class="form-control input-perc" 
                                            id="inputPerc_{{ $vendedor->CODVENDR }}" 
                                            data-vendedor="{{ $vendedor->CODVENDR }}" 
                                            placeholder="Até 30" 
                                            oninput="this.value = this.value.replace('.', ',').replace(/[^0-9,]/g, '')">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    </div>
                                </td>

                                 {{-- 4. NOVA META --}}
                                    <td>
                                        <div class="input-group fw-bold">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" 
                                                name="metas[{{ $vendedor->CODVENDR }}][meta]"
                                                class="form-control input-meta"
                                                id="metaCalculada_{{ $vendedor->CODVENDR }}"
                                                value="{{ number_format($vendedor->META, 2, ',', '.') }}" 
                                                readonly>
                                        </div>
                                        <small class="text-muted" style="font-size: 0.75rem;">Valor a ser salvo</small>
                                    </td>
                                
                                {{-- 5. MOTIVO --}}
                                <td>
                                    <input type="text" 
                                        class="form-control input-motivo" 
                                        id="motivo_{{ $vendedor->CODVENDR }}"
                                        name="metas[{{ $vendedor->CODVENDR }}][motivo]" 
                                        value="{{ $vendedor->MOTIVO ?? '' }}" 
                                        placeholder="Mín de 5 caracteres"
                                        minlength="5"
                                        disabled> 
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>

                <div class="footer-actions p-3 text-end">
                        @if(!$bloquearEdicao)
                            <button type="submit" class="btn btn-success btn-action">
                                Salvar Alterações
                            </button>
                        @else
                            <button type="button" class="btn btn-secondary btn-action" disabled>
                                {{ ($anoFiltro > $anoAtual || ($anoFiltro === $anoAtual && $mesFiltro > $mesAtual)) ? 'Mês Fechado' : 'Mês Anterior' }}
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        @else
        <div class="alert alert-info shadow-sm border-0" role="alert">
            <strong>Instrução:</strong> @if($userContext['is_admin'] ?? false) Selecione uma <strong>Filial</strong> ou um <strong>Gerente</strong> nos filtros acima para listar os vendedores e gerenciar as metas. @else Selecione uma <strong>Filial</strong> para filtrar os seus vendedores sob sua supervisão. @endif
        </div>
        @endif

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
      {{-- JAVASCRIPT EXCLUSIVO DA TELA PARA CONTROLAR OS TRAVAMENTOS E VALIDAÇÃO --}}

        <script>
                    // Função para bloquear a tela e enviar o formulário de filtros
            window.bloquearEFiltrar = function() {
                const form = document.getElementById('form-filtros');
                
                // 1. Desativa os selects visualmente e impede novos cliques
                form.querySelectorAll('select').forEach(function(select) {
                    select.style.pointerEvents = 'none'; // Bloqueia o clique do mouse
                    select.style.opacity = '0.6';        // Deixa "apagado"
                });

                // 2. Muda o texto do cabeçalho do card para avisar o usuário
                const tituloFiltro = document.querySelector('.card-header-filters');
                if (tituloFiltro) {
                    tituloFiltro.innerHTML = 'Filtros de Seleção <span class="badge bg-warning text-dark ms-3">Processando... Aguarde!</span>';
                }

                // 3. Muda o cursor do mouse para a "bolinha rodando" de carregamento
                document.body.style.cursor = 'wait';

                // 4. Dispara a submissão do formulário de forma segura
                form.submit();
            };
           document.addEventListener('DOMContentLoaded', function () {
                const minimoCaracteres = 5;

                function formatarMoeda(valor) {
                    return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function recalcularMeta(codvendr) {
                    const metaOriginal = parseFloat(document.getElementById('metaOriginal_' + codvendr).value);
                    const metaAtual = parseFloat(document.getElementById('metaAtual_' + codvendr).value);
                    const inputMetaCalculada = document.getElementById('metaCalculada_' + codvendr);
                    
                    const isDias = document.getElementById('switchDias_' + codvendr).checked;
                    const isPerc = document.getElementById('switchPerc_' + codvendr).checked;

                    // INICIA COM A META ATUAL (A que já estava salva no banco)
                    let novaMeta = metaAtual;

                    if (isDias) {
                        const selectDias = document.getElementById('selectDias_' + codvendr);
                        // Só faz o cálculo se tiver um valor escolhido no dropdown
                        if (selectDias.value !== "") {
                            const dias = parseInt(selectDias.value) || 0;
                            novaMeta = metaOriginal - ((metaOriginal / 30) * dias); 
                        }
                    } 
                    else if (isPerc) {
                            const inputPerc = document.getElementById('inputPerc_' + codvendr);
                            // Só faz o cálculo se tiver um valor digitado
                            if (inputPerc.value !== "") {
                                // Troca a vírgula por ponto para o JavaScript conseguir fazer o calculo corretamente
                                const valorTratado = inputPerc.value.replace(',', '.');
                                const perc = parseFloat(valorTratado) || 0;
                                const percValido = perc > 30 ? 30 : perc; 
                                
                                if(perc > 30) inputPerc.value = "30"; 
                                
                                novaMeta = metaOriginal + (metaOriginal * (percValido / 100));
                            }
                        }
                    inputMetaCalculada.value = formatarMoeda(novaMeta);
                }

                 function gerenciarSwitches(codvendr, switchAtivado) {

                    const tipos = ['Dias', 'Perc'];
                    const inputMotivo = document.getElementById('motivo_' + codvendr);
                    const inputMeta = document.getElementById('metaCalculada_' + codvendr);
                    const metaAtual = parseFloat(document.getElementById('metaAtual_' + codvendr).value);

                    let algumAtivo = false;

                    tipos.forEach(tipo => {
                        const chk = document.getElementById(`switch${tipo}_${codvendr}`);
                        const div = document.getElementById(`col${tipo}_${codvendr}`);
                        
                        if (tipo === switchAtivado && chk.checked) {
                            div.classList.remove('d-none');
                            algumAtivo = true;

                          //  if (tipo === 'Comum') {
                               // inputMeta.removeAttribute('readonly'); 
                               // inputMeta.focus();
                            //} else {
                                //inputMeta.setAttribute('readonly', 'readonly'); 
                                recalcularMeta(codvendr); 
                           // }
                        } else {
                            chk.checked = false;
                            div.classList.add('d-none');
                            
                            if (tipo === 'Dias') document.getElementById('selectDias_' + codvendr).value = '';
                            if (tipo === 'Perc') document.getElementById('inputPerc_' + codvendr).value = '';
                        }
                    });

                    if (algumAtivo) {
                        inputMotivo.removeAttribute('disabled');
                    } else {
                        inputMotivo.setAttribute('disabled', 'disabled');
                        inputMotivo.classList.remove('is-invalid');
                        inputMotivo.value = '';
                        inputMeta.setAttribute('readonly', 'readonly');
                        
                        // DEVOLVE PARA A META ATUAL SE DESLIGAR AS CHAVES
                        inputMeta.value = formatarMoeda(metaAtual); 
                    }
                }

                // Atribui os eventos de clique
             //   document.querySelectorAll('.switch-comum').forEach(t => t.addEventListener('change', function() { gerenciarSwitches(this.getAttribute('data-vendedor'), 'Comum'); }));
                document.querySelectorAll('.switch-dias').forEach(t => t.addEventListener('change', function() { gerenciarSwitches(this.getAttribute('data-vendedor'), 'Dias'); }));
                document.querySelectorAll('.switch-perc').forEach(t => t.addEventListener('change', function() { gerenciarSwitches(this.getAttribute('data-vendedor'), 'Perc'); }));

                // Refaz cálculos
                document.querySelectorAll('.select-dias, .input-perc').forEach(input => {
                    input.addEventListener('input', function() { recalcularMeta(this.getAttribute('data-vendedor')); });
                });

                // Validação de motivo
                document.querySelectorAll('.input-motivo').forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value.trim().length >= minimoCaracteres) this.classList.remove('is-invalid');
                        else this.classList.add('is-invalid');
                    });
                });

               // Submissão do Form
            const formSalvar = document.querySelector('form[action="{{ route("metas.store") }}"]');
            if (formSalvar) {
                formSalvar.addEventListener('submit', function (event) {
                    let temErro = false;
                    let msgErro = '';
                    let campoFocar = null;

            // Lista apenas quem teve o switch ativado
            const vendedoresAlterados = [];
            document.querySelectorAll('.switch-comum:checked, .switch-dias:checked, .switch-perc:checked').forEach(el => {
                const cod = el.getAttribute('data-vendedor');
                if(!vendedoresAlterados.includes(cod)) vendedoresAlterados.push(cod);
            });

            // Validações
            vendedoresAlterados.forEach(codvendr => {
              // const isComum = document.getElementById('switchComum_' + codvendr).checked;
                const isDias = document.getElementById('switchDias_' + codvendr).checked;
                const isPerc = document.getElementById('switchPerc_' + codvendr).checked;
                const inputMotivo = document.getElementById('motivo_' + codvendr);
                
                if (isDias && document.getElementById('selectDias_' + codvendr).value === '') {
                    temErro = true; msgErro = "Selecione a quantidade de dias para os vendedores marcados."; campoFocar = document.getElementById('selectDias_' + codvendr);
                } else if (isPerc && (document.getElementById('inputPerc_' + codvendr).value === '' || document.getElementById('inputPerc_' + codvendr).value <= 0)) {
                    temErro = true; msgErro = "Informe a porcentagem de aumento."; campoFocar = document.getElementById('inputPerc_' + codvendr);
                } 
                // COMENTADO AQUI TAMBÉM:
                // else if (isComum && document.getElementById('metaCalculada_' + codvendr).value === '') {
                //    temErro = true; msgErro = "Informe a nova meta manual."; campoFocar = document.getElementById('metaCalculada_' + codvendr);
                // }

                if (!temErro && inputMotivo && inputMotivo.value.trim().length < minimoCaracteres) {
                    temErro = true;
                    msgErro = `Justifique as alterações com pelo menos ${minimoCaracteres} caracteres.`;
                    inputMotivo.classList.add('is-invalid');
                    if (!campoFocar) campoFocar = inputMotivo;
                }
            });

            if (temErro) {
                event.preventDefault(); 
                alert(msgErro);
                if (campoFocar) campoFocar.focus();
                return;
            }

            // 1. Primeiro, bloqueia todas as "Novas Metas" visuais para que elas NÃO sejam enviadas.
            // (Isso faz com que o formulário envie apenas o input hidden "metaOriginal" delas)
            document.querySelectorAll('.input-meta').forEach(input => {
                input.setAttribute('disabled', 'disabled');
            });

            // 2. Depois, nós liberamos APENAS os inputs dos vendedores que realmente foram alterados.
            vendedoresAlterados.forEach(codvendr => {
                const inputMeta = document.getElementById('metaCalculada_' + codvendr);
                const inputMotivo = document.getElementById('motivo_' + codvendr);
                
                // Pega os novos campos ocultos
                const inputTpAlt = document.getElementById('tpAlt_' + codvendr);
                const inputQtAlt = document.getElementById('qtAlt_' + codvendr);

                // Descobre qual switch foi ligado para esse vendedor
                const isDias = document.getElementById('switchDias_' + codvendr).checked;
                const isPerc = document.getElementById('switchPerc_' + codvendr).checked;

                // Preenche os valores baseados no switch
               // Preenche os valores baseados no switch
                if (isDias) {
                    inputTpAlt.value = 'RD';
                    inputQtAlt.value = document.getElementById('selectDias_' + codvendr).value;
                } else if (isPerc) {
                    inputTpAlt.value = 'AP';
                    // Pega o valor com vírgula da tela e manda com PONTO para o Laravel/Banco de Dados
                    inputQtAlt.value = document.getElementById('inputPerc_' + codvendr).value.replace(',', '.');
                }

                // Libera todos os campos para viajarem no POST para o Laravel
                inputMeta.removeAttribute('disabled');
                inputMeta.removeAttribute('readonly');
                inputMotivo.removeAttribute('disabled');
                inputTpAlt.removeAttribute('disabled');
                inputQtAlt.removeAttribute('disabled');
            });
        });
      }
      
            });
    </script>
  </body>

</html>