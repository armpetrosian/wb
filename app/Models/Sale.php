<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'sale_id',
        'date',
        'amount',
        'payload',
    ];

    protected $casts = [
        'date' => 'date',
        'payload' => 'array',
    ];
}
