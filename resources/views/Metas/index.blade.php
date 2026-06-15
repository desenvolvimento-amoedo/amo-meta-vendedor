{{-- resources/views/metas/index.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metas de Vendedores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/metas.css') }}" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-custom">
        <div class="container">
            <a class="navbar-brand brand-custom" href="#">
                <img src="{{ asset('images/logo_fundo_removido.png') }}" alt="Logo Amoedo" class="brand-logo">
                Gestão de Metas dos Vendedores
            </a>

            <span class="user-status">
                Usuário Conectado: <strong>{{ $userContext['username'] ?? 'Usuário' }}</strong>
                <span class="badge badge-role">
                    {{ ($userContext['is_admin'] ?? false) ? 'Administrador' : 'Gerente (CODSUP: '.($userContext['codsup'] ?? '').')' }}
                </span>
            </span>
        </div>
    </nav>

    <div class="container mb-5">

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

        <div class="card card-custom">
            <div class="card-header card-header-filters">Filtros de Seleção (Selecione a Filial ou o Gerente)</div>
            <div class="card-body">
                <form method="GET" action="{{ route('metas.index') }}" id="form-filtros">
                    <div class="row g-3">

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ano</label>
                            <select name="ano" class="form-select" onchange="document.getElementById('form-filtros').submit()">
                                @for($i = date('Y') - 1; $i <= date('Y') + 1; $i++)
                                    <option value="{{ $i }}" {{ ($filtros['ano'] ?? date('Y')) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Mês</label>
                            <select name="mes" class="form-select" onchange="document.getElementById('form-filtros').submit()">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ ($filtros['mes'] ?? date('m')) == $m ? 'selected' : '' }}>
                                        {{ str_pad($m, 2, '0', STR_PAD_LEFT) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Filial</label>
                            <select name="codfil" class="form-select" onchange="document.getElementById('form-filtros').submit()">
                                <option value="">Selecione a Filial</option>
                                @foreach($listas['filiais'] as $filial)
                                <option value="{{ $filial->CODFIL }}" {{ $filtros['codfil'] == $filial->CODFIL ? 'selected' : '' }}>
                                    {{ $filial->CODFIL }} - {{ $filial->FANTASIA }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Gerente</label>
                            @if($userContext['is_admin'] ?? false)
                            <select name="codsup" class="form-select" onchange="document.getElementById('form-filtros').submit()">
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

        @if(!empty($vendedores) && (is_array($vendedores) || is_object($vendedores)) && count($vendedores) > 0)
        <div class="card card-custom">
            <div class="card-header card-header-results">
                <span>Vendedores Encontrados</span>
                <span class="badge bg-light text-primary">Total: {{ count($vendedores) }}</span>
            </div>

            <div class="card-body p-0">
                <form method="POST" action="{{ route('metas.store') }}">
                    @csrf
                    <input type="hidden" name="ano" value="{{ $filtros['ano'] ?? date('Y') }}">
                    <input type="hidden" name="mes" value="{{ $filtros['mes'] ?? date('m') }}">
                    <input type="hidden" name="codfil" value="{{ $filtros['codfil'] ?? '' }}">
                    <input type="hidden" name="codsup" value="{{ $filtros['codsup'] ?? '' }}">

                    @php
                    $anoAtual = (int) date('Y');
                    $mesAtual = (int) date('m');
                    $anoFiltro = (int) ($filtros['ano'] ?? $anoAtual);
                    $mesFiltro = (int) ($filtros['mes'] ?? $mesAtual);
                    $bloquearEdicao = ($anoFiltro < $anoAtual || ($anoFiltro == $anoAtual && $mesFiltro < $mesAtual));
                    @endphp

                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-header-custom">
                                <tr>
                                    <th style="width: 5%;">Código</th>
                                    <th style="width: 20%;">Vendedor</th>
                                    <th style="width: 5%;">Filial</th>
                                    <th style="width: 15%;">Meta Definida</th>
                                    <th style="width: 15%;">Alteração</th>
                                    <th style="width: 25%;">Motivo da Alteração</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($vendedores ?? []) as $vendedor)
                                <tr>
                                    <td class="fw-bold">{{ $vendedor->CODVENDR }}</td>
                                    <td>{{ $vendedor->NOME }}</td>
                                    <td><span class="badge bg-secondary">{{ $vendedor->CODFIL }}</span></td>
                                    
                                    {{-- COLUNA DA META --}}
                                    <td>
                                        <input type="hidden" name="metas[{{ $vendedor->CODVENDR }}][codfil]" value="{{ $vendedor->CODFIL }}">
                                        <div class="input-group">
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="R$ 0"
                                                   class="form-control input-meta"
                                                   id="meta_{{ $vendedor->CODVENDR }}"
                                                   name="metas[{{ $vendedor->CODVENDR }}][meta]" 
                                                   value="{{ $vendedor->META }}" 
                                                   placeholder="R$ 0,00" 
                                                   disabled> {{-- Sempre travado ao carregar --}}
                                        </div>
                                    </td>

                                    {{-- COLUNA DO SWITCH SLIDER --}}
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" 
                                                   class="switch-alteracao" 
                                                   id="switch_{{ $vendedor->CODVENDR }}" 
                                                   data-vendedor="{{ $vendedor->CODVENDR }}"
                                                   {{ $bloquearEdicao ? 'disabled' : '' }}>
                                            <span class="slider"></span>
                                        </label>
                                    </td>

                                    {{-- COLUNA DO MOTIVO --}}
                                    <td>
                                        <input type="text" 
                                               class="form-control input-motivo" 
                                               id="motivo_{{ $vendedor->CODVENDR }}"
                                               name="metas[{{ $vendedor->CODVENDR }}][motivo]" 
                                               value="{{ $vendedor->MOTIVO ?? '' }}" 
                                               placeholder="Motivo da Alteração" 
                                               disabled> {{-- Sempre travado ao carregar --}}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>

                            
                        </table>

                    </div>

                    <div class="footer-actions">
                        @if(!$bloquearEdicao)
                        <button type="submit" class="btn btn-success btn-action">Salvar Alterações</button>
                        @else
                        <button type="button" class="btn btn-secondary btn-action" disabled>Mês Fechado</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
        @else
        <div class="alert alert-info shadow-sm border-0" role="alert">
            💡 <strong>Instrução:</strong> @if($userContext['is_admin'] ?? false) Selecione uma <strong>Filial</strong> ou um <strong>Gerente</strong> nos filtros acima para listar os vendedores e gerenciar as metas. @else Selecione uma <strong>Filial</strong> para filtrar os seus vendedores sob sua supervisão. @endif
        </div>
        @endif

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    {{-- JAVASCRIPT EXCLUSIVO DA TELA PARA CONTROLAR OS TRAVAMENTOS --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // 1. Gerencia o comportamento dos switches para ativar/desativar os inputs
            const switches = document.querySelectorAll('.switch-alteracao');
            
            switches.forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    const codvendr = this.getAttribute('data-vendedor');
                    const inputMeta = document.getElementById('meta_' + codvendr);
                    const inputMotivo = document.getElementById('motivo_' + codvendr);

                    if (this.checked) {
                        // Switch ativo: libera meta e motivo, foca na meta
                        if (inputMeta) inputMeta.removeAttribute('disabled');
                        if (inputMotivo) inputMotivo.removeAttribute('disabled');
                        if (inputMeta) inputMeta.focus();
                    } else {
                        // Switch desligado: bloqueia novamente
                        if (inputMeta) inputMeta.setAttribute('disabled', 'disabled');
                        if (inputMotivo) inputMotivo.setAttribute('disabled', 'disabled');
                    }
                });
            });

            // 2. Antes de submeter o POST, removemos temporariamente o 'disabled' 
            // de todos os inputs para que o Laravel não receba arrays vazios nas linhas não modificadas
            const formSalvar = document.querySelector('form[action="{{ route("metas.store") }}"]');

            if (formSalvar) {
                formSalvar.addEventListener('submit', function () {
                    document.querySelectorAll('.input-meta, .input-motivo').forEach(function (input) {
                        input.removeAttribute('disabled');
                    });
                });
            }
        });
    </script>
</body>

</html>