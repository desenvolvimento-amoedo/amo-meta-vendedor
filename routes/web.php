<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetaController;

/*
|--------------------------------------------------------------------------
| Web Routes - Gestão de Metas
|--------------------------------------------------------------------------
|
| Todas as rotas dentro deste grupo passam pelo 'corporate.auth', que 
| identifica automaticamente o usuário logado no portal/rede da empresa.
|
*/

Route::middleware(['corporate.auth'])->group(function () {

    // Rota da página inicial: Exibe os filtros e a tabela de metas dos vendedores
    Route::get('/', [MetaController::class, 'index'])->name('metas.index');

    // Rota de envio do formulário: Salva as metas preenchidas em lote (updateOrCreate)
    Route::post('/metas', [MetaController::class, 'store'])->name('metas.store');

});
