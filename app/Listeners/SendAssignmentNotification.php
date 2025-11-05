<?php

namespace App\Listeners;

use App\Events\ReservationScheduled;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAssignmentNotification implements ShouldQueue
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
    public function handle(ReservationScheduled $event): void
    {
        $this->notificationService->sendAssignmentNotification($event->reservation);
        $this->notificationService->scheduleReminder($event->reservation);
    }
}

