<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Genera la referencia o enlace de cobro de la pasarela para la orden especificada.
     */
    public function createPreference(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        try {
            $order = Order::with('details')->findOrFail($request->order_id);

            // TODO: Sustituir la URL ficticia por la integración con el SDK oficial de tu pasarela 
            // (Mercado Pago, Stripe, Culqi, Niubiz, etc.)
            $checkoutUrl = "https://sandbox.pasarela.com/checkout/pay?pref_id=PREF_" . $order->id . "_" . time();

            return response()->json([
                'status' => 'success',
                'order_id' => $order->id,
                'preference_id' => 'PREF_' . $order->id . '_' . time(),
                'init_point' => $checkoutUrl, // URL leída por CheckoutView.vue
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar preferencia de pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la notificación Webhook asíncrona enviada por la pasarela al confirmar el pago.
     */
    public function handleWebhook(Request $request)
    {
        Log::info('Webhook de pago recibido:', $request->all());

        $data = $request->all();

        // Procesar estado del pago
        if (isset($data['order_id']) && isset($data['status'])) {
            $order = Order::find($data['order_id']);

            if ($order && strtolower($data['status']) === 'approved') {
                $order->update([
                    'status' => 'COMPLETADO',
                ]);
            }
        }

        return response()->json(['status' => 'received'], 200);
    }
}