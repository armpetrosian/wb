<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Income extends Model
{
    protected $fillable = [
        'account_id',
        'income_id',
        'number',
        'date',
        'last_change_date',
        'supplier_article',
        'tech_size',
        'barcode',
        'quantity',
        'total_price',
        'date_close',
        'warehouse_name',
        'nm_id',
        'status',
        'data',
    ];

    protected $casts = [
        'date' => 'datetime',
        'last_change_date' => 'datetime',
        'date_close' => 'datetime',
        'quantity' => 'integer',
        'total_price' => 'float',
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

    public function scopeFresh(Builder $query, $days = 7)
    {
        return $query->where('date', '>=', now()->subDays($days));
    }

    public function scopeForPeriod(Builder $query, $startDate, $endDate = null)
    {
        $query->where('date', '>=', $startDate);

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query;
    }

    public function scopeWithStatus(Builder $query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeBySupplierArticle(Builder $query, $supplierArticle)
    {
        return $query->where('supplier_article', $supplierArticle);
    }

    public function scopeByNmId(Builder $query, $nmId)
    {
        return $query->where('nm_id', $nmId);
    }
}
