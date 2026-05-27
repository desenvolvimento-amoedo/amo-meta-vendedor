<?php

namespace Modules\Controladoria\Entities;

use Illuminate\Database\Eloquent\Model;

class Filial extends Model
{
    protected $connection = 'sqlsrv_gemco';
    protected $table = 'CAD_FILIAL';
    public $timestamps = false; // Desativa os timestamps automáticos

    protected $fillable = [
        'FANTASIA',
        'CODFIL'
    ];
}
