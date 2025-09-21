<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WbApiClient;
use App\Models\RawResponse;

class WbFetchAll extends Command
{
    protected $signature = 'wb:fetch-all {--dateFrom=} {--dateTo=}';
    protected $description = 'Fetch all WB API endpoints (sales, orders, stocks, incomes)';

    protected WbApiClient $client;

    public function __construct(WbApiClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    public function handle()
    {
        $dateFrom = $this->option('dateFrom') ?? now()->subMonth()->format('Y-m-d');
        $dateTo   = $this->option('dateTo') ?? now()->format('Y-m-d');

        $endpoints = [
            'sales'   => ['dateFrom' => $dateFrom, 'dateTo' => $dateTo],
            'orders'  => ['dateFrom' => $dateFrom, 'dateTo' => $dateTo],
            'stocks'  => ['dateFrom' => now()->format('Y-m-d')],
            'incomes' => ['dateFrom' => $dateFrom, 'dateTo' => $dateTo],
        ];

        foreach ($endpoints as $ep => $params) {
            $this->info("Fetching {$ep}...");
            try {
                $data = $this->client->getPaginated($ep, $params);

                foreach ($data as $item) {
                    RawResponse::create([
                        'endpoint' => $ep,
                        'request_payload' => json_encode($params),
                        'response_body' => json_encode($item),
                        'http_status' => 200,
                        'fetched_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("Ошибка при {$ep}: " . $e->getMessage());
            }
        }

        $this->info('Все данные стянуты');
    }
}
