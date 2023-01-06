<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GfRealvalidation extends Model
{
    protected $fillable = [
        'hash',
        'status',
        'is_cell',
        'is_valid',
        'caller_name',
        'carrier',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_cell' => 'boolean',
        'is_valid' => 'boolean',
    ];
}
