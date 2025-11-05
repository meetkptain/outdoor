<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCaptured
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public Reservation $reservation;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, Reservation $reservation)
    {
        $this->payment = $payment;
        $this->reservation = $reservation;
    }
}

