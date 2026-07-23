<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTestSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('master23');

        User::withoutEvents(function () use ($password) {
            $users = [
                ['name' => 'Master', 'email' => 'master@erp.com', 'role' => 'Master'],
                ['name' => 'Super Admin', 'email' => 'admin@erp.com', 'role' => 'Super Admin'],
                ['name' => 'Administrador', 'email' => 'manager@erp.com', 'role' => 'Administrador'],
                ['name' => 'Empleado', 'email' => 'empleado@erp.com', 'role' => 'Empleado'],
                ['name' => 'Cliente', 'email' => 'cliente@erp.com', 'role' => 'Cliente'],
                ['name' => 'Proveedor', 'email' => 'proveedor@erp.com', 'role' => 'Proveedor'],
            ];

            foreach ($users as $data) {
                $user = User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => $password,
                        'email_verified_at' => now(),
                    ]
                );
                $user->assignRole($data['role']);
            }
        });
    }
}
