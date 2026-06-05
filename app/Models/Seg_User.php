<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seg_User extends Model
{
    protected $table = 'SEG_USER';
    protected $primaryKey = 'USERID';
    protected $fillable = [
        'CODFIL',
        'NOME',
        'CODVENDR',
        'STATUS',
        'CODSETOR',
    ];

    public function role()
    {
        return $this->belongsTo('App\\Models\\Seg_Role', 'role_id');
    }
}
