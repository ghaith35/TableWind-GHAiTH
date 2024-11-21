<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralPkey extends Model
{
    use HasFactory;

    protected $table = 'GENERAL_PKEY_TABLES';
    protected $primaryKey = 'constraint_id';

    protected $fillable = [
        'attribute_id',
    ];

    public function attribute()
    {
        return $this->belongsTo(GeneralAttribute::class, 'attribute_id', 'id_attribute');
    }
}
