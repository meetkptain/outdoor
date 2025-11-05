<?php

namespace App\Listeners;

use App\Events\ReservationCancelled;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendCancellationNotification implements ShouldQueue
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
    public function handle(ReservationCancelled $event): void
    {
        $this->notificationService->sendCancellationNotification($event->reservation);
    }
}

