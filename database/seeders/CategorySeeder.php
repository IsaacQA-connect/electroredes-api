<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Materiales eléctricos',
            'description' => 'Cables, canaletas, enchufes y otros materiales eléctricos.',
            'status' => true,
        ]);

        Category::create([
            'name' => 'Iluminación',
            'description' => 'Luminarias, focos y productos de iluminación.',
            'status' => true,
        ]);

        Category::create([
            'name' => 'Tecnología',
            'description' => 'Audífonos, baterías portátiles y equipos de sonido.',
            'status' => true,
        ]);

        Category::create([
            'name' => 'Papelería',
            'description' => 'Papel, cuadernos y útiles escolares.',
            'status' => true,
        ]);

        Category::create([
            'name' => 'Servicios',
            'description' => 'Servicios relacionados con instalaciones eléctricas.',
            'status' => true,
        ]);
    }
}
