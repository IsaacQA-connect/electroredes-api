<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $materiales = Category::where(
            'name',
            'Materiales eléctricos'
        )->first();

        $iluminacion = Category::where(
            'name',
            'Iluminación'
        )->first();

        $tecnologia = Category::where(
            'name',
            'Tecnología'
        )->first();

        $papeleria = Category::where(
            'name',
            'Papelería'
        )->first();

        Product::create([
            'category_id' => $materiales->id,
            'code' => 'ELE-CAB-001',
            'barcode' => '7751234567890',
            'name' => 'Cable eléctrico 2.5 mm',
            'description' => 'Cable para instalaciones eléctricas.',
            'unit' => 'M',
            'cost' => 4.50,
            'sale_price' => 6.50,
            'minimum_stock' => 20,
            'status' => true,
        ]);

        Product::create([
            'category_id' => $iluminacion->id,
            'code' => 'ILU-LED-001',
            'barcode' => '7751234567891',
            'name' => 'Foco LED 12W',
            'description' => 'Foco LED de 12W.',
            'unit' => 'UND',
            'cost' => 4.00,
            'sale_price' => 7.00,
            'minimum_stock' => 10,
            'status' => true,
        ]);

        Product::create([
            'category_id' => $tecnologia->id,
            'code' => 'TEC-AUD-001',
            'barcode' => '7751234567892',
            'name' => 'Audífonos Bluetooth',
            'description' => 'Audífonos inalámbricos Bluetooth.',
            'unit' => 'UND',
            'cost' => 25.00,
            'sale_price' => 39.90,
            'minimum_stock' => 5,
            'status' => true,
        ]);

        Product::create([
            'category_id' => $papeleria->id,
            'code' => 'PAP-CUA-001',
            'barcode' => null,
            'name' => 'Cuaderno universitario',
            'description' => 'Cuaderno de 100 hojas.',
            'unit' => 'UND',
            'cost' => 5.00,
            'sale_price' => 8.00,
            'minimum_stock' => 10,
            'status' => true,
        ]);
    }
}
