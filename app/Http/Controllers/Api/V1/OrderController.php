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
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Iniciar consulta cargando relaciones necesarias (evita problema N+1)
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

        // 3. Ordenar descendentemente
        $orders = $query->latest()->get();

        return response()->json([
            'status' => true,
            'data'   => OrderResource::collection($orders)
        ], 200);
    }

    public function store(
        StoreOrderRequest $request
    ): JsonResponse {
        
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

        // Validar permisos de acceso
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
     * Lógica compartida para la redirección y descarga de comprobantes
     */
    protected function downloadDocument(Request $request, Order $order, string $format)
    {
        $user = $request->user();
        $roleName = is_object($user->role) ? $user->role->name : $user->role;

        if (strtoupper($roleName ?? '') !== 'ADMIN') {
            $isOwner = $order->user_id === $user->id || 
                       ($user->customer && $order->customer_id === $user->customer->id);

            if (!$isOwner) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
        }

        $urlField = $format === 'xml' ? 'xml_url' : 'pdf_url';
        
        // 1. Obtener URL guardada en la orden o en el modelo Invoice
        $fileUrl = $order->{$urlField} ?? $order->invoice?->{$urlField};

        // 2. Si no existe la URL directa pero se tiene la serie y correlativo, construir la URL de APIsPeru
        if (!$fileUrl) {
            $invoiceNumber = $order->invoice_number ?? null;
            $serie   = $order->invoice?->series ?? ($invoiceNumber ? explode('-', $invoiceNumber)[0] : null);
            $number  = $order->invoice?->number ?? ($invoiceNumber ? explode('-', $invoiceNumber)[1] : null);
            $tipoDoc = $order->invoice?->document_type ?? ($order->invoice_type === 'FACTURA' ? '01' : '03');

            if ($serie && $number) {
                $ruc = config('services.apisperu.ruc', config('app.company_ruc', '20000000000'));
                $baseUrl = config('services.apisperu.url', 'https://facturacion.apisperu.com/api/v1');
                $fileUrl = "{$baseUrl}/invoice/{$format}?ruc={$ruc}&tipoDoc={$tipoDoc}&serie={$serie}&correlativo={$number}";
            }
        }

        if (!$fileUrl) {
            return response()->json([
                'message' => 'El comprobante ' . strtoupper($format) . ' aún no está disponible.'
            ], 404);
        }

        return redirect()->away($fileUrl);
    }
}