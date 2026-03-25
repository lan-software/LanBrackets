<?php

namespace App\Console\Commands;

use App\Actions\CreateParticipantAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('participant:create {--type= : Participant type (team, user)} {--name= : Name} {--tag= : Team tag (teams only)} {--description= : Description (teams only)} {--email= : Email (users only)} {--password= : Password (users only, auto-generated if omitted)}')]
#[Description('Create a new team or user participant')]
class CreateParticipant extends Command
{
    public function handle(CreateParticipantAction $action): int
    {
        $type = $this->option('type') ?? select(
            label: 'Participant type',
            options: ['team' => 'Team', 'user' => 'User'],
        );

        $name = $this->option('name') ?? text(
            label: 'Name',
            required: true,
        );

        $options = match ($type) {
            'team' => $this->collectTeamOptions(),
            'user' => $this->collectUserOptions(),
            default => [],
        };

        try {
            $participant = $action->execute($type, $name, $options);

            $this->info(ucfirst($type) . " created: {$participant->name} (ID: {$participant->id})");

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{tag?: string, description?: string}
     */
    protected function collectTeamOptions(): array
    {
        $options = [];

        if ($tag = $this->option('tag')) {
            $options['tag'] = $tag;
        }

        if ($description = $this->option('description')) {
            $options['description'] = $description;
        }

        return $options;
    }

    /**
     * @return array{email?: string, password?: string}
     */
    protected function collectUserOptions(): array
    {
        $options = [];

        $options['email'] = $this->option('email') ?? text(
            label: 'Email address',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Must be a valid email address.',
        );

        if ($password = $this->option('password')) {
            $options['password'] = $password;
        }

        return $options;
    }
}
