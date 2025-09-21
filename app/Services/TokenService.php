<?php

namespace App\Services;

use App\Models\Token;
use App\Models\Account;
use App\Models\TokenType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TokenService
{
    public function createToken(Account $account, array $data): Token
    {
        return DB::transaction(function () use ($account, $data) {
            // Деактивируем старые токены этого же типа, если нужно
            if ($data['is_active'] ?? false) {
                Token::where('account_id', $account->id)
                    ->where('token_type_id', $data['token_type_id'])
                    ->update(['is_active' => false]);
            }

            // Создание токена
            $token = $account->tokens()->create($data);

            // Добавляем метаданные
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                foreach ($data['metadata'] as $key => $value) {
                    $token->metadataItems()->create([
                        'key' => $key,
                        'value' => $value,
                        'is_encrypted' => $this->shouldEncryptMetadata($key),
                    ]);
                }
            }

            return $token->load('metadataItems');
        });
    }

    public function updateToken(Token $token, array $data): Token
    {
        return DB::transaction(function () use ($token, $data) {
            // При активации этого токена, деактивируем остальные того же типа
            if (isset($data['is_active']) && $data['is_active']) {
                Token::where('account_id', $token->account_id)
                    ->where('token_type_id', $token->token_type_id)
                    ->where('id', '!=', $token->id)
                    ->update(['is_active' => false]);
            }

            $token->update($data);

            // Обновляем метаданные
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                foreach ($data['metadata'] as $key => $value) {
                    $token->metadataItems()->updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $value,
                            'is_encrypted' => $this->shouldEncryptMetadata($key),
                        ]
                    );
                }
            }

            return $token->load('metadataItems');
        });
    }


    public function getActiveToken(int $accountId, int $tokenTypeId): ?Token
    {
        return Token::where('account_id', $accountId)
            ->where('token_type_id', $tokenTypeId)
            ->where('is_active', true)
            ->first();
    }


    protected function shouldEncryptMetadata(string $key): bool
    {
        // Какие ключи не нужно шифровать
        $unencryptedKeys = ['client_id', 'username', 'user_id'];

        return !in_array($key, $unencryptedKeys);
    }


    public function updateLastUsedAt(Token $token): bool
    {
        return $token->update(['last_used_at' => now()]);
    }


    public function deactivateToken(Token $token): bool
    {
        return $token->update(['is_active' => false]);
    }
}
