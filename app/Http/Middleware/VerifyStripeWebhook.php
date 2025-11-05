<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyStripeWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // En environnement de test, bypasser la vérification si la signature est 'test_signature'
        if (app()->environment('testing') && $request->header('Stripe-Signature') === 'test_signature') {
            return $next($request);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        if (!$sigHeader || !$endpointSecret) {
            Log::error('Stripe webhook: Missing signature or secret');
            return response()->json(['error' => 'Missing webhook configuration'], 400);
        }

        try {
            Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
                'sig_header' => $sigHeader,
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook verification error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Webhook verification failed'], 400);
        }

        return $next($request);
    }
}
