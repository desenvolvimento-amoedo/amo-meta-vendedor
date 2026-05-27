<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AMO_META extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'AMO_META';
    protected $primaryKey = 'ID';
    public $timestamps = false; // Desativa os timestamps automáticos

    protected $fillable = [
        'CODVENDR',
        'CODFIL',
        'ANO',
        'MES',
        'META',
        'DATA_CADASTRO'
    ];

            public function vendedor()
            {
                // Use a string class name to avoid undefined-type issues in static analysis tools
                return $this->belongsTo('App\\Models\\VenVend', 'CODVENDR', 'CODVENDR');
            }
}
