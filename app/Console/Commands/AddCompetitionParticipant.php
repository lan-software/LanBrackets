<?php

namespace App\Console\Commands;

use App\Actions\AddCompetitionParticipantAction;
use App\Models\Competition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;

#[Signature('competition:add-participant {competition : The competition ID} {--type= : Participant type (team, user)} {--id= : Participant ID (team or user)} {--seed= : Seed number (auto-assigned if omitted)}')]
#[Description('Add a team or user as a participant in a competition')]
class AddCompetitionParticipant extends Command
{
    public function handle(AddCompetitionParticipantAction $action): int
    {
        $competition = Competition::find($this->argument('competition'));

        if ($competition === null) {
            $this->error('Competition not found.');

            return self::FAILURE;
        }

        $participantType = $this->option('type') ?? select(
            label: 'Participant type',
            options: ['team' => 'Team', 'user' => 'User'],
        );

        $participantId = $this->option('id') ?? $this->promptForParticipant($participantType);

        $participant = match ($participantType) {
            'team' => Team::find($participantId),
            'user' => User::find($participantId),
            default => null,
        };

        if ($participant === null) {
            $this->error("Participant not found (type: {$participantType}, id: {$participantId}).");

            return self::FAILURE;
        }

        $seed = $this->option('seed') ? (int) $this->option('seed') : null;

        try {
            $cp = $action->execute($competition, $participant, $seed);

            $this->info("Participant added: {$participant->name} (seed: {$cp->seed})");

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function promptForParticipant(string $type): int
    {
        if ($type === 'team') {
            $teams = Team::all();

            if ($teams->isEmpty()) {
                $this->error('No teams found. Create a team first.');
                exit(1);
            }

            $teamId = select(
                label: 'Select a team',
                options: $teams->mapWithKeys(fn ($t) => [$t->id => "{$t->name} (ID: {$t->id})"]),
            );

            return (int) $teamId;
        }

        $users = User::all();

        if ($users->isEmpty()) {
            $this->error('No users found. Create a user first.');
            exit(1);
        }

        $userId = select(
            label: 'Select a user',
            options: $users->mapWithKeys(fn ($u) => [$u->id => "{$u->name} (ID: {$u->id})"]),
        );

        return (int) $userId;
    }
}
