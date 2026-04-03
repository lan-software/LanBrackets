<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

class ApiTokenCreate extends Command
{
    protected $signature = 'api-token:create
        {name : Name for the token}
        {--generate : Generate a random token}
        {--token= : Provide a specific token value}';

    protected $description = 'Create a new API token';

    public function handle(): int
    {
        $name = $this->argument('name');
        $generate = $this->option('generate');
        $providedToken = $this->option('token');

        if (! $generate && ! $providedToken) {
            $this->error('You must pass either --generate or --token=<value>.');

            return self::FAILURE;
        }

        $plainText = $generate ? null : $providedToken;
        $result = ApiToken::createToken($name, $plainText);

        $this->info('Token created successfully!');
        $this->newLine();
        $this->line("  Name:    {$result['token']->name}");
        $this->line("  Prefix:  {$result['token']->plain_text_prefix}");
        $this->line("  Token:   {$result['plainText']}");
        $this->newLine();
        $this->warn('Copy the token now — it will not be shown again.');

        return self::SUCCESS;
    }
}
