@extends('emails.layout')

@section('title', 'Rappel : Votre vol demain')

@section('content')
    <h2 style="color: #667eea; margin-top: 0;">Rappel : Votre vol demain ! 🪂</h2>
    
    <p>Bonjour <strong>{{ $reservation->customer_first_name }} {{ $reservation->customer_last_name }}</strong>,</p>
    
    <p>Ceci est un rappel amical : <strong>votre vol parapente est prévu demain !</strong></p>
    
    <div class="info-box">
        <h3 style="margin-top: 0; color: #667eea;">📅 Date et heure :</h3>
        <p style="font-size: 20px; margin: 10px 0; font-weight: 600;">
            {{ $reservation->scheduled_at->format('d/m/Y') }} à {{ $reservation->scheduled_at->format('H:i') }}
        </p>
    </div>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Informations pratiques</h3>
        <dl>
            <dt>Réservation # :</dt>
            <dd>{{ $reservation->uuid }}</dd>
            
            @if($reservation->site)
            <dt>Lieu de rendez-vous :</dt>
            <dd>{{ $reservation->site->name }}<br>
                @if($reservation->site->location)
                    <small>{{ $reservation->site->location }}</small>
                @endif
            </dd>
            @endif
            
            @if($reservation->instructor)
            <dt>Moniteur :</dt>
            <dd>{{ $reservation->instructor->name }}</dd>
            @endif
        </dl>
    </div>
    
    <div class="info-box" style="background-color: #d1ecf1; border-left-color: #0c5460;">
        <p style="margin: 0;"><strong>✅ Checklist avant le vol :</strong></p>
        <ul style="margin: 10px 0 0 20px; padding: 0;">
            <li>Vérifier les conditions météorologiques</li>
            <li>Prévoir des vêtements adaptés (chaussures fermées, vêtements chauds)</li>
            <li>Apporter une pièce d'identité</li>
            <li>Arriver 15 minutes avant l'heure prévue</li>
            <li>Confirmer votre présence si besoin</li>
        </ul>
    </div>
    
    @if($reservation->special_requests)
    <div class="info-box" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p style="margin: 0;"><strong>📝 Vos demandes spéciales :</strong></p>
        <p style="margin: 10px 0 0 0;">{{ $reservation->special_requests }}</p>
    </div>
    @endif
    
    <p>En cas d'empêchement ou de questions, n'hésitez pas à nous contacter rapidement.</p>
    
    <div style="text-align: center;">
        <a href="{{ $trackingUrl }}" class="button">Voir les détails de ma réservation</a>
    </div>
    
    <p>Nous avons hâte de vous voir demain pour ce moment magique ! 🌤️</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
@endsection
