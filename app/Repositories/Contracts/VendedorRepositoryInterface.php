<?php

namespace App\Repositories\Contracts;

interface VendedorRepositoryInterface
{
    public function getFiliaisDisponiveis(bool $isAdmin, ?int $codsup);
    public function getGerentesDisponiveis(bool $isAdmin, ?int $codsup);
    public function getVendedoresComMetas(int $ano, int $mes, ?int $codfil, ?int $codsup);

}