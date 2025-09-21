<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $fillable = [
        'account_id',
        'token_type_id',
        'name',
        'token_value',
        'refresh_token',
        'expires_at',
        'last_used_at',
        'is_active',
        'metadata'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function getTokenValueAttribute($value)
    {
        return $value; // Return the raw value
    }

    public function setTokenValueAttribute($value)
    {
        $this->attributes['token_value'] = $value;
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function tokenType()
    {
        return $this->belongsTo(TokenType::class);
    }
}
