<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RawResponse;
use App\Models\Sale;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Income;

class WbProcessRaw extends Command
{
    protected $signature = 'wb:process-raw';
    protected $description = 'Process raw_responses and fill normalized tables';

    public function handle()
    {
        $this->info('Start processing raw responses...');

        $this->processSales();
        $this->processOrders();
        $this->processStocks();
        $this->processIncomes();

        $this->info('All data processed successfully.');
    }

    private function processSales()
    {
        $records = RawResponse::where('endpoint', 'sales')->get();
        foreach ($records as $r) {
            $data = json_decode($r->response_body, true)['data'] ?? [];
            foreach ($data as $item) {
                $saleId = $item['sale_id'] ?? null;
                if (!$saleId) continue;

                Sale::updateOrCreate(
                    ['sale_id' => $saleId],
                    [
                        'date' => $item['date'] ?? null,
                        'amount' => $item['amount'] ?? null,
                        'payload' => json_encode($item),
                    ]
                );
            }
        }
        $this->info('Sales processed.');
    }

    private function processOrders()
    {
        $records = RawResponse::where('endpoint', 'orders')->get();
        foreach ($records as $r) {
            $data = json_decode($r->response_body, true)['data'] ?? [];
            foreach ($data as $item) {
                $orderId = $item['order_id'] ?? null;
                if (!$orderId) continue;

                Order::updateOrCreate(
                    ['order_id' => $orderId],
                    [
                        'date' => $item['date'] ?? null,
                        'total' => $item['total'] ?? null,
                        'payload' => json_encode($item),
                    ]
                );
            }
        }
        $this->info('Orders processed.');
    }

    private function processStocks()
    {
        $records = RawResponse::where('endpoint', 'stocks')->get();
        foreach ($records as $r) {
            $data = json_decode($r->response_body, true)['data'] ?? [];
            foreach ($data as $item) {
                $sku = $item['sku'] ?? null;
                $date = $item['date'] ?? null;
                if (!$sku || !$date) continue;

                Stock::updateOrCreate(
                    ['sku' => $sku, 'date' => $date],
                    [
                        'quantity' => $item['quantity'] ?? null,
                        'payload' => json_encode($item),
                    ]
                );
            }
        }
        $this->info('Stocks processed.');
    }

    private function processIncomes()
    {
        $records = RawResponse::where('endpoint', 'incomes')->get();
        foreach ($records as $r) {
            $data = json_decode($r->response_body, true)['data'] ?? [];
            foreach ($data as $item) {
                $incomeId = $item['income_id'] ?? null;
                if (!$incomeId) continue;

                Income::updateOrCreate(
                    ['id' => $incomeId],
                    [
                        'date' => $item['date'] ?? null,
                        'amount' => $item['amount'] ?? null,
                        'payload' => json_encode($item),
                    ]
                );
            }
        }
        $this->info('Incomes processed.');
    }
}
