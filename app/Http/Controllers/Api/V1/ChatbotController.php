<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function query(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $userMessage = $request->input('message');

        // 1. RAG: Buscar productos en MySQL con los campos reales
        $products = DB::table('products')
            ->select('code', 'name', 'brand_id', 'sale_price', 'stock', 'unit')
            ->where('name', 'LIKE', "%{$userMessage}%")
            ->orWhere('code', 'LIKE', "%{$userMessage}%")
            ->limit(5)
            ->get();

        // 2. Formatear el contexto
        $context = "INFORMACIÓN REAL DE INVENTARIO Y STOCK:\n";
        if ($products->isEmpty()) {
            $context .= "No se encontraron productos exactos en el catálogo para esta búsqueda.\n";
        } else {
            foreach ($products as $p) {
                $status = $p->stock > 0 ? "Disponible ({$p->stock} {$p->unit})" : "AGOTADO";
                $context .= "- SKU/Código: {$p->code} | {$p->name} | Precio: S/ {$p->sale_price} | Stock: {$status}\n";
            }
        }

        // 3. System Prompt
        $systemPrompt = "Eres 'ElectriBOT', el asistente virtual experto de ELECTRO REDES (tienda de materiales eléctricos en Moquegua).
        REGLAS ESTRICTAS:
        1. Responde de forma amable, profesional y concisa (máximo 3 oraciones por respuesta).
        2. Usa ÚNICAMENTE la siguiente información real del inventario para responder sobre precios y stock:
        {$context}
        3. Si el stock es 0 o el producto no está en el catálogo, o si el usuario pide hablar con una persona, incluye al final de tu respuesta la etiqueta especial: [ESCALAR_WHATSAPP].
        4. Todos los precios están expresados en Soles peruanos (S/).";

        // 4. Clave de API (Lectura segura)
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

        if (!$apiKey) {
            Log::error('Chatbot Error: GEMINI_API_KEY no está configurada en .env o config');
            return response()->json([
                'reply' => 'El servicio de IA no tiene configurada la credencial API.',
                'needs_human' => true
            ], 200);
        }

        // Endpoint oficial de Google Gemini 1.5 Flash
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout(10)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nPregunta del cliente: " . $userMessage]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                // Registrar el error exacto en storage/logs/laravel.log
                Log::error('Gemini API Error Response: ' . $response->body());

                return response()->json([
                    'reply' => 'Error de conexión con la IA de Google: ' . $response->status() . ' - ' . $response->json('error.message', 'Falla remota'),
                    'needs_human' => true
                ], 200);
            }

            $responseData = $response->json();
            $botReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'No pude procesar la respuesta.';

            $needsHuman = str_contains($botReply, '[ESCALAR_WHATSAPP]');
            $cleanReply = str_replace('[ESCALAR_WHATSAPP]', '', $botReply);

            return response()->json([
                'reply' => trim($cleanReply),
                'needs_human' => $needsHuman,
                'products' => $products
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot Exception: ' . $e->getMessage());
            return response()->json([
                'reply' => 'Excepción en servidor: ' . $e->getMessage(),
                'needs_human' => true
            ], 200);
        }
    }
}