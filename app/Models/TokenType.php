<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TokenType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_refresh_token',
        'requires_expires_at',
        'validation_rules',
    ];

    protected $casts = [
        'requires_refresh_token' => 'boolean',
        'requires_expires_at' => 'boolean',
        'validation_rules' => 'array',
    ];

    public function apiServices(): BelongsToMany
    {
        return $this->belongsToMany(ApiService::class, 'api_service_token_types')
            ->withPivot('is_default', 'settings')
            ->withTimestamps();
    }

    public function tokens()
    {
        return $this->hasMany(Token::class);
    }
}
