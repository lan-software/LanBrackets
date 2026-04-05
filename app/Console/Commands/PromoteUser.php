<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:promote {email : The email address of the user to promote} {--role=admin : The role to assign (moderator, admin, or superadmin)}')]
#[Description('Promote a user to a privileged role')]
class PromoteUser extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $roleName = (string) $this->option('role');
        $role = UserRole::tryFrom($roleName);

        if ($role === null || ! in_array($role, UserRole::privileged(), true)) {
            $this->error("Invalid role. Use 'moderator', 'admin', or 'superadmin'.");

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        if ($user->role === $role) {
            $this->warn("User [{$user->name}] already has the {$role->value} role.");

            return self::SUCCESS;
        }

        $user->forceFill(['role' => $role])->save();

        $this->info("User [{$user->name}] has been promoted to {$role->value}.");

        return self::SUCCESS;
    }
}
