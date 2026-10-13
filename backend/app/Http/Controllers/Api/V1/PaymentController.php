<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Support\PaymentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request): JsonResponse
    {
        if (! PaymentSettings::enabled()) {
            return response()->json(['message' => 'Online payments are not available right now. Please contact us for bank transfer details.'], 503);
        }
        $settings = PaymentSettings::settings();
        $payment = Payment::create([
            ...Arr::except($request->validated(), ['website', 'amount']),
            'gateway' => $settings['gateway'],
            'currency' => $settings['currency'],
            'amount' => (int) round((float) $request->validated('amount') * 100),
        ]);

        try {
            $checkout = PaymentSettings::for($payment->gateway)->checkout($payment);
        } catch (Throwable $e) {
            Log::warning('Payment checkout could not be opened', ['reference' => $payment->reference, 'error' => $e->getMessage()]);
            $payment->markFailed('The gateway could not open the checkout');

            return response()->json(['message' => 'We could not open the payment page. Please try again in a minute, or contact us.'], 502);
        }

        return response()->json(['data' => [...$payment->forPayer(), 'checkout' => $checkout]], 201);
    }

    /** Called by the payer's browser after the checkout; the gateway is asked before anything is marked paid. */
    public function confirm(Request $request, string $reference): JsonResponse
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        if ($payment->status !== 'paid') {
            rescue(fn () => PaymentSettings::for($payment->gateway)->confirm($payment, $request->all()));
        }

        return response()->json(['data' => $payment->forPayer()]);
    }

    /** The gateway's own server-to-server report: catches payments whose browser closed before returning to the site. */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        $handler = PaymentSettings::for($gateway);
        if (! $handler->validWebhook($request)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $outcome = $handler->webhookOutcome($request->json()->all());
        $payment = $outcome ? Payment::where('gateway', $gateway)->where('gateway_order_id', $outcome['order'])->first() : null;
        if ($payment) {
            $outcome['status'] === 'paid' ? $payment->markPaid($outcome['payment']) : $payment->markFailed($outcome['reason']);
        }

        return response()->json(['received' => true]);
    }
}
