<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Date assignée pour votre vol parapente #{$this->reservation->uuid}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assignment-notification',
            with: [
                'reservation' => $this->reservation,
                'trackingUrl' => route('reservations.show', ['uuid' => $this->reservation->uuid]),
                'addOptionsUrl' => route('reservations.add-options', ['uuid' => $this->reservation->uuid]),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
