<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\HandleWebhookCall;
use App\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PaymentWebhookController extends Controller
{
    /**
     * Take a payment provider's webhook. Only a correctly signed one is stored and handled. A 2xx tells the
     * provider it's done; a 500 makes it try again later; a 400 tells it never to resend.
     */
    public function __invoke(Request $request, string $provider, PaymentManager $payments, HandleWebhookCall $handleWebhookCall): Response
    {
        try {
            $gateway = $payments->driver($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        if (! $gateway->verifyWebhook($request)) {
            Log::warning('Rejected a payment webhook whose signature did not check out.', ['provider' => $provider, 'ip' => $request->ip()]);

            return response('Invalid signature', 400);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response('Invalid body', 400);
        }

        $call = $handleWebhookCall->store($provider, $payload);

        if ($call->processed_at !== null) {
            return response('Already handled');
        }

        try {
            $handleWebhookCall->handle($call);
        } catch (Throwable $exception) {
            report($exception);

            return response('Could not handle this event yet', 500);
        }

        return response('OK');
    }
}
