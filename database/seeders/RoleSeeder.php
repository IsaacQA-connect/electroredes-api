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
        Role::firstOrCreate(
            ['name' => 'ADMINISTRADOR'],
            [
                'description' => 'Administrador del sistema',
                'status' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'VENDEDOR'],
            [
                'description' => 'Usuario encargado de las ventas',
                'status' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => 'CLIENTE'],
            [
                'description' => 'Cliente registrado desde la tienda online',
                'status' => true,
            ]
        );
    }
}