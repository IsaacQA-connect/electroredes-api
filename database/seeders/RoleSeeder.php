<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create([
            'name' => 'ADMIN',
            'description' => 'Administrador del sistema',
            'status' => true,
        ]);

        Role::create([
            'name' => 'VENDEDOR',
            'description' => 'Usuario encargado de las ventas',
            'status' => true,
        ]);
    }
}
