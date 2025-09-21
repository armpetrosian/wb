<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataUpdate extends Model
{
    protected $fillable = [
        'account_id',
        'data_type',
        'last_updated_at'
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
