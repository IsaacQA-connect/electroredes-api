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

        $userMessage = trim($request->input('message'));

        // 1. Limpieza y tokenización de palabras clave
        $stopWords = ['tienen', 'busco', 'precio', 'de', 'del', 'para', 'con', 'el', 'la', 'los', 'las', 'un', 'una', 'en', 'hay', 'cotizar', 'cuanto', 'cuesta'];
        $cleanMessage = preg_replace('/[^\w\s]/u', '', mb_strtolower($userMessage));
        $rawWords = explode(' ', $cleanMessage);
        
        $keywords = array_values(array_filter($rawWords, function($word) use ($stopWords) {
            return strlen($word) >= 3 && !in_array($word, $stopWords);
        }));

        // 2. Consulta RAG optimizada en MySQL
        $query = DB::table('products')
            ->select('products.code', 'products.name', 'products.sale_price', 'products.stock', 'products.unit');

        if (!empty($keywords)) {
            $query->where(function($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhere('products.name', 'LIKE', "%{$word}%")
                      ->orWhere('products.code', 'LIKE', "%{$word}%");
                }
            });
        } else {
            $query->where('products.name', 'LIKE', "%{$userMessage}%");
        }

        $products = $query->limit(5)->get();

        // 3. Formatear contexto para el Prompt
        $context = "INFORMACIÓN REAL DE INVENTARIO Y STOCK:\n";
        if ($products->isEmpty()) {
            $context .= "No se encontraron productos exactos en el catálogo para esta búsqueda.\n";
        } else {
            foreach ($products as $p) {
                $status = $p->stock > 0 ? "Disponible ({$p->stock} {$p->unit})" : "AGOTADO";
                $context .= "- Código: {$p->code} | {$p->name} | Precio: S/ {$p->sale_price} | Stock: {$status}\n";
            }
        }

        // 4. System Prompt refinado
        $systemPrompt = "Eres 'ElectroBOT', el asistente virtual experto de ELECTRO REDES (tienda de materiales eléctricos en Moquegua).
        REGLAS ESTRICTAS:
        1. Responde de forma amable, profesional y concisa (máximo 3 oraciones por respuesta).
        2. Usa ÚNICAMENTE la siguiente información real del inventario para responder sobre precios y stock:
        {$context}
        3. Si la lista de productos está vacía, el stock es 0 o el usuario solicita atención personalizada, incluye al final de tu respuesta obligatoriamente la etiqueta: [ESCALAR_WHATSAPP].
        4. Todos los precios están expresados en Soles peruanos (S/).";

        // 5. Clave API y llamada HTTP
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

        if (!$apiKey) {
            Log::error('Chatbot Error: GEMINI_API_KEY no está configurada.');
            return response()->json([
                'reply' => 'El servicio de IA no tiene configurada la credencial API.',
                'needs_human' => true
            ], 200);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nPregunta del cliente: " . $userMessage]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
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