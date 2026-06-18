<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetaController;

/*
|--------------------------------------------------------------------------
| Web Routes - Gestão de Metas
|--------------------------------------------------------------------------
|
| Passamos o grupo 'web' para ativar as sessões/cookies + seu 'corporate.auth'
| para garantir a identificação e segurança do usuário logado.
|
*/

Route::middleware(['web', 'corporate.auth'])->group(function () {

    // Rota da página inicial: Exibe os filtros e a tabela de metas dos vendedores
    Route::get('/', [MetaController::class, 'index'])->name('metas.index');

    // Rota de envio do formulário: Salva as metas preenchidas em lote
    Route::post('/metas', [MetaController::class, 'store'])->name('metas.store');

});