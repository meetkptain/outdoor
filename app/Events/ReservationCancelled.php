<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Reservation $reservation;
    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Reservation $reservation, string $reason)
    {
        $this->reservation = $reservation;
        $this->reason = $reason;
    }
}

