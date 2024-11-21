<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralAttribute extends Model
{
    use HasFactory;

    protected $table = 'GENERAL_ATTRIBUTE_TABLES';
    protected $primaryKey = 'id_attribute';

    protected $fillable = [
        'attribute_name',
        'data_type',
        'id_table',
    ];

    protected $casts = [
        'timestamp_insert' => 'datetime', // Ensuring it's treated as a DateTime column
    ];

    public function table()
    {
        return $this->belongsTo(GeneralTable::class, 'id_table', 'id_table');
    }

    public function values()
    {
        return $this->hasMany(GeneralValue::class, 'id_attr', 'id_attribute');
    }
}
