<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'date',
        'total',
        'payload',
    ];
    protected $casts = [
        'date' => 'date',
        'payload' => 'array',
    ];

}
