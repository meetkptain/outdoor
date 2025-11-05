<?php

namespace App\Listeners;

use App\Events\ReservationCompleted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendThankYouAndUpsell implements ShouldQueue
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
    public function handle(ReservationCompleted $event): void
    {
        // Tenter upsell photo/vidéo
        $this->notificationService->sendUpsellAfterFlight($event->reservation);

        // Email de remerciement
        $this->notificationService->sendThankYouEmail($event->reservation);
    }
}

