<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetaController;
use App\Services\AuthCorporateService; 
use Illuminate\Http\Request;          


/*
|--------------------------------------------------------------------------
| Web Routes - Gestão de Metas
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'corporate.auth'])->group(function () {

    // Rota da página inicial: Exibe os filtros e a tabela de metas dos vendedores
    Route::get('/', [MetaController::class, 'index'])->name('metas.index');

    // Rota de envio do formulário: Salva as metas preenchidas em lote
    Route::post('/metas', [MetaController::class, 'store'])->name('metas.store');

    // NOVA ROTA DE TESTE: Colocando aqui dentro, ela herda a segurança do middleware
    Route::get('/teste-usuario', function (Request $request, AuthCorporateService $authService) {
        return response()->json($authService->getUserContext($request));
    });

});