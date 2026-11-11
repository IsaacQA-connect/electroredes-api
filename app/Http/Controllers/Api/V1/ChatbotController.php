<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ChatbotController extends Controller
{
    public function query(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $userMessage = $request->input('message');

        // 1. RAG: Buscar productos relevantes en la base de datos de MySQL
        $products = DB::table('products')
            ->select('code', 'name', 'price', 'stock', 'unit_type')
            ->where('name', 'LIKE', "%{$userMessage}%")
            ->orWhere('brand', 'LIKE', "%{$userMessage}%")
            ->orWhere('code', 'LIKE', "%{$userMessage}%")
            ->limit(5)
            ->get();

        // 2. Formatear el contexto extraído de la base de datos
        $context = "INFORMACIÓN REAL DE INVENTARIO Y STOCK:\n";
        if ($products->isEmpty()) {
            $context .= "No se encontraron productos exactos en el catálogo para esta búsqueda.\n";
        } else {
            foreach ($products as $p) {
                $status = $p->stock > 0 ? "Disponible ({$p->stock} {$p->unit_type})" : "AGOTADO";
                $context .= "- SKU: {$p->code} | {$p->name} | Marca: {$p->brand} | Precio: S/ {$p->price} | Stock: {$status}\n";
            }
        }

        // 3. Crear el System Prompt con las reglas del negocio
        $systemPrompt = "Eres 'ElectriBOT', el asistente virtual experto de ELECTRO REDES (tienda de materiales eléctricos en Moquegua).
        REGLAS ESTRICTAS:
        1. Responde de forma amable, profesional y concisa (máximo 3 oraciones por respuesta).
        2. Usa ÚNICAMENTE la siguiente información real del inventario para responder sobre precios y stock:
        {$context}
        3. Si el stock es 0 o el producto no está en el catálogo, o si el usuario pide hablar con una persona, incluye al final de tu respuesta la etiqueta especial: [ESCALAR_WHATSAPP].
        4. Todos los precios están expresados en Soles peruanos (S/).";

        // 4. Consumir la API oficial de Google Gemini
        $apiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

        try {
            $response = Http::post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nPregunta del cliente: " . $userMessage]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                return response()->json([
                    'reply' => 'Lo siento, en este momento tengo problemas para consultar el sistema. ¿Deseas contactar con un asesor?',
                    'needs_human' => true
                ], 500);
            }

            $responseData = $response->json();
            $botReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'No pude procesar la respuesta.';

            // Detectar si requiere derivar a atención humana
            $needsHuman = str_contains($botReply, '[ESCALAR_WHATSAPP]');
            $cleanReply = str_replace('[ESCALAR_WHATSAPP]', '', $botReply);

            return response()->json([
                'reply' => trim($cleanReply),
                'needs_human' => $needsHuman,
                'products' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'reply' => 'Ocurrió un error inesperado al conectar con el asistente.',
                'needs_human' => true
            ], 500);
        }
    }
}