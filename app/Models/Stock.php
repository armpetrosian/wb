<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Stock extends Model
{
    protected $fillable = [
        'account_id',
        'nm_id',
        'warehouse_id',
        'quantity',
        'in_way_to_client',
        'in_way_from_client',
        'data',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'in_way_to_client' => 'integer',
        'in_way_from_client' => 'integer',
        'data' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForAccount(Builder $query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeRecentlyUpdated(Builder $query, $hours = 24)
    {
        return $query->where('updated_at', '>=', now()->subHours($hours));
    }

    public function scopeForWarehouse(Builder $query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeLowQuantity(Builder $query, $threshold = 5)
    {
        return $query->where('quantity', '<=', $threshold);
    }
}
