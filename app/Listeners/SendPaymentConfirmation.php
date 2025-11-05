<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentCaptured $event): void
    {
        // Log pour tracking
        Log::info('Payment captured', [
            'payment_id' => $event->payment->id,
            'reservation_id' => $event->reservation->id,
            'amount' => $event->payment->amount,
        ]);

        // Si besoin, envoyer un email de confirmation de paiement
        // Pour l'instant, c'est géré par ReservationCompleted
    }
}

