<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueryHistory extends Model
{
    use HasFactory;

    protected $table = 'General_QUERY_table'; // Name of the table
    protected $fillable = ['query']; // The 'query' column
}