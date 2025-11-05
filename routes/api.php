<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\ReservationController;
use App\Http\Controllers\Api\v1\PaymentController;
use App\Http\Controllers\Api\v1\BiplaceurController; // @deprecated - Utilisez InstructorController
use App\Http\Controllers\Api\v1\InstructorController;
use App\Http\Controllers\Api\v1\ActivityController;
use App\Http\Controllers\Api\v1\ActivitySessionController;
use App\Http\Controllers\Api\v1\ClientController;
use App\Http\Controllers\Api\v1\DashboardController;
use App\Http\Controllers\Api\v1\OptionController;
use App\Http\Controllers\Api\v1\CouponController;
use App\Http\Controllers\Api\v1\GiftCardController;
use App\Http\Controllers\Api\v1\SignatureController;
use App\Http\Controllers\Api\v1\SiteController;
use App\Http\Controllers\Api\v1\NotificationController;
use App\Http\Controllers\Api\v1\Admin\ReservationAdminController;
use App\Http\Controllers\Api\v1\Admin\ResourceController;
use App\Http\Controllers\Api\v1\Admin\ReportController;
use App\Http\Controllers\Api\Admin\StripeConnectController;
use App\Http\Controllers\Webhook\StripeWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    
    // ==================== AUTHENTIFICATION ====================
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    });

    // ==================== RÉSERVATIONS (PUBLIC) ====================
    Route::prefix('reservations')->group(function () {
        Route::post('/', [ReservationController::class, 'store']);
        Route::get('/{uuid}', [ReservationController::class, 'show']);
        Route::post('/{uuid}/add-options', [ReservationController::class, 'addOptions']);
        Route::post('/{uuid}/apply-coupon', [ReservationController::class, 'applyCoupon']);
        Route::post('/{uuid}/reschedule', [ReservationController::class, 'reschedule'])->middleware('auth:sanctum');
        Route::post('/{uuid}/cancel', [ReservationController::class, 'cancel'])->middleware('auth:sanctum');
    });

    // ==================== RÉSERVATIONS (CLIENT AUTHENTIFIÉ) ====================
    Route::prefix('my')->middleware(['auth:sanctum', 'role:client'])->group(function () {
        Route::get('/reservations', [ReservationController::class, 'myReservations']);
        Route::get('/reservations/{id}', [ReservationController::class, 'myReservation']);
        Route::get('/reservations/{id}/history', [ReservationController::class, 'myReservationHistory']);
        Route::post('/reservations/{id}/add-options', [ReservationController::class, 'addOptionsToMyReservation']);
    });

    // ==================== PAIEMENTS ====================
    Route::prefix('payments')->group(function () {
        Route::post('/intent', [PaymentController::class, 'createIntent']);
        Route::post('/capture', [PaymentController::class, 'capture'])->middleware(['auth:sanctum', 'role:admin,instructor,biplaceur']); // 'biplaceur' pour rétrocompatibilité
        Route::post('/refund', [PaymentController::class, 'refund'])->middleware(['auth:sanctum', 'role:admin']);
        
        // Stripe Terminal & QR Code (Instructeurs)
        // Note: 'biplaceur' dans le middleware pour rétrocompatibilité
        Route::middleware(['auth:sanctum', 'role:instructor,biplaceur'])->group(function () {
            Route::post('/terminal/connection-token', [PaymentController::class, 'getTerminalConnectionToken']);
            Route::post('/terminal/payment-intent', [PaymentController::class, 'createTerminalPaymentIntent']);
            Route::post('/qr/create', [PaymentController::class, 'createQrCheckout']);
        });
    });

    // ==================== INSTRUCTORS (Générique) ====================
    Route::prefix('instructors')->group(function () {
        // Routes publiques (liste)
        Route::get('/', [InstructorController::class, 'index']);
        Route::get('/by-activity/{activity_type}', [InstructorController::class, 'byActivity']);
        
        // Routes instructeur authentifié
        // Note: 'biplaceur' dans le middleware pour rétrocompatibilité
        Route::middleware(['auth:sanctum', 'role:instructor,biplaceur'])->prefix('me')->group(function () {
            Route::get('/sessions', [InstructorController::class, 'mySessions']);
            Route::get('/sessions/today', [InstructorController::class, 'sessionsToday']);
            Route::get('/calendar', [InstructorController::class, 'calendar']);
            Route::put('/availability', [InstructorController::class, 'updateAvailability']);
            Route::post('/sessions/{id}/mark-done', [InstructorController::class, 'markSessionDone']);
            Route::post('/sessions/{id}/reschedule', [InstructorController::class, 'rescheduleSession']);
            Route::get('/sessions/{id}/quick-info', [InstructorController::class, 'quickInfo']);
        });

        // Routes admin
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::get('/{id}', [InstructorController::class, 'show']);
            Route::get('/{id}/calendar', [InstructorController::class, 'calendar']);
            Route::post('/', [InstructorController::class, 'store']);
            Route::put('/{id}', [InstructorController::class, 'update']);
            Route::delete('/{id}', [InstructorController::class, 'destroy']);
        });
    });

    // ==================== ACTIVITIES (Générique) ====================
    Route::prefix('activities')->group(function () {
        // Routes publiques
        Route::get('/', [ActivityController::class, 'index']);
        Route::get('/by-type/{type}', [ActivityController::class, 'byType']);
        Route::get('/{id}', [ActivityController::class, 'show']);
        Route::get('/{id}/sessions', [ActivityController::class, 'sessions']);
        Route::get('/{id}/availability', [ActivityController::class, 'availability']);

        // Routes admin
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::post('/', [ActivityController::class, 'store']);
            Route::put('/{id}', [ActivityController::class, 'update']);
            Route::delete('/{id}', [ActivityController::class, 'destroy']);
        });
    });

    // ==================== BIPLACEURS (DEPRECATED - Alias vers Instructors) ====================
    // @deprecated - Utilisez /instructors?activity_type=paragliding à la place
    Route::prefix('biplaceurs')->group(function () {
        // Routes publiques
        Route::get('/', [BiplaceurController::class, 'index']);
        Route::get('/{id}', [BiplaceurController::class, 'show'])->middleware(['auth:sanctum', 'role:admin']);
        
        // Routes admin
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::post('/', [BiplaceurController::class, 'store']);
            Route::put('/{id}', [BiplaceurController::class, 'update']);
            Route::delete('/{id}', [BiplaceurController::class, 'destroy']);
        });
        
        // Routes biplaceur authentifié
        Route::middleware(['auth:sanctum', 'role:biplaceur'])->prefix('me')->group(function () {
            Route::get('/flights', [BiplaceurController::class, 'myFlights']);
            Route::get('/flights/today', [BiplaceurController::class, 'flightsToday']);
            Route::get('/calendar', [BiplaceurController::class, 'calendar']);
            Route::put('/availability', [BiplaceurController::class, 'updateAvailability']);
            Route::post('/flights/{id}/mark-done', [BiplaceurController::class, 'markFlightDone']);
            Route::post('/flights/{id}/reschedule', [BiplaceurController::class, 'rescheduleFlight']);
            Route::get('/flights/{id}/quick-info', [BiplaceurController::class, 'quickInfo']);
        });
        
        // Routes admin (calendrier spécifique)
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::get('/{id}/calendar', [BiplaceurController::class, 'calendar']);
        });
    });

    // ==================== ACTIVITY SESSIONS (Générique) ====================
    Route::prefix('activity-sessions')->group(function () {
        // Routes publiques
        Route::get('/', [ActivitySessionController::class, 'index']);
        Route::get('/by-activity/{activity_id}', [ActivitySessionController::class, 'byActivity']);
        Route::get('/{id}', [ActivitySessionController::class, 'show']);

        // Routes admin
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::post('/', [ActivitySessionController::class, 'store']);
            Route::put('/{id}', [ActivitySessionController::class, 'update']);
            Route::delete('/{id}', [ActivitySessionController::class, 'destroy']);
        });
    });

    // ==================== CLIENTS ====================
    Route::prefix('clients')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::get('/{id}', [ClientController::class, 'show']);
        Route::post('/', [ClientController::class, 'store']);
        Route::put('/{id}', [ClientController::class, 'update']);
        Route::get('/{id}/history', [ClientController::class, 'history']);
    });

    // ==================== OPTIONS ====================
    Route::prefix('options')->group(function () {
        Route::get('/', [OptionController::class, 'index']); // Public
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::post('/', [OptionController::class, 'store']);
            Route::put('/{id}', [OptionController::class, 'update']);
            Route::delete('/{id}', [OptionController::class, 'destroy']);
        });
    });

    // ==================== COUPONS ====================
    Route::prefix('coupons')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [CouponController::class, 'index']);
        Route::post('/', [CouponController::class, 'store']);
        Route::put('/{id}', [CouponController::class, 'update']);
        Route::delete('/{id}', [CouponController::class, 'destroy']);
    });

    // ==================== BONS CADEAUX ====================
    Route::prefix('giftcards')->group(function () {
        Route::post('/validate', [GiftCardController::class, 'validate']);
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::get('/', [GiftCardController::class, 'index']);
            Route::post('/', [GiftCardController::class, 'store']);
            Route::put('/{id}', [GiftCardController::class, 'update']);
        });
    });

    // ==================== SIGNATURES ====================
    Route::prefix('signatures')->group(function () {
        Route::post('/{reservation_id}', [SignatureController::class, 'store']);
    });

    // ==================== SITES ====================
    Route::prefix('sites')->group(function () {
        Route::get('/', [SiteController::class, 'index']); // Public
        Route::get('/{id}', [SiteController::class, 'show']); // Public
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::post('/', [SiteController::class, 'store']);
            Route::put('/{id}', [SiteController::class, 'update']);
            Route::delete('/{id}', [SiteController::class, 'destroy']);
        });
    });

    // ==================== RESSOURCES (ADMIN) ====================
    Route::prefix('admin/resources')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [ResourceController::class, 'index']);
        Route::get('/vehicles', [ResourceController::class, 'vehicles']);
        Route::get('/tandem-gliders', [ResourceController::class, 'tandemGliders']);
        Route::get('/available', [ResourceController::class, 'available']);
        Route::post('/', [ResourceController::class, 'store']);
        Route::get('/{id}', [ResourceController::class, 'show']);
        Route::put('/{id}', [ResourceController::class, 'update']);
        Route::delete('/{id}', [ResourceController::class, 'destroy']);
    });

    // ==================== DASHBOARD (ADMIN) ====================
    Route::prefix('admin/dashboard')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index']); // Route principale
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/stats', [DashboardController::class, 'stats']); // Alias pour summary
        Route::get('/revenue', [DashboardController::class, 'revenue']);
        Route::get('/flights', [DashboardController::class, 'flightStats']); // @deprecated - Utilisez /activity-stats ou /stats
        Route::get('/top-instructors', [DashboardController::class, 'topInstructors']);
        Route::get('/top-biplaceurs', [DashboardController::class, 'topBiplaceurs']); // @deprecated - Utilisez /top-instructors?activity_type=paragliding
    });
    
    // ==================== ADMIN - INSTRUCTORS (Générique) ====================
    Route::prefix('admin/instructors')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [InstructorController::class, 'index']);
        Route::get('/{id}', [InstructorController::class, 'show']);
        Route::get('/{id}/calendar', [InstructorController::class, 'calendar']);
        Route::post('/', [InstructorController::class, 'store']);
        Route::put('/{id}', [InstructorController::class, 'update']);
        Route::delete('/{id}', [InstructorController::class, 'destroy']);
    });

    // ==================== ADMIN - RÉSERVATIONS ====================
    Route::prefix('admin/reservations')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [ReservationAdminController::class, 'index']);
        Route::get('/{id}', [ReservationAdminController::class, 'show']);
        Route::get('/{id}/history', [ReservationAdminController::class, 'history']);
        Route::post('/{id}/schedule', [ReservationAdminController::class, 'schedule']);
        Route::put('/{id}/assign', [ReservationAdminController::class, 'assign']);
        Route::patch('/{id}/status', [ReservationAdminController::class, 'updateStatus']);
        Route::post('/{id}/add-options', [ReservationAdminController::class, 'addOptions']);
        Route::post('/{id}/complete', [ReservationAdminController::class, 'complete']);
        Route::post('/{id}/capture', [ReservationAdminController::class, 'capture']);
        Route::post('/{id}/refund', [ReservationAdminController::class, 'refund']);
    });

    // ==================== NOTIFICATIONS ====================
    Route::prefix('notifications')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    });

    // ==================== ADMIN - RAPPORTS ====================
    Route::prefix('admin/reports')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/daily', [ReportController::class, 'daily']);
        Route::get('/monthly', [ReportController::class, 'monthly']);
    });

    // ==================== STRIPE CONNECT ====================
    Route::prefix('admin/stripe/connect')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/account', [StripeConnectController::class, 'createAccount']);
        Route::get('/status', [StripeConnectController::class, 'getAccountStatus']);
        Route::get('/login-link', [StripeConnectController::class, 'getLoginLink']);
    });

    // ==================== SUBSCRIPTIONS ====================
    Route::prefix('admin/subscriptions')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\SubscriptionController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Admin\SubscriptionController::class, 'create']);
        Route::get('/current', [\App\Http\Controllers\Api\Admin\SubscriptionController::class, 'current']);
        Route::post('/cancel', [\App\Http\Controllers\Api\Admin\SubscriptionController::class, 'cancel']);
    });
});

// ==================== WEBHOOKS ====================
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('verify.stripe.webhook');
