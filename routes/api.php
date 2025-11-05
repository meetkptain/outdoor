<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\ReservationController;
use App\Http\Controllers\Api\v1\PaymentController;
use App\Http\Controllers\Api\v1\BiplaceurController;
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
        Route::post('/capture', [PaymentController::class, 'capture'])->middleware(['auth:sanctum', 'role:admin,biplaceur']);
        Route::post('/refund', [PaymentController::class, 'refund'])->middleware(['auth:sanctum', 'role:admin']);
        
        // Stripe Terminal & QR Code (Biplaceurs)
        Route::middleware(['auth:sanctum', 'role:biplaceur'])->group(function () {
            Route::post('/terminal/connection-token', [PaymentController::class, 'getTerminalConnectionToken']);
            Route::post('/terminal/payment-intent', [PaymentController::class, 'createTerminalPaymentIntent']);
            Route::post('/qr/create', [PaymentController::class, 'createQrCheckout']);
        });
    });

    // ==================== BIPLACEURS ====================
    Route::prefix('biplaceurs')->group(function () {
        // Routes publiques (liste)
        Route::get('/', [BiplaceurController::class, 'index']);
        
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

        // Routes admin
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::get('/{id}', [BiplaceurController::class, 'show']);
            Route::get('/{id}/calendar', [BiplaceurController::class, 'calendar']); // Calendrier biplaceur (admin)
            Route::post('/', [BiplaceurController::class, 'store']);
            Route::put('/{id}', [BiplaceurController::class, 'update']);
            Route::delete('/{id}', [BiplaceurController::class, 'destroy']);
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
        Route::get('/flights', [DashboardController::class, 'flightStats']);
        Route::get('/top-biplaceurs', [DashboardController::class, 'topBiplaceurs']);
    });
    
    // ==================== ADMIN - BIPLACEURS ====================
    Route::prefix('admin/biplaceurs')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/{id}', [BiplaceurController::class, 'show']);
        Route::get('/{id}/calendar', [BiplaceurController::class, 'calendar']); // Calendrier biplaceur (admin)
        Route::post('/', [BiplaceurController::class, 'store']);
        Route::put('/{id}', [BiplaceurController::class, 'update']);
        Route::delete('/{id}', [BiplaceurController::class, 'destroy']);
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
