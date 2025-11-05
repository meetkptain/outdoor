@extends('emails.layout')

@section('title', 'Date assignée pour votre vol')

@section('content')
    <h2 style="color: #667eea; margin-top: 0;">Date assignée !</h2>
    
    <p>Bonjour <strong>{{ $reservation->customer_first_name }} {{ $reservation->customer_last_name }}</strong>,</p>
    
    <p>Excellente nouvelle ! Votre vol parapente est maintenant planifié.</p>
    
    <div class="info-box">
        <h3 style="margin-top: 0; color: #667eea;">🪂 Votre vol est prévu le :</h3>
        <p style="font-size: 20px; margin: 10px 0; font-weight: 600;">
            {{ $reservation->scheduled_at->format('d/m/Y') }} à {{ $reservation->scheduled_at->format('H:i') }}
        </p>
    </div>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Détails de votre vol</h3>
        <dl>
            <dt>Réservation # :</dt>
            <dd>{{ $reservation->uuid }}</dd>
            
            @if($reservation->instructor)
            <dt>Moniteur :</dt>
            <dd>{{ $reservation->instructor->name ?? 'À confirmer' }}</dd>
            @endif
            
            @if($reservation->site)
            <dt>Site de décollage :</dt>
            <dd>{{ $reservation->site->name }}</dd>
            @endif
            
            <dt>Type de vol :</dt>
            <dd>{{ ucfirst($reservation->flight_type) }}</dd>
            
            <dt>Nombre de participants :</dt>
            <dd>{{ $reservation->participants_count }}</dd>
        </dl>
    </div>
    
    <div class="info-box" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p style="margin: 0;"><strong>📋 Important :</strong></p>
        <ul style="margin: 10px 0 0 20px; padding: 0;">
            <li>Merci d'arriver 15 minutes avant l'heure prévue</li>
            <li>Pensez à vérifier les conditions météorologiques</li>
            <li>Vous recevrez un rappel 24h avant votre vol</li>
        </ul>
    </div>
    
    <p>Vous souhaitez ajouter des options à votre réservation ? (photo, vidéo, etc.)</p>
    
    <div style="text-align: center;">
        <a href="{{ $addOptionsUrl }}" class="button">Ajouter des options</a>
    </div>
    
    <p>Ou suivez l'état de votre réservation :</p>
    
    <div style="text-align: center;">
        <a href="{{ $trackingUrl }}" class="button" style="background-color: #6c757d;">Suivre ma réservation</a>
    </div>
    
    <p>Nous avons hâte de vous faire découvrir les sensations du vol libre ! 🪂</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
@endsection
