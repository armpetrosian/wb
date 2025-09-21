<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApiService extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'description',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function tokenTypes(): BelongsToMany
    {
        return $this->belongsToMany(TokenType::class, 'api_service_token_types')
            ->withPivot('is_default', 'settings')
            ->withTimestamps();
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }
}
