<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralFkey extends Model
{
    use HasFactory;

    protected $table = 'GENERAL_FKEY_TABLES';
    protected $primaryKey = 'constraint_id';

    protected $fillable = [
        'source_table_id',
        'target_table_id',
        'source_attribute_id',
        'target_attribute_id',
    ];

    public function sourceTable()
    {
        return $this->belongsTo(GeneralTable::class, 'source_table_id', 'id_table');
    }

    public function targetTable()
    {
        return $this->belongsTo(GeneralTable::class, 'target_table_id', 'id_table');
    }

    public function sourceAttribute()
    {
        return $this->belongsTo(GeneralAttribute::class, 'source_attribute_id', 'id_attribute');
    }

    public function targetAttribute()
    {
        return $this->belongsTo(GeneralAttribute::class, 'target_attribute_id', 'id_attribute');
    }
}
