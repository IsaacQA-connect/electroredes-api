<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Iniciar consulta cargando relaciones necesarias
        $query = Order::with(['customer', 'details.product', 'payments', 'invoice']);

        // 2. Si no es administrador, filtrar únicamente sus órdenes
        $roleName = is_object($user->role) ? $user->role->name : $user->role;
        
        if (strtoupper($roleName ?? '') !== 'ADMIN') {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->customer) {
                    $q->orWhere('customer_id', $user->customer->id);
                }
            });
        }

        $orders = $query->latest()->get();

        return response()->json([
            'status' => true,
            'data'   => OrderResource::collection($orders)
        ], 200);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Venta registrada correctamente.',
            'data'    => new OrderResource($order->load(['customer', 'details.product', 'payments', 'invoice'])),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $roleName = is_object($user->role) ? $user->role->name : $user->role;

        if (strtoupper($roleName ?? '') !== 'ADMIN') {
            $isOwner = $order->user_id === $user->id || 
                       ($user->customer && $order->customer_id === $user->customer->id);

            if (!$isOwner) {
                return response()->json(['message' => 'No autorizado para ver esta orden.'], 403);
            }
        }

        $order->load([
            'customer',
            'user',
            'details.product',
            'payments',
            'invoice'
        ]);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $updatedOrder = $this->orderService->updateStatus(
            $order,
            $request->status,
            $request->user()
        );

        return new OrderResource($updatedOrder);
    }

    /**
     * Descargar Comprobante PDF de la Orden
     */
    public function downloadPdf(Request $request, Order $order)
    {
        return $this->downloadDocument($request, $order, 'pdf');
    }

    /**
     * Descargar Comprobante XML de la Orden
     */
    public function downloadXml(Request $request, Order $order)
    {
        return $this->downloadDocument($request, $order, 'xml');
    }

    /**
     * Descarga de comprobantes (proxy seguro con Bearer Token)
     */
    protected function downloadDocument(Request $request, Order $order, string $format)
    {
        $user = $request->user();
        $roleName = is_object($user->role) ? $user->role->name : $user->role;

        // Validar permisos de acceso
        if (strtoupper($roleName ?? '') !== 'ADMIN') {
            $isOwner = $order->user_id === $user->id || 
                       ($user->customer && $order->customer_id === $user->customer->id);

            if (!$isOwner) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
        }

        $order->loadMissing('invoice');

        // 1. Obtener serie, correlativo y tipo de documento
        $invoice = $order->invoice;
        $invoiceNumber = $order->invoice_number;

        $serie   = $invoice?->series ?? ($invoiceNumber ? explode('-', $invoiceNumber)[0] : null);
        $number  = $invoice?->number ?? ($invoiceNumber ? explode('-', $invoiceNumber)[1] : null);
        $tipoDoc = $invoice?->document_type ?? ($order->invoice_type === 'FACTURA' ? '01' : '03');

        if (!$serie || !$number) {
            return response()->json([
                'message' => 'La orden no tiene un comprobante emitido asignado.'
            ], 404);
        }

        // 2. Parámetros de APIsPeru
        $token   = config('services.apisperu.token');
        $ruc     = config('services.apisperu.ruc', config('app.company_ruc', '10723512552'));
        $baseUrl = config('services.apisperu.url', 'https://facturacion.apisperu.com/api/v1');

        $apiUrl = "{$baseUrl}/invoice/{$format}?ruc={$ruc}&tipoDoc={$tipoDoc}&serie={$serie}&correlativo={$number}";

        // 3. Petición autenticada a APIsPeru en segundo plano
        $response = Http::withToken($token)->get($apiUrl);

        if ($response->failed()) {
            return response()->json([
                'message' => 'No se pudo obtener el archivo ' . strtoupper($format) . ' de APIsPeru.',
                'error'   => $response->json('error') ?? $response->body()
            ], $response->status());
        }

        // 4. Entregar el archivo binario al navegador
        $filename = "{$serie}-{$number}.{$format}";
        $contentType = $format === 'xml' ? 'application/xml' : 'application/pdf';
        $disposition = $format === 'xml' ? 'attachment' : 'inline';

        return response($response->body(), 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}