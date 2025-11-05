<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Notification as NotificationModel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Envoyer l'email de confirmation de réservation
     */
    public function sendReservationConfirmation(Reservation $reservation): void
    {
        try {
            $notification = NotificationModel::create([
                'reservation_id' => $reservation->id,
                'type' => 'email',
                'template' => 'reservation_confirmation',
                'recipient' => $reservation->customer_email,
                'subject' => "Confirmation de votre réservation #{$reservation->uuid}",
                'status' => 'pending',
            ]);

            Mail::to($reservation->customer_email)->send(
                new \App\Mail\ReservationConfirmationMail($reservation)
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send reservation confirmation', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($notification)) {
                $notification->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Envoyer notification d'assignation de date
     */
    public function sendAssignmentNotification(Reservation $reservation): void
    {
        try {
            // Email
            $emailNotification = NotificationModel::create([
                'reservation_id' => $reservation->id,
                'type' => 'email',
                'template' => 'assignment_notification',
                'recipient' => $reservation->customer_email,
                'subject' => "Date assignée pour votre vol parapente #{$reservation->uuid}",
                'status' => 'pending',
            ]);

            Mail::to($reservation->customer_email)->send(
                new \App\Mail\AssignmentNotificationMail($reservation)
            );

            $emailNotification->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

            // SMS si téléphone fourni
            if ($reservation->customer_phone) {
                $this->sendSMS(
                    $reservation,
                    "Votre vol parapente est prévu le {$reservation->scheduled_at->format('d/m/Y à H:i')}. Détails par email."
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send assignment notification', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Programmer un rappel 24h avant le vol
     */
    public function scheduleReminder(Reservation $reservation): void
    {
        if (!$reservation->scheduled_at) {
            return;
        }

        $reminderTime = Carbon::parse($reservation->scheduled_at)->subDay();

        if ($reminderTime->isPast()) {
            return; // Trop tard pour programmer
        }

        \App\Jobs\SendReminder::dispatch($reservation)
            ->delay($reminderTime);
    }

    /**
     * Envoyer un rappel
     */
    public function sendReminder(Reservation $reservation): void
    {
        if ($reservation->reminder_sent) {
            return;
        }

        try {
            Mail::to($reservation->customer_email)->send(
                new \App\Mail\ReminderMail($reservation)
            );

            $reservation->update([
                'reminder_sent' => true,
                'reminder_sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send reminder', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoyer upsell photo/vidéo après le vol
     */
    public function sendUpsellAfterFlight(Reservation $reservation): void
    {
        // Vérifier si options photo/vidéo déjà prises
        $hasPhoto = $reservation->options()
            ->whereIn('code', ['photo', 'video'])
            ->exists();

        if ($hasPhoto) {
            return; // Déjà pris, pas besoin d'upsell
        }

        try {
            Mail::to($reservation->customer_email)->send(
                new \App\Mail\UpsellAfterFlightMail($reservation)
            );
        } catch (\Exception $e) {
            Log::error('Failed to send upsell email', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoyer email de remerciement après le vol
     */
    public function sendThankYouEmail(Reservation $reservation): void
    {
        try {
            Mail::to($reservation->customer_email)->send(
                new \App\Mail\ThankYouMail($reservation)
            );
        } catch (\Exception $e) {
            Log::error('Failed to send thank you email', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifier l'ajout d'options
     */
    public function sendOptionsAddedNotification(Reservation $reservation): void
    {
        try {
            Mail::to($reservation->customer_email)->send(
                new \App\Mail\OptionsAddedMail($reservation)
            );
        } catch (\Exception $e) {
            Log::error('Failed to send options added notification', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoyer un SMS
     */
    protected function sendSMS(Reservation $reservation, string $message): void
    {
        if (!config('services.twilio.sid')) {
            return; // Twilio non configuré
        }

        try {
            $notification = NotificationModel::create([
                'reservation_id' => $reservation->id,
                'type' => 'sms',
                'template' => 'sms_notification',
                'recipient' => $reservation->customer_phone,
                'content' => $message,
                'status' => 'pending',
            ]);

            // Implémentation Twilio
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $twilio->messages->create(
                $reservation->customer_phone,
                [
                    'from' => config('services.twilio.from'),
                    'body' => $message,
                ]
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send SMS', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($notification)) {
                $notification->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Envoyer notification de report
     */
    public function sendRescheduleNotification(Reservation $reservation): void
    {
        try {
            $notification = NotificationModel::create([
                'reservation_id' => $reservation->id,
                'type' => 'email',
                'template' => 'reschedule_notification',
                'recipient' => $reservation->customer_email,
                'subject' => "Votre vol parapente #{$reservation->uuid} a été reporté",
                'status' => 'pending',
            ]);

            Mail::to($reservation->customer_email)->send(
                new \App\Mail\ReservationConfirmationMail($reservation) // Utiliser template existant ou créer nouveau
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

            // SMS si téléphone fourni
            if ($reservation->customer_phone) {
                $this->sendSMS(
                    $reservation,
                    "Votre vol parapente #{$reservation->uuid} a été reporté. Une nouvelle date vous sera proposée prochainement."
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send reschedule notification', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoyer notification d'annulation
     */
    public function sendCancellationNotification(Reservation $reservation): void
    {
        try {
            $notification = NotificationModel::create([
                'reservation_id' => $reservation->id,
                'type' => 'email',
                'template' => 'cancellation_notification',
                'recipient' => $reservation->customer_email,
                'subject' => "Annulation de votre réservation #{$reservation->uuid}",
                'status' => 'pending',
            ]);

            Mail::to($reservation->customer_email)->send(
                new \App\Mail\ReservationConfirmationMail($reservation) // Utiliser template existant ou créer nouveau
            );

            $notification->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

            // SMS si téléphone fourni
            if ($reservation->customer_phone) {
                $this->sendSMS(
                    $reservation,
                    "Votre réservation #{$reservation->uuid} a été annulée. Remboursement en cours."
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send cancellation notification', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
