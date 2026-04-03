<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

class ApiTokenList extends Command
{
    protected $signature = 'api-token:list';

    protected $description = 'List all API tokens';

    public function handle(): int
    {
        $tokens = ApiToken::orderBy('created_at', 'desc')->get();

        if ($tokens->isEmpty()) {
            $this->info('No API tokens found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Prefix', 'Last Used', 'Expires', 'Status'],
            $tokens->map(fn (ApiToken $token) => [
                $token->id,
                $token->name,
                $token->plain_text_prefix.'...',
                $token->last_used_at?->diffForHumans() ?? 'Never',
                $token->expires_at?->toDateString() ?? 'Never',
                $token->revoked_at ? 'Revoked' : ($token->isValid() ? 'Active' : 'Expired'),
            ])
        );

        return self::SUCCESS;
    }
}
