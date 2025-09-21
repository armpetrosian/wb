<?php

namespace App\Console\Commands\TokenType;

use App\Models\TokenType;
use Illuminate\Console\Command;

class CreateTokenType extends Command
{
    protected $signature = 'token-type:create
                            {name : Название типа токена}
                            {slug : Уникальный идентификатор}
                            {description? : Описание типа токена}
                            {--requires-refresh : Требует refresh token}
                            {--requires-expires : Требует expires_at}';

    protected $description = 'Создать новый тип токена';

    public function handle()
    {
        $tokenType = TokenType::create([
            'name' => $this->argument('name'),
            'slug' => $this->argument('slug'),
            'description' => $this->argument('description'),
            'requires_refresh_token' => $this->option('requires-refresh'),
            'requires_expires_at' => $this->option('requires-expires'),
        ]);

        $this->info("Тип токена успешно создан!");
        $this->line("ID: " . $tokenType->id);
        $this->line("Название: " . $tokenType->name);
        $this->line("Идентификатор: " . $tokenType->slug);
    }
}
