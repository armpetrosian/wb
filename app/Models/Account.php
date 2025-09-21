<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'api_service_id',
        'name',
        'external_id',
        'is_active',
        'settings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    // Отношения
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function apiService()
    {
        return $this->belongsTo(ApiService::class);
    }

    public function tokens()
    {
        return $this->hasMany(Token::class);
    }

    public function activeToken()
    {
        return $this->hasOne(Token::class)->where('is_active', true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Методы для работы с датами обновления
    public function getLastUpdateDate(string $dataType): ?\Carbon\Carbon
    {
        $update = \App\Models\DataUpdate::where('account_id', $this->id)
            ->where('data_type', $dataType)
            ->first();

        return $update ? $update->last_updated_at : null;
    }

    public function updateLastUpdateDate(string $dataType): void
    {
        \App\Models\DataUpdate::updateOrCreate(
            ['account_id' => $this->id, 'data_type' => $dataType],
            ['last_updated_at' => now()]
        );
    }
}
