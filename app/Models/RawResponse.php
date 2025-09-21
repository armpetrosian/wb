<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawResponse extends Model
{
    protected $fillable =
        [
            'account_id',
            'endpoint',
            'request_payload',
            'response_body',
            'http_status',
            'fetched_at',
        ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];
}
