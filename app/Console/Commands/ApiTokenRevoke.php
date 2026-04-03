<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

class ApiTokenRevoke extends Command
{
    protected $signature = 'api-token:revoke {id : The token ID to revoke}';

    protected $description = 'Revoke an API token';

    public function handle(): int
    {
        $token = ApiToken::find($this->argument('id'));

        if ($token === null) {
            $this->error('Token not found.');

            return self::FAILURE;
        }

        if ($token->revoked_at !== null) {
            $this->warn('Token is already revoked.');

            return self::SUCCESS;
        }

        $token->update(['revoked_at' => now()]);

        $this->info("Token [{$token->name}] has been revoked.");

        return self::SUCCESS;
    }
}
