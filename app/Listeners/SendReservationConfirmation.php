<?php

namespace App\Listeners;

use App\Events\ReservationCreated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendReservationConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(ReservationCreated $event): void
    {
        $this->notificationService->sendReservationConfirmation($event->reservation);
    }
}

