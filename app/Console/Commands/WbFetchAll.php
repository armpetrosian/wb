<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WbApiClient;
use App\Models\RawResponse;

class WbFetchAll extends Command
{
    protected $signature = 'wb:fetch-all {--account_id=} {--dateFrom=} {--dateTo=}';
    protected $description = 'Fetch all WB API endpoints (sales, orders, stocks, incomes)';

    public function handle()
    {
        $accountId = $this->option('account_id');
        if (!$accountId) {
            $this->error('Не указан account_id. Используйте --account_id=ID');
            return 1;
        }

        $account = \App\Models\Account::find($accountId);
        if (!$account) {
            $this->error("Аккаунт с ID {$accountId} не найден");
            return 1;
        }

        if (!$account->activeToken) {
            $this->error("Аккаунт {$account->name} не имеет активного токена");
            return 1;
        }

        $client = (new \App\Services\WbApiClientFactory())->make($account);
        
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
                $data = $client->getPaginated($ep, $params);

                foreach ($data as $item) {
                    RawResponse::create([
                        'account_id' => $accountId,
                        'endpoint' => $ep,
                        'request_payload' => json_encode($params),
                        'response_body' => json_encode($item),
                        'http_status' => 200,
                        'fetched_at' => now(),
                        'processed' => false
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("Ошибка при {$ep}: " . $e->getMessage());
            }
        }

        $this->info('Все данные стянуты');
    }
}
