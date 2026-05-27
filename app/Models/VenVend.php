<?php

namespace Modules\Controladoria\Entities;

use Illuminate\Database\Eloquent\Model;

class VenVend extends Model
{
    protected $connection = 'sqlsrv_gemco';
    protected $table = 'VEN_VEND';
     public $timestamps = false; // Desativa os timestamps automáticos

    protected $fillable = [
        'CODFIL',
        'CODVENDR',
        'NOME',
        'CODSUP'
    ];

        public function metas()
        {
            // Use a string class name to avoid undefined-type issues in static analysis tools
            return $this->hasMany('App\\Models\\AMO_META', 'CODVENDR', 'CODVENDR');
        }
}
