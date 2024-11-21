<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralValue extends Model
{
    use HasFactory;

    protected $table = 'GENERAL_VALUE_TABLES';
    protected $primaryKey = 'id_value';

    protected $fillable = [
        'id_attr',
        'value_data',
        'timestamp_insert',  // New attribute added here

    ];

    public function attribute()
    {
        return $this->belongsTo(GeneralAttribute::class, 'id_attr', 'id_attribute');
    }
}
