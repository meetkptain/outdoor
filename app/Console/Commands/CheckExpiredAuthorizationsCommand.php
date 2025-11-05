<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredAuthorizationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:check-expired-auths';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier et réautoriser les autorisations Stripe expirées (> 7 jours)';

    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Vérification des autorisations expirées...');

        // Récupérer les paiements autorisés mais non capturés depuis plus de 7 jours
        $expiredAuths = Payment::where('status', 'requires_capture')
            ->where('type', 'authorization')
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->with('reservation')
            ->get();

        $expired = 0;
        $reauthorized = 0;
        $errors = 0;

        foreach ($expiredAuths as $payment) {
            $reservation = $payment->reservation;

            if (!$reservation) {
                continue;
            }

            // Vérifier si la réservation est toujours valide
            if (in_array($reservation->status, ['cancelled', 'refunded'])) {
                continue;
            }

            $expired++;

            try {
                // Essayer de réautoriser (si SetupIntent existe)
                // Pour l'instant, on log juste l'expiration
                $this->warn("⚠️  Autorisation expirée pour réservation #{$reservation->uuid} (PaymentIntent: {$payment->stripe_payment_intent_id})");

                // TODO: Implémenter réautorisation si SetupIntent sauvegardé
                // $this->paymentService->reauthorizeIfNeeded($reservation);

                // Pour l'instant, on marque juste comme expiré dans les logs
                \Illuminate\Support\Facades\Log::warning('Expired authorization detected', [
                    'payment_id' => $payment->id,
                    'reservation_id' => $reservation->id,
                    'reservation_uuid' => $reservation->uuid,
                    'created_at' => $payment->created_at,
                ]);

            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Erreur pour payment #{$payment->id}: {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Résumé: {$expired} autorisations expirées détectées, {$errors} erreurs");

        if ($expired > 0) {
            $this->warn("⚠️  Action requise: Vérifier manuellement ces réservations ou implémenter réautorisation automatique");
        }

        return Command::SUCCESS;
    }
}

