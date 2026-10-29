<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\ApisPeruService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected ApisPeruService $apisPeruService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Iniciar consulta cargando relaciones necesarias
        $query = Order::with(['customer', 'details.product', 'payments', 'invoice']);

        // 2. Si no es administrador, filtrar únicamente sus órdenes
        $roleName = is_object($user?->role) ? $user->role->name : $user?->role;
        
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
        // 1. Crear la orden y descontar stock mediante OrderService
        $order = $this->orderService->create(
            $request->validated(),
            $request->user()
        );

        // 2. Cargar relaciones requeridas
        $order->load(['customer', 'details.product', 'payments', 'invoice']);

        // 3. Evaluar qué productos quedaron en o por debajo del stock mínimo
        $lowStockProducts = [];
        foreach ($order->details as $detail) {
            $product = $detail->product;
            if ($product && $product->stock <= $product->min_stock) {
                $lowStockProducts[] = [
                    'id'        => $product->id,
                    'name'      => $product->name,
                    'stock'     => (float) $product->stock,
                    'min_stock' => (float) $product->min_stock,
                ];
            }
        }

        // 4. Resolver el recurso de la orden e adjuntar el arreglo de bajo stock
        $responseData = (new OrderResource($order))->resolve($request);
        $responseData['low_stock_products'] = $lowStockProducts;

        return response()->json([
            'message' => 'Venta registrada correctamente.',
            'data'    => $responseData,
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $roleName = is_object($user?->role) ? $user->role->name : $user?->role;

        if (strtoupper($roleName ?? '') !== 'ADMIN') {
            $isOwner = $order->user_id === $user?->id || 
                       ($user?->customer && $order->customer_id === $user->customer->id);

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
     * Descarga de comprobantes delegada al servicio de APIsPeru
     */
    protected function downloadDocument(Request $request, Order $order, string $format)
    {
        $user = $request->user();
        $roleName = is_object($user?->role) ? $user->role->name : $user?->role;

        // Validar permisos de acceso
        if ($user && strtoupper($roleName ?? '') !== 'ADMIN') {
            $isOwner = $order->user_id === $user->id || 
                       ($user->customer && $order->customer_id === $user->customer->id);

            if (!$isOwner) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
        }

        // Cargar comprobante y sus detalles para armar el payload
        $order->loadMissing(['invoice.details']);

        if (!$order->invoice) {
            return response()->json([
                'message' => 'La orden no tiene un comprobante emitido asignado.'
            ], 404);
        }

        // Obtener el archivo binario desde APIsPeru
        $response = $format === 'pdf'
            ? $this->apisPeruService->getInvoicePdf($order->invoice)
            : $this->apisPeruService->getInvoiceXml($order->invoice);

        if ($response->failed()) {
            return response()->json([
                'message' => 'No se pudo obtener el archivo ' . strtoupper($format) . ' de APIsPeru.',
                'error'   => $response->json() ?? $response->body()
            ], $response->status());
        }

        // Entregar el archivo al navegador
        $filename    = "{$order->invoice->series}-{$order->invoice->number}.{$format}";
        $contentType = $format === 'xml' ? 'application/xml' : 'application/pdf';
        $disposition = $format === 'xml' ? 'attachment' : 'inline';

        return response($response->body(), 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}