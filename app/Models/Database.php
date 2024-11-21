<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralDatabase extends Model
{
    use HasFactory;

    protected $table = 'GENERAL_BD_TABLES';
    protected $primaryKey = 'id_bd';

    protected $fillable = [
        'timestamp_insert',
        'db_name',
    ];

    public function tables()
    {
        return $this->hasMany(GeneralTable::class, 'db_id', 'id_bd');
    }
}
