<?php

namespace App\Console\Commands;

use App\Models\Competition;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('competition:list')]
#[Description('List all competitions')]
class ListCompetitions extends Command
{
    public function handle(): int
    {
        $competitions = Competition::withCount(['stages', 'participants'])
            ->orderByDesc('created_at')
            ->get();

        if ($competitions->isEmpty()) {
            $this->warn('No competitions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Type', 'Status', 'Stages', 'Participants'],
            $competitions->map(fn ($c) => [
                $c->id,
                $c->name,
                $c->type->value,
                $c->status->value,
                $c->stages_count,
                $c->participants_count,
            ]),
        );

        return self::SUCCESS;
    }
}
