<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'ADMIN')->first();

        User::create([
            'role_id' => $adminRole->id,
            'name' => 'Administrador',
            'email' => 'admin@electroredes.com',
            'password' => Hash::make('password'),
            'status' => true,
        ]);
    }
}
