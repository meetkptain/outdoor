@extends('emails.layout')

@section('title', 'Options ajoutées à votre réservation')

@section('content')
    <h2 style="color: #667eea; margin-top: 0;">Options ajoutées avec succès ! ✅</h2>
    
    <p>Bonjour <strong>{{ $reservation->customer_first_name }} {{ $reservation->customer_last_name }}</strong>,</p>
    
    <p>Nous avons bien reçu votre demande d'ajout d'options à votre réservation.</p>
    
    <div class="info-box">
        <p style="margin: 0;">
            <strong>Réservation #{{ $reservation->uuid }}</strong>
        </p>
    </div>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Nouvelles options ajoutées</h3>
        
        @php
            $newOptions = $reservation->options->where('pivot.added_at_stage', '!=', 'initial');
            $newOptionsAmount = $newOptions->sum(function($option) {
                return $option->pivot->total_price;
            });
        @endphp
        
        @if($newOptions->count() > 0)
            <dl>
                @foreach($newOptions as $option)
                    <dt>{{ $option->name }} :</dt>
                    <dd>
                        Quantité : {{ $option->pivot->quantity }}<br>
                        Prix unitaire : {{ number_format($option->pivot->unit_price, 2, ',', ' ') }} €<br>
                        <strong>Total : {{ number_format($option->pivot->total_price, 2, ',', ' ') }} €</strong>
                    </dd>
                @endforeach
            </dl>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #dee2e6;">
                <p style="margin: 0;">
                    <strong style="font-size: 16px;">Montant des nouvelles options : {{ number_format($newOptionsAmount, 2, ',', ' ') }} €</strong>
                </p>
            </div>
        @endif
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #dee2e6;">
            <p style="margin: 0;">
                <strong>Nouveau montant total de la réservation :</strong><br>
                <span style="font-size: 20px; color: #667eea;">{{ number_format($reservation->total_amount, 2, ',', ' ') }} €</span>
            </p>
        </div>
    </div>
    
    @if($reservation->payment_status === 'authorized' || $reservation->payment_status === 'pending')
    <div class="info-box" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p style="margin: 0;"><strong>💳 Information de paiement :</strong></p>
        <p style="margin: 10px 0 0 0;">
            @if($reservation->payment_status === 'authorized')
                Le montant des nouvelles options sera prélevé lors de la capture finale de paiement après votre vol.
            @else
                Un paiement complémentaire pourra être nécessaire pour les nouvelles options.
            @endif
        </p>
    </div>
    @endif
    
    <p>Les options ont été ajoutées avec succès à votre réservation et seront disponibles selon les conditions prévues.</p>
    
    <div style="text-align: center; margin: 25px 0;">
        <a href="{{ $trackingUrl }}" class="button">Voir ma réservation complète</a>
    </div>
    
    @if($reservation->scheduled_at)
    <div class="info-box" style="background-color: #d1ecf1; border-left-color: #0c5460;">
        <p style="margin: 0;"><strong>📅 Rappel :</strong></p>
        <p style="margin: 10px 0 0 0;">
            Votre vol est prévu le <strong>{{ $reservation->scheduled_at->format('d/m/Y à H:i') }}</strong>.
            N'oubliez pas d'arriver 15 minutes avant !
        </p>
    </div>
    @endif
    
    <p>Si vous avez des questions concernant les options ajoutées, n'hésitez pas à nous contacter.</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
@endsection
