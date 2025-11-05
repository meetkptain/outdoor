<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UpsellAfterFlightMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Souvenez-vous de votre vol parapente - Photos et vidéos disponibles",
        );
    }

    public function content(): Content
    {
        $addOptionsUrl = route('reservations.add-options', ['uuid' => $this->reservation->uuid]);

        return new Content(
            view: 'emails.upsell-after-flight',
            with: [
                'reservation' => $this->reservation,
                'addOptionsUrl' => $addOptionsUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
