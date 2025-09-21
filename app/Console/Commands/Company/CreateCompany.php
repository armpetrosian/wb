<?php

namespace App\Console\Commands\Company;

use App\Models\Company;
use Illuminate\Console\Command;

class CreateCompany extends Command
{
    protected $signature = 'company:create
                            {name : Название компании}
                            {description? : Описание компании}';

    protected $description = 'Создать новую компанию';

    public function handle()
    {
        $company = Company::create([
            'name' => $this->argument('name'),
            'description' => $this->argument('description') ?? null,
        ]);

        $this->info("Компания успешно создана!");
        $this->line("ID: {$company->id}");
        $this->line("Название: {$company->name}");
        if ($company->description) {
            $this->line("Описание: {$company->description}");
        }

        return Command::SUCCESS;
    }
}
