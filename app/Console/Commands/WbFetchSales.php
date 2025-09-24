<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Repositories\SaleRepository;
use App\Services\WbApiClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WbFetchSales extends Command
{
    protected $signature = 'wb:fetch-sales
                            {--account_id= : ID аккаунта}
                            {--dateFrom= : Начальная дата (YYYY-MM-DD)}
                            {--dateTo= : Конечная дата (YYYY-MM-DD)}';

    protected $description = 'Получение продаж с Wildberries API';

    protected $saleRepository;
    protected $clientFactory;

    public function __construct(SaleRepository $saleRepository, WbApiClientFactory $clientFactory)
    {
        parent::__construct();
        $this->saleRepository = $saleRepository;
        $this->clientFactory = $clientFactory;
    }

    public function handle()
    {
        $accountId = $this->option('account_id');
        if (!$accountId) {
            $this->error('Не указан account_id');
            return 1;
        }

        $account = Account::find($accountId);
        if (!$account) {
            $this->error("Аккаунт с ID {$accountId} не найден");
            return 1;
        }

        //Проверяем, что у аккаунта есть активный токен
        if (!$account->activeToken) {
            $this->error("Аккаунт {$account->name} не имеет активного токена");
            $this->line("Создайте токен командой: php artisan token:create {$account->api_service_id} TOKEN_TYPE_ID ВАШ_ТОКЕН");
            return 1;
        }

        try {
            $client = $this->clientFactory->make($account);

            $lastUpdate = $this->saleRepository->getLastUpdateDate($accountId);
            $dateFrom = $this->option('dateFrom') ?:
                ($lastUpdate ? $lastUpdate->format('Y-m-d') : now()->subDays(7)->format('Y-m-d'));

            $dateTo = $this->option('dateTo') ?: now()->format('Y-m-d');

            $this->info("Получение продаж для аккаунта {$account->name} за период с {$dateFrom} по {$dateTo}");

            $sales = $client->getSales($dateFrom, $dateTo);

            if (empty($sales)) {
                $this->info('Нет новых данных для загрузки');
                return 0;
            }

            $bar = $this->output->createProgressBar(count($sales));
            $bar->start();

            foreach ($sales as $saleData) {
                try {
                    $this->saleRepository->updateOrCreateSale($accountId, [
                        'account_id' => $accountId,
                        'sale_id' => $saleData['sale_id'] ?? null,
                        'date' => $saleData['date'] ?? null,
                        'amount' => $saleData['total_price'] ?? 0,
                        'payload' => $saleData,
                    ]);

                    $bar->advance();
                } catch (\Exception $e) {
                    Log::error('Ошибка при сохранении продажи', [
                        'error' => $e->getMessage(),
                        'sale_data' => $saleData,
                        'account_id' => $accountId,
                    ]);
                    $this->error("\nОшибка при сохранении продажи: " . $e->getMessage());
                }
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Успешно загружено " . count($sales) . " записей о продажах");

            DB::table('data_updates')->updateOrInsert(
                ['account_id' => $accountId, 'data_type' => 'sales'],
                ['last_updated_at' => now()]
            );

            return 0;

        } catch (\Exception $e) {
            Log::error('Ошибка при получении продаж', [
                'error' => $e->getMessage(),
                'account_id' => $accountId,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("Произошла ошибка: " . $e->getMessage());
            return 1;
        }
    }
}
