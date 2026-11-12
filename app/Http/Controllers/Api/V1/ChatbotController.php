<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private string $whatsappNumber = '51933437116';
    private string $storeAddress = 'Asoc. 8 de diciembre Mz-H1 Lt-8 - San Antonio - Moquegua';

    public function query(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'session_id' => 'nullable|string|max:100',
        ]);

        $userMessage = trim($request->input('message'));

        // 1. Identificador de sesión automático (si Vue 3 no envía session_id, usa IP + User-Agent)
        $sessionId = $request->input('session_id') 
            ?: 'guest_' . hash('sha256', $request->ip() . '|' . $request->userAgent());
        $cacheKey = "electribot_session_{$sessionId}";

        // Recuperar estado de la conversación (expira en 30 min de inactividad)
        $session = Cache::get($cacheKey, [
            'history' => [],
            'unresolved_attempts' => 0,
            'last_topic' => ''
        ]);

        // 2. Escudo Anti-Tokens: Respuesta instantánea para saludos puros (0 tokens consumidos)
        if (preg_match('/^(hola|buenos dias|buenas tardes|buenas noches|buenas|hey|que tal)[!.\s]*$/i', $userMessage)) {
            return response()->json([
                'reply' => "¡Hola! Soy ElectriBOT, asesor comercial de ELECTROREDES en Moquegua. ¿Qué material eléctrico, cableado o cotización necesitas hoy?",
                'needs_human' => false,
                'products' => []
            ]);
        }

        // 3. Control de Escalado Automático al 3er intento sin solución
        if ($session['unresolved_attempts'] >= 3) {
            Cache::forget($cacheKey); // Reiniciar contador tras derivar
            $waUrl = $this->buildWhatsappUrl("Hola ELECTROREDES, necesito asesoría personalizada sobre: {$userMessage}");
            
            return response()->json([
                'reply' => "Para darte una solución exacta y revisar disponibilidad especial en almacén, te comunico directamente con uno de nuestros asesores comerciales en Moquegua.",
                'needs_human' => true,
                'whatsapp_url' => $waUrl,
                'products' => []
            ]);
        }

        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('GEMINI_API_KEY no configurada.');
            return response()->json(['reply' => 'Servicio en mantenimiento.', 'needs_human' => true], 200);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

        // 4. System Prompt Comercial y Guardrails Estrictos
        $systemInstruction = "Eres 'ElectriBOT', asesor técnico-comercial de ELECTROREDES (tienda de materiales eléctricos en Moquegua, dirección: {$this->storeAddress}, WhatsApp: 933437116).

        OBJETIVOS COMERCIALES:
        1. Brindar asesoría técnica preventiva (recomendar calibres, llaves termomagnéticas, tuberías o accesorios compatibles).
        2. Realizar cotizaciones preliminares multiplicando precio unitario por cantidad solicitada (precios en Soles S/).
        3. Calificar prospectos: si piden cotización formal o volumen mayorista, pide nombre, empresa/RUC y teléfono.

        REGLAS ESTRICTAS DE DOMINIO Y AHORRO DE TOKENS:
        - SOLO respondes consultas sobre materiales eléctricos, ferretería eléctrica, cotizaciones, horarios o ubicación de ELECTROREDES.
        - Si el usuario pregunta temas ajenos (política, recetas, código, chistes, tareas, otros rubros), responde en UNA sola oración: 'Solo puedo ayudarte con consultas y cotizaciones de materiales eléctricos en ELECTROREDES.'
        - Usa SIEMPRE la herramienta 'buscar_productos_catalogo' cuando el cliente pregunte por un producto, marca, precio o stock. Nunca inventes precios ni productos.
        - Si la consulta del cliente es ambigua (ej. 'necesito cable' o 'quiero una llave'), haz 1 pregunta técnica breve para precisar (ej. calibre, amperaje o uso) mientras muestras opciones si las hay.
        - Sé directo y conciso (máximo 3 a 4 oraciones).";

        // 5. Declaración de Herramienta (Function Calling)
        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'buscar_productos_catalogo',
                        'description' => 'Busca productos activos en el inventario MySQL de ELECTROREDES por nombre, código, marca o categoría.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'termino' => [
                                    'type' => 'STRING',
                                    'description' => 'Palabra clave limpia del producto o característica técnica (ej: THW 14, termomagnetica, tubo pvc, cinta aislante).'
                                ],
                                'marca' => [
                                    'type' => 'STRING',
                                    'description' => 'Nombre de la marca si el cliente la mencionó (ej: Indeco, Schneider, Bticino, Pavco).'
                                ],
                                'categoria' => [
                                    'type' => 'STRING',
                                    'description' => 'Categoría general si aplica (ej: cables, iluminacion, tableros).'
                                ]
                            ],
                            'required' => ['termino']
                        ]
                    ]
                ]
            ]
        ];

        // Construir historial de conversación para memoria de contexto (últimos 6 turnos)
        $contents = $session['history'];
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        $foundProducts = [];
        $searchWasEmpty = false;

        try {
            // Primera llamada a Gemini (Evaluación e intención de uso de herramienta)
            $response = Http::timeout(20)->post($url, [
                'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents' => $contents,
                'tools' => $tools,
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 300,
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini Error 1: ' . $response->body());
                return $this->fallbackResponse($userMessage);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0]['content'] ?? null;
            $part = $candidate['parts'][0] ?? [];

            // 6. Verificar si Gemini decidió llamar a nuestra función de MySQL
            if (isset($part['functionCall'])) {
                $fnCall = $part['functionCall'];
                $args = $fnCall['args'] ?? [];

                // Ejecutar búsqueda real en MySQL cruzando products, brands y categories
                $foundProducts = $this->searchProductsInDatabase(
                    $args['termino'] ?? '',
                    $args['marca'] ?? null,
                    $args['categoria'] ?? null
                );

                if ($foundProducts->isEmpty()) {
                    $searchWasEmpty = true;
                }

                // Agregar la llamada y la respuesta de la función al flujo de Gemini
                $contents[] = $candidate;
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => 'buscar_productos_catalogo',
                                'response' => [
                                    'resultados' => $foundProducts->isEmpty() 
                                        ? 'Sin coincidencias exactas en stock.' 
                                        : $foundProducts->toArray()
                                ]
                            ]
                        ]
                    ]
                ];

                // Segunda llamada a Gemini con los datos reales de MySQL ya inyectados
                $secondResponse = Http::timeout(20)->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 300,
                    ]
                ]);

                if ($secondResponse->failed()) {
                    Log::error('Gemini Error 2: ' . $secondResponse->body());
                    return $this->fallbackResponse($userMessage);
                }

                $finalData = $secondResponse->json();
                $botReply = $finalData['candidates'][0]['content']['parts'][0]['text'] ?? 'No pude procesar los datos del catálogo.';
            } else {
                // Gemini respondió directamente (ej. pregunta técnica general, dirección o rechazo de tema ajeno)
                $botReply = $part['text'] ?? '¿En qué material eléctrico puedo ayudarte?';
            }

            // 7. Actualizar contador de intentos no resueltos y memoria en Caché
            if ($searchWasEmpty) {
                $session['unresolved_attempts'] += 1;
            } elseif (!empty($foundProducts) && $foundProducts->isNotEmpty()) {
                $session['unresolved_attempts'] = 0; // Resetear contador si hubo éxito
            }

            // Guardar historial limpio (solo texto para no inflar tokens en siguientes turnos)
            $session['history'][] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];
            $session['history'][] = ['role' => 'model', 'parts' => [['text' => $botReply]]];
            $session['history'] = array_slice($session['history'], -6); // Máximo últimos 3 intercambios

            Cache::put($cacheKey, $session, now()->addMinutes(30));

            // Verificar si ya alcanzó el límite en este turno o pide asesor humano
            $needsHuman = ($session['unresolved_attempts'] >= 3) || str_contains(mb_strtolower($userMessage), 'asesor') || str_contains(mb_strtolower($userMessage), 'humano');
            $waUrl = $needsHuman ? $this->buildWhatsappUrl("Hola ELECTROREDES, vengo del chat web. Consulta: {$userMessage}") : null;

            if ($needsHuman && $session['unresolved_attempts'] >= 3) {
                $botReply .= "\n\nHe intentado ubicar ese requerimiento específico sin éxito en el catálogo digital. Puedes continuar directamente con un asesor en WhatsApp para atención inmediata.";
                Cache::forget($cacheKey);
            }

            return response()->json([
                'reply' => trim($botReply),
                'needs_human' => $needsHuman,
                'whatsapp_url' => $waUrl,
                'attempts' => $session['unresolved_attempts'],
                'products' => $foundProducts
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot Exception: ' . $e->getMessage());
            return $this->fallbackResponse($userMessage);
        }
    }

    /**
     * Consulta optimizada cruzando products, brands y categories
     */
    private function searchProductsInDatabase(string $termino, ?string $marca = null, ?string $categoria = null)
    {
        $query = DB::table('products')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id',
                'products.code',
                'products.name',
                'products.description',
                'products.unit',
                'products.sale_price',
                'products.stock',
                'products.image_path',
                'brands.name as brand_name',
                'categories.name as category_name'
            )
            ->where('products.status', 1); // Solo productos activos

        // Limpiar palabras del término extraído por Gemini
        $cleanTerm = trim(preg_replace('/[^\w\s]/u', '', mb_strtolower($termino)));
        $words = array_filter(explode(' ', $cleanTerm), fn($w) => mb_strlen($w) >= 2);

        if (!empty($words)) {
            $query->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word) {
                        $sub->where('products.name', 'LIKE', "%{$word}%")
                            ->orWhere('products.description', 'LIKE', "%{$word}%")
                            ->orWhere('products.code', 'LIKE', "%{$word}%")
                            ->orWhere('brands.name', 'LIKE', "%{$word}%")
                            ->orWhere('categories.name', 'LIKE', "%{$word}%");
                    });
                }
            });
        }

        if (!empty($marca)) {
            $query->where('brands.name', 'LIKE', "%{$marca}%");
        }

        if (!empty($categoria)) {
            $query->where('categories.name', 'LIKE', "%{$categoria}%");
        }

        // Priorizar productos con stock disponible
        return $query->orderByDesc('products.stock')
            ->limit(5)
            ->get();
    }

    private function buildWhatsappUrl(string $text): string
    {
        return "https://wa.me/{$this->whatsappNumber}?text=" . urlencode($text);
    }

    private function fallbackResponse(string $userMessage)
    {
        return response()->json([
            'reply' => 'En este momento tengo inconvenientes para consultar el inventario en línea. ¿Deseas comunicarte directamente con un asesor por WhatsApp?',
            'needs_human' => true,
            'whatsapp_url' => $this->buildWhatsappUrl("Hola ELECTROREDES, necesito ayuda con: {$userMessage}"),
            'products' => []
        ], 200);
    }
}