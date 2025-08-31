<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'sku',
        'quantity',
        'date',
        'payload',
    ];
    protected $casts = [
        'date' => 'date',
        'payload' => 'array',
    ];

}
