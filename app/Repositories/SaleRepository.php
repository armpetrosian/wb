<?php

namespace App\Repositories;

use App\Models\Sale;
use Carbon\Carbon;

class SaleRepository
{
    public function getFreshSales($accountId, $days = 7)
    {
        return Sale::query()
            ->forAccount($accountId)
            ->fresh($days)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getSalesByPeriod($accountId, $startDate, $endDate = null)
    {
        return Sale::query()
            ->forAccount($accountId)
            ->forPeriod($startDate, $endDate)
            ->orderBy('date')
            ->get();
    }

    public function updateOrCreateSale($accountId, array $saleData, $uniqueKey = 'sale_id')
    {
        return Sale::updateOrCreate(
            [
                'account_id' => $accountId,
                $uniqueKey => $saleData[$uniqueKey] ?? null,
            ],
            $saleData
        );
    }

    public function getLastUpdateDate($accountId)
    {
        return Sale::query()
            ->forAccount($accountId)
            ->max('date');
    }
}
