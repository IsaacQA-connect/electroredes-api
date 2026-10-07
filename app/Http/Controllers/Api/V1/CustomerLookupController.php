<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CustomerLookupController extends Controller
{
    public function lookup(string $type, string $number)
    {
        $token = config('services.apisperu.token');
        
        if ($type === 'dni' && strlen($number) === 8) {
            $response = Http::get("https://dniruc.apisperu.com/api/v1/dni/{$number}?token={$token}");
            
            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success'  => true,
                    'name'     => "{$data['nombres']} {$data['apellidoPaterno']} {$data['apellidoMaterno']}",
                    'address'  => '',
                    'doc_type' => '1'
                ]);
            }
        }

        if ($type === 'ruc' && strlen($number) === 11) {
            $response = Http::get("https://dniruc.apisperu.com/api/v1/ruc/{$number}?token={$token}");
            
            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success'  => true,
                    'name'     => $data['razonSocial'],
                    'address'  => $data['direccion'] ?? '',
                    'doc_type' => '6'
                ]);
            }
        }

        return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 444);
    }
}