<?php

namespace App\Providers;

use App\Events\PaymentCaptured;
use App\Events\ReservationCancelled;
use App\Events\ReservationCompleted;
use App\Events\ReservationCreated;
use App\Events\ReservationScheduled;
use App\Listeners\SendAssignmentNotification;
use App\Listeners\SendCancellationNotification;
use App\Listeners\SendPaymentConfirmation;
use App\Listeners\SendReservationConfirmation;
use App\Listeners\SendThankYouAndUpsell;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ReservationCreated::class => [
            SendReservationConfirmation::class,
        ],
        ReservationScheduled::class => [
            SendAssignmentNotification::class,
        ],
        PaymentCaptured::class => [
            SendPaymentConfirmation::class,
        ],
        ReservationCompleted::class => [
            SendThankYouAndUpsell::class,
        ],
        ReservationCancelled::class => [
            SendCancellationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

