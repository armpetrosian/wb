<?php

namespace App\Console\Commands\Account;

use App\Models\Account;
use App\Models\Company;
use App\Models\ApiService;
use Illuminate\Console\Command;

class CreateAccount extends Command
{
    protected $signature = 'account:create
                            {company_id : ID компании}
                            {api_service_id : ID API сервиса}
                            {name : Название аккаунта}
                            {--external_id= : Внешний ID аккаунта}
                            {--active : Сделать аккаунт активным}';

    protected $description = 'Создать новый аккаунт';

    public function handle()
    {
        $company = Company::find($this->argument('company_id'));
        if (!$company) {
            $this->error('Компания не найдена');
            return 1;
        }

        $apiService = ApiService::find($this->argument('api_service_id'));
        if (!$apiService) {
            $this->error('API сервис не найден');
            return 1;
        }

        $account = new Account([
            'company_id' => $company->id,
            'api_service_id' => $apiService->id,
            'name' => $this->argument('name'),
            'external_id' => $this->option('external_id'),
            'is_active' => $this->option('active', false),
        ]);

        $account->save();

        $this->info("Аккаунт успешно создан!");
        $this->line("ID: " . $account->id);
        $this->line("Название: " . $account->name);
        $this->line("Компания: " . $company->name);
        $this->line("API сервис: " . $apiService->name);
        $this->line("Статус: " . ($account->is_active ? 'Активен' : 'Неактивен'));

        return 0;
    }
}
