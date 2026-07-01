<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AMO_META_LOG extends Model
{
    protected $connection = 'sqlsrv_desenvolvimento';
    protected $table = 'portal.dbo.AMO_META_LOG';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    protected $fillable = [
        'ANO',
        'MES',
        'CODFILRH',
        'CODVENDR',
        'META_ANTIGA',
        'META_NOVA',
        'MOTIVO',
        'USUARIO_ALTERACAO',
        'DATA_ALTERACAO'
    ];
}
