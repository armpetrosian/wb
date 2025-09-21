<?php

namespace App\Console\Commands\ApiService;

use App\Models\ApiService;
use Illuminate\Console\Command;

class CreateApiService extends Command
{
    protected $signature = 'api-service:create
                            {name : Название API-сервиса}
                            {slug : Уникальный идентификатор API-сервиса}
                            {base_url : Базовый URL API-сервиса}
                            {--description= : Описание API-сервиса}
                            {--active : Активировать сервис}';

    protected $description = 'Создать новый API-сервис';

    public function handle()
    {
        $service = ApiService::create([
            'name' => $this->argument('name'),
            'slug' => $this->argument('slug'),
            'base_url' => rtrim($this->argument('base_url'), '/') . '/',
            'description' => $this->option('description'),
            'is_active' => $this->option('active') ?? true,
        ]);

        $this->info("API-сервис успешно создан!");
        $this->line("ID: {$service->id}");
        $this->line("Название: {$service->name}");
        $this->line("Идентификатор: {$service->slug}");
        $this->line("Базовый URL: {$service->base_url}");
        if ($service->description) {
            $this->line("Описание: {$service->description}");
        }
        $this->line("Активен: " . ($service->is_active ? 'Да' : 'Нет'));

        return Command::SUCCESS;
    }
}
