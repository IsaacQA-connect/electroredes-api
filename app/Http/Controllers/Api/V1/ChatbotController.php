<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private string $whatsappNumber = '51933437116';
    private string $storeAddress = 'Asoc. 8 de diciembre Mz-H1 Lt-8 - San Antonio - Moquegua';

    public function query(Request $request)
    {
        $request->validate([
            'message'    => 'required|string|max:500',
            'session_id' => 'nullable|string|max:100',
        ]);

        $userMessage = trim($request->input('message'));

        // 1. Identificador de sesión automático
        $sessionId = $request->input('session_id')
            ?: 'guest_' . hash('sha256', $request->ip() . '|' . $request->userAgent());
        $cacheKey = "electribot_session_{$sessionId}";

        $session = Cache::get($cacheKey, [
            'history'             => [],
            'unresolved_attempts' => 0,
        ]);

        // 2. Escudo Anti-Tokens: Respuesta instantánea para saludos puros (0 tokens)
        if (preg_match('/^(hola|buenos dias|buenas tardes|buenas noches|buenas|hey|que tal)[!.\s]*$/i', $userMessage)) {
            return response()->json([
                'reply'       => '¡Hola! Soy ElectriBOT, asesor comercial de ELECTROREDES en Moquegua. ¿Qué producto, material eléctrico o cotización necesitas hoy?',
                'needs_human' => false,
                'products'    => []
            ]);
        }

        // 3. Escalado automático a WhatsApp al 3er intento sin solución
        if ($session['unresolved_attempts'] >= 3) {
            Cache::forget($cacheKey);
            $waUrl = $this->buildWhatsappUrl("Hola ELECTROREDES, necesito asesoría personalizada sobre: {$userMessage}");

            return response()->json([
                'reply'        => 'Para darte una solución exacta y revisar disponibilidad especial en almacén, te comunico directamente con uno de nuestros asesores comerciales en Moquegua.',
                'needs_human'  => true,
                'whatsapp_url' => $waUrl,
                'products'     => []
            ]);
        }

        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('GEMINI_API_KEY no configurada.');
            return $this->fallbackResponse($userMessage);
        }

        // Modelo vigente de baja latencia y bajo costo
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

        // 4. System Prompt alineado al catálogo real y ahorro de tokens
        $systemInstruction = "Eres 'ElectriBOT', asesor técnico-comercial de ELECTROREDES en Moquegua (Dirección: {$this->storeAddress}, WhatsApp: 933437116).

OBJETIVOS COMERCIALES:
1. Brindar asesoría técnica y comercial sobre los productos de la tienda (materiales eléctricos, ferretería eléctrica, equipos de cómputo/tecnología, librería y artículos del catálogo).
2. Realizar cotizaciones preliminares multiplicando precio unitario por cantidad solicitada (precios en Soles S/).
3. Calificar prospectos: si piden cotización formal o compra por volumen, solicita nombre, empresa/RUC y teléfono.

REGLAS ESTRICTAS:
- Responde SOLO consultas relacionadas con productos de ELECTROREDES, asesoría técnica de uso, cotizaciones, envíos, horarios o ubicación.
- Ante temas ajenos (política, recetas, programación, chistes, tareas), responde en UNA oración: 'Solo puedo ayudarte con consultas y cotizaciones de productos en ELECTROREDES.'
- Usa SIEMPRE la herramienta 'buscar_productos_catalogo' cuando pregunten por productos, marcas, precios o stock. Jamás inventes precios ni existencias.
- Revisa el campo 'stock_disponible' antes de confirmar disponibilidad: si es 0, indica que actualmente está agotado pero puede consultarse reposición por WhatsApp.
- Si la consulta es muy amplia (ej. 'necesito cable'), muestra las opciones halladas y haz 1 pregunta breve para precisar (calibre, medida o modelo).
- Sé claro, amable y conciso (máximo 4 a 5 oraciones).";

        // 5. Declaración de Herramienta (Function Calling)
        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name'        => 'buscar_productos_catalogo',
                        'description' => 'Busca productos activos en el inventario de ELECTROREDES por nombre, código, marca o categoría.',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'termino' => [
                                    'type'        => 'STRING',
                                    'description' => 'Palabra clave principal del producto (ej: monitor, cable, termomagnetica, energia, router).'
                                ],
                                'marca' => [
                                    'type'        => 'STRING',
                                    'description' => 'Nombre de la marca solo si el cliente la mencionó explícitamente.'
                                ],
                                'categoria' => [
                                    'type'        => 'STRING',
                                    'description' => 'Categoría solo si el cliente la mencionó explícitamente.'
                                ]
                            ],
                            'required' => ['termino']
                        ]
                    ]
                ]
            ]
        ];

        $contents = $session['history'];
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        $foundProducts = collect();
        $searchWasEmpty = false;

        try {
            // Primera llamada a Gemini
            $response = Http::timeout(20)->post($url, [
                'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents'          => $contents,
                'tools'             => $tools,
                'generationConfig'  => [
                    'temperature'     => 0.2,
                    'maxOutputTokens' => 550,
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini Error 1: ' . $response->body());
                return $this->fallbackResponse($userMessage);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0]['content'] ?? null;
            $parts = $candidate['parts'] ?? [];

            // Buscar functionCall en cualquiera de las partes de la respuesta
            $fnCall = null;
            foreach ($parts as $p) {
                if (isset($p['functionCall'])) {
                    $fnCall = $p['functionCall'];
                    break;
                }
            }

            // 6. Ejecutar búsqueda en MySQL si Gemini invocó la herramienta
            if ($fnCall) {
                $args = $fnCall['args'] ?? [];

                $foundProducts = $this->searchProductsInDatabase(
                    $args['termino'] ?? $userMessage,
                    $args['marca'] ?? null,
                    $args['categoria'] ?? null
                );

                $searchWasEmpty = $foundProducts->isEmpty();

                // Payload ligero para Gemini (ahorra tokens omitiendo URLs de S3 e IDs internos)
                $compactResults = $foundProducts->map(fn($p) => [
                    'codigo'           => $p->code,
                    'nombre'           => $p->name,
                    'descripcion'      => $p->description,
                    'marca'            => $p->brand_name ?? 'Genérico',
                    'categoria'        => $p->category_name ?? 'General',
                    'precio_soles'     => (float) $p->sale_price,
                    'unidad'           => $this->formatSunatUnit($p->unit),
                    'stock_disponible' => (float) $p->stock,
                ])->values()->toArray();

                $contents[] = $candidate;
                $contents[] = [
                    'role'  => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name'     => 'buscar_productos_catalogo',
                                'response' => [
                                    'resultados' => empty($compactResults)
                                        ? 'Sin coincidencias en el catálogo.'
                                        : $compactResults
                                ]
                            ]
                        ]
                    ]
                ];

                // Segunda llamada a Gemini con resultados reales
                $secondResponse = Http::timeout(20)->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents'          => $contents,
                    'generationConfig'  => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 550,
                    ]
                ]);

                if ($secondResponse->failed()) {
                    Log::error('Gemini Error 2: ' . $secondResponse->body());
                    return $this->fallbackResponse($userMessage);
                }

                $finalParts = $secondResponse->json('candidates.0.content.parts', []);
                $botReply = collect($finalParts)->pluck('text')->filter()->implode("\n")
                    ?: 'No pude procesar los datos del catálogo.';
            } else {
                $botReply = collect($parts)->pluck('text')->filter()->implode("\n")
                    ?: '¿En qué producto o cotización puedo ayudarte?';
            }

            // 7. Actualizar contador de intentos y memoria de sesión
            if ($searchWasEmpty) {
                $session['unresolved_attempts'] += 1;
            } elseif ($foundProducts->isNotEmpty()) {
                $session['unresolved_attempts'] = 0;
            }

            $session['history'][] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];
            $session['history'][] = ['role' => 'model', 'parts' => [['text' => $botReply]]];
            $session['history'] = array_slice($session['history'], -6);

            Cache::put($cacheKey, $session, now()->addMinutes(30));

            $lowerMsg = mb_strtolower($userMessage);
            $needsHuman = ($session['unresolved_attempts'] >= 3)
                || str_contains($lowerMsg, 'asesor')
                || str_contains($lowerMsg, 'humano')
                || str_contains($lowerMsg, 'whatsapp');

            $waUrl = $needsHuman
                ? $this->buildWhatsappUrl("Hola ELECTROREDES, vengo del chat web. Consulta: {$userMessage}")
                : null;

            if ($session['unresolved_attempts'] >= 3) {
                $botReply .= "\n\nNo logré ubicar ese requerimiento exacto en el catálogo digital. Puedes continuar directamente con un asesor en WhatsApp para atención inmediata.";
                Cache::forget($cacheKey);
            }

            return response()->json([
                'reply'        => trim($botReply),
                'needs_human'  => $needsHuman,
                'whatsapp_url' => $waUrl,
                'attempts'     => $session['unresolved_attempts'],
                'products'     => $foundProducts->values()
            ]);

        } catch (\Throwable $e) {
            Log::error('Chatbot Exception: ' . $e->getMessage());
            return $this->fallbackResponse($userMessage);
        }
    }

    /**
     * Búsqueda en dos fases: coincidencia exacta y respaldo flexible si no hay resultados
     */
    private function searchProductsInDatabase(string $termino, ?string $marca = null, ?string $categoria = null): Collection
    {
        $cleanTerm = trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($termino)));
        $words = array_values(array_filter(
            preg_split('/\s+/', $cleanTerm),
            fn($w) => mb_strlen($w) >= 2
        ));

        // Fase 1: Búsqueda estricta (todas las palabras + filtros de marca/categoría)
        $results = $this->runProductQuery($words, $marca, $categoria, strict: true);

        // Fase 2: Si no hubo coincidencias, relajar filtros de categoría/marca y buscar por coincidencia parcial (OR)
        if ($results->isEmpty() && (!empty($words) || !empty($marca) || !empty($categoria))) {
            $fallbackWords = !empty($words) ? $words : array_filter([$marca, $categoria]);
            $results = $this->runProductQuery($fallbackWords, null, null, strict: false);
        }

        return $results;
    }

    private function runProductQuery(array $words, ?string $marca, ?string $categoria, bool $strict): Collection
    {
        $query = DB::table('products')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id',
                'products.code',
                'products.barcode',
                'products.name',
                'products.description',
                'products.unit',
                'products.sale_price',
                'products.stock',
                'products.image_path',
                'brands.name as brand_name',
                'categories.name as category_name'
            )
            ->where('products.status', 1);

        if (!empty($words)) {
            $query->where(function ($q) use ($words, $strict) {
                foreach ($words as $word) {
                    $method = $strict ? 'where' : 'orWhere';
                    $q->{$method}(function ($sub) use ($word) {
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

        return $query->orderByDesc('products.stock')
            ->limit(5)
            ->get();
    }

    private function formatSunatUnit(?string $unit): string
    {
        return match (strtoupper((string) $unit)) {
            'NIU'   => 'unidad',
            'ZZ'    => 'servicio',
            'MTR'   => 'metro',
            'BX'    => 'caja',
            'PK'    => 'paquete',
            'KGM'   => 'kilogramo',
            'LTR'   => 'litro',
            'DZN'   => 'docena',
            'GLL'   => 'galón',
            'SET'   => 'juego',
            default => $unit ?: 'unidad',
        };
    }

    private function buildWhatsappUrl(string $text): string
    {
        return "https://wa.me/{$this->whatsappNumber}?text=" . urlencode($text);
    }

    private function fallbackResponse(string $userMessage)
    {
        return response()->json([
            'reply'        => 'En este momento tengo inconvenientes para consultar el inventario en línea. ¿Deseas comunicarte directamente con un asesor por WhatsApp?',
            'needs_human'  => true,
            'whatsapp_url' => $this->buildWhatsappUrl("Hola ELECTROREDES, necesito ayuda con: {$userMessage}"),
            'products'     => []
        ], 200);
    }
}