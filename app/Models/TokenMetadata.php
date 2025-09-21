<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TokenMetadata extends Model
{
    protected $fillable = [
        'token_id',
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }

    // Шифрование/дешифровка значения метаданных
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = $this->is_encrypted ? encrypt($value) : $value;
    }

    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }
}
