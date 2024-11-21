<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralTable extends Model
{
    use HasFactory;

    protected $table = 'GENERAL_TABLE_TABLES';
    protected $primaryKey = 'id_table';

    protected $fillable = [
        'timestamp_insert',
        'table_name',
        'db_id',
    ];

    public function database()
    {
        return $this->belongsTo(GeneralDatabase::class, 'db_id', 'id_bd');
    }

    public function attributes()
    {
        return $this->hasMany(GeneralAttribute::class, 'id_table', 'id_table');
    }

    public function values()
    {
        return $this->hasMany(GeneralValue::class, 'id_attr', 'id_table');
    }
}
