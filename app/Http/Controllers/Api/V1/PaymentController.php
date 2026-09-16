<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;

class PaymentController extends Controller
{
    private function getAccessToken(): ?string
    {
        return config('services.mercadopago.access_token') ?? env('MERCADOPAGO_ACCESS_TOKEN');
    }

    public function createPreference(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return response()->json(['message' => 'Falta MERCADOPAGO_ACCESS_TOKEN en el .env'], 500);
            }
            MercadoPagoConfig::setAccessToken($accessToken);

            $order = Order::with('details.product', 'user')->findOrFail($request->order_id);

            if ($order->details->isEmpty()) {
                return response()->json([
                    'message' => 'La orden #' . $order->id . ' no tiene productos asociados.'
                ], 400);
            }

            $items = [];
            foreach ($order->details as $detail) {
                $items[] = [
                    'id' => (string) $detail->product_id,
                    'title' => $detail->product->name ?? 'Producto Electroredes',
                    'quantity' => (int) $detail->quantity,
                    'unit_price' => (float) ($detail->unit_price ?? $detail->price ?? 0),
                    'currency_id' => 'PEN',
                ];
            }

            $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

            $preferenceData = [
                'items' => $items,
                'external_reference' => (string) $order->id,
                'payer' => [
                    'email' => $order->user->email ?? 'cliente_prueba@test.com',
                    'name' => $order->user->name ?? 'Cliente',
                    'surname' => 'Prueba',
                ],
                'back_urls' => [
                    'success' => $frontendUrl . '/catalog?status=success',
                    'failure' => $frontendUrl . '/cart?status=failure',
                    'pending' => $frontendUrl . '/catalog?status=pending',
                ],
                'binary_mode' => true,
            ];

            if (str_starts_with($frontendUrl, 'https://')) {
                $preferenceData['auto_return'] = 'approved';
            }

            $client = new PreferenceClient();
            $preference = $client->create($preferenceData);

            return response()->json([
                'id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
            ]);

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $apiResponse = $e->getApiResponse();
            $errorContent = $apiResponse ? $apiResponse->getContent() : [];

            Log::error('Error Mercado Pago API:', ['status' => $e->getStatusCode(), 'response' => $errorContent]);

            return response()->json(['message' => 'Error en Mercado Pago', 'details' => $errorContent], 400);

        } catch (\Throwable $e) {
            Log::error('Error Servidor:', ['message' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        Log::info('Webhook Mercado Pago:', $request->all());

        try {
            $accessToken = $this->getAccessToken();
            if (empty($accessToken)) return response()->json(['status' => 'mock_active'], 200);

            MercadoPagoConfig::setAccessToken($accessToken);

            if ($request->get('type') === 'payment' || $request->get('topic') === 'payment') {
                $paymentId = $request->input('data.id') ?? $request->get('id');

                if ($paymentId) {
                    $client = new PaymentClient();
                    $mpPayment = $client->get($paymentId);

                    if ($mpPayment) {
                        $orderId = $mpPayment->external_reference;
                        $order = Order::find($orderId);

                        if ($order) {
                            $mpStatus = strtoupper($mpPayment->status);

                            // Mapeo a OrderStatus Enum
                            if ($mpStatus === 'APPROVED') {
                                $order->update(['status' => OrderStatus::COMPLETED->value]);
                            } elseif (in_array($mpStatus, ['REJECTED', 'CANCELLED'])) {
                                $order->update(['status' => OrderStatus::CANCELLED->value]);
                            }

                            // Mapeo a PaymentStatus Enum y uso de payment_date
                            $paymentStatus = match ($mpStatus) {
                                'APPROVED' => PaymentStatus::APPROVED->value,
                                'REJECTED' => PaymentStatus::REJECTED->value,
                                'CANCELLED' => PaymentStatus::CANCELLED->value,
                                'REFUNDED' => PaymentStatus::REFUNDED->value,
                                default => PaymentStatus::PENDING->value,
                            };

                            Payment::updateOrCreate(
                                ['order_id' => $order->id],
                                [
                                    'method' => 'ONLINE_PAYMENT',
                                    'amount' => $mpPayment->transaction_amount,
                                    'status' => $paymentStatus,
                                    'transaction_code' => (string) $mpPayment->id,
                                    'payment_date' => $mpPayment->date_approved ?? now(),
                                ]
                            );
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error en Webhook MP: ' . $e->getMessage());
        }

        return response()->json(['status' => 'ok'], 200);
    }

    public function confirmPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_id' => 'required',
            'status' => 'required',
        ]);

        $order = Order::findOrFail($request->order_id);
        $rawStatus = strtoupper($request->status);

        // Mapear de la respuesta URL a Enums válidos
        if (in_array($rawStatus, ['APPROVED', 'SUCCESS'])) {
            $order->update(['status' => OrderStatus::COMPLETED->value]);
            $paymentStatus = PaymentStatus::APPROVED->value;
        } else {
            $paymentStatus = PaymentStatus::PENDING->value;
        }

        $payment = Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'method' => 'ONLINE_PAYMENT',
                'status' => $paymentStatus,
                'transaction_code' => (string) $request->payment_id,
                'payment_date' => now(),
            ]
        );

        return response()->json(['message' => 'Pago actualizado correctamente', 'payment' => $payment]);
    }
}