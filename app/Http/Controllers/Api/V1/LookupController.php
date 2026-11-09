<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LookupController extends Controller
{
    public function searchDni($dni)
    {
        if (strlen($dni) !== 8) {
            return response()->json(['message' => 'El DNI debe tener 8 dígitos'], 422);
        }

        $token   = config('services.apisperu.lookup.token');
        $baseUrl = config('services.apisperu.lookup.url');

        $response = Http::withToken($token)->get("{$baseUrl}/dni/{$dni}");

        // Si la respuesta falla, devolvemos el código y mensaje real que retorna APIsPeru
        if ($response->failed()) {
            return response()->json([
                'message' => 'Error en la consulta con APIsPeru',
                'status_code' => $response->status(),
                'apisperu_response' => $response->json() ?? $response->body()
            ], $response->status());
        }

        $data = $response->json();

        return response()->json([
            'success' => true,
            'name' => trim(($data['nombres'] ?? '') . ' ' . ($data['apellidoPaterno'] ?? '') . ' ' . ($data['apellidoMaterno'] ?? '')),
            'raw' => $data,
        ]);
    }

    public function searchRuc($ruc)
    {
        if (strlen($ruc) !== 11) {
            return response()->json(['message' => 'El RUC debe tener 11 dígitos'], 422);
        }

        $token   = config('services.apisperu.lookup.token');
        $baseUrl = config('services.apisperu.lookup.url');

        $response = Http::withToken($token)->get("{$baseUrl}/ruc/{$ruc}");

        if ($response->failed()) {
            return response()->json(['message' => 'No se encontraron datos para el RUC'], 404);
        }

        $data = $response->json();

        return response()->json([
            'success' => true,
            'name' => $data['razonSocial'] ?? '',
            'address' => $data['direccion'] ?? '',
            'state' => $data['estado'] ?? '',
            'condition' => $data['condicion'] ?? '',
            'raw' => $data,
        ]);
    }
}