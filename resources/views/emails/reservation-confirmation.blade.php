@extends('emails.layout')

@section('title', 'Confirmation de réservation')

@section('content')
    <h2 style="color: #667eea; margin-top: 0;">Réservation confirmée !</h2>
    
    <p>Bonjour <strong>{{ $reservation->customer_first_name }} {{ $reservation->customer_last_name }}</strong>,</p>
    
    <p>Nous avons bien reçu votre réservation de vol parapente. Votre réservation est enregistrée sous le numéro :</p>
    
    <div class="info-box">
        <strong style="font-size: 18px; color: #667eea;">#{{ $reservation->uuid }}</strong>
    </div>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Détails de votre réservation</h3>
        <dl>
            <dt>Type de vol :</dt>
            <dd>{{ ucfirst($reservation->flight_type) }}</dd>
            
            <dt>Nombre de participants :</dt>
            <dd>{{ $reservation->participants_count }}</dd>
            
            @if($reservation->options->count() > 0)
            <dt>Options sélectionnées :</dt>
            <dd>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    @foreach($reservation->options as $option)
                        <li>{{ $option->name }} (x{{ $option->pivot->quantity }})</li>
                    @endforeach
                </ul>
            </dd>
            @endif
            
            <dt>Montant total :</dt>
            <dd><strong>{{ number_format($reservation->total_amount, 2, ',', ' ') }} €</strong></dd>
            
            @if($reservation->deposit_amount > 0)
            <dt>Acompte payé :</dt>
            <dd>{{ number_format($reservation->deposit_amount, 2, ',', ' ') }} €</dd>
            @endif
            
            @if($reservation->coupon_code)
            <dt>Code promo appliqué :</dt>
            <dd>{{ $reservation->coupon_code }}</dd>
            @endif
        </dl>
    </div>
    
    <p><strong>Prochaines étapes :</strong></p>
    <ul>
        <li>Votre réservation est en attente d'assignation de date</li>
        <li>Nous vous contacterons prochainement pour fixer une date de vol</li>
        <li>Vous recevrez un email de confirmation une fois la date assignée</li>
    </ul>
    
    <p>Vous pouvez suivre l'état de votre réservation à tout moment :</p>
    
    <div style="text-align: center;">
        <a href="{{ $trackingUrl }}" class="button">Suivre ma réservation</a>
    </div>
    
    <p>Si vous avez des questions ou des demandes spéciales, n'hésitez pas à nous contacter.</p>
    
    <p>À très bientôt dans les airs ! 🪂</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
@endsection
