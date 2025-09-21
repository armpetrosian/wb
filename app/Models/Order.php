<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    protected $fillable = [
        'account_id',
        'order_id',
        'date',
        'status',
        'data',
    ];

    protected $casts = [
        'date' => 'datetime',
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
}
