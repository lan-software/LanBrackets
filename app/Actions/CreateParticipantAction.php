<?php

namespace App\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateParticipantAction
{
    /**
     * Create a new participant (team or user).
     *
     * @param  array{tag?: string, description?: string, email?: string, password?: string}  $options
     */
    public function execute(string $type, string $name, array $options = []): Model
    {
        return match ($type) {
            'team' => $this->createTeam($name, $options),
            'user' => $this->createUser($name, $options),
            default => throw new InvalidArgumentException("Invalid participant type [{$type}]. Must be 'team' or 'user'."),
        };
    }

    /**
     * @param  array{tag?: string, description?: string}  $options
     */
    protected function createTeam(string $name, array $options): Team
    {
        return Team::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'tag' => $options['tag'] ?? strtoupper(Str::substr($name, 0, 3)),
            'description' => $options['description'] ?? null,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array{email?: string, password?: string}  $options
     */
    protected function createUser(string $name, array $options): User
    {
        if (empty($options['email'])) {
            throw new InvalidArgumentException('Email is required when creating a user participant.');
        }

        return User::create([
            'name' => $name,
            'email' => $options['email'],
            'password' => $options['password'] ?? Str::random(16),
        ]);
    }
}
