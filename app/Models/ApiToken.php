<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'token', 'plain_text_prefix', 'webhook_url', 'webhook_secret',
    'last_used_at', 'expires_at', 'revoked_at',
])]
class ApiToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Create a new API token and return the plain text value.
     *
     * @return array{token: self, plainText: string}
     */
    public static function createToken(string $name, ?string $plainText = null): array
    {
        $plainText ??= 'lbt_'.Str::random(60);

        $token = self::create([
            'name' => $name,
            'token' => hash('sha256', $plainText),
            'plain_text_prefix' => substr($plainText, 0, 8),
        ]);

        return ['token' => $token, 'plainText' => $plainText];
    }

    /**
     * Find a token by its plain text value.
     */
    public static function findByPlainText(string $plainText): ?self
    {
        return self::where('token', hash('sha256', $plainText))->first();
    }
}
