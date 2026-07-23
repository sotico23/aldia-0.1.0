<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CleanClientUserRoles extends Command
{
    protected $signature = 'app:clean-client-user-roles
        {--dry-run : List affected users without making changes}';

    protected $description = 'Remove "Administrador" role from users linked to a Cliente and ensure they have only the "Cliente" role';

    public function handle(): void
    {
        $clienteRole = Role::firstOrCreate(['name' => 'Cliente']);

        $users = User::has('cliente')->get();

        if ($users->isEmpty()) {
            $this->info('No client users found.');

            return;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Current Roles', 'Action'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->getRoleNames()->implode(', '),
                $this->option('dry-run') ? 'Would update' : 'Updated',
            ])
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes made.');

            return;
        }

        foreach ($users as $user) {
            $user->syncRoles([$clienteRole->name]);
        }

        $this->info("Cleaned roles for {$users->count()} client user(s).");
    }
}
