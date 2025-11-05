<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmation de votre réservation #{$this->reservation->uuid}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-confirmation',
            with: [
                'reservation' => $this->reservation,
                'trackingUrl' => route('reservations.show', ['uuid' => $this->reservation->uuid]),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
