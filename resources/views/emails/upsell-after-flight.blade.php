@extends('emails.layout')

@section('title', 'Souvenez-vous de votre vol - Photos et vidéos')

@section('content')
    <h2 style="color: #667eea; margin-top: 0;">Immortalisez votre vol ! 📸</h2>
    
    <p>Bonjour <strong>{{ $reservation->customer_first_name }} {{ $reservation->customer_last_name }}</strong>,</p>
    
    <p>Nous espérons que vous avez apprécié votre expérience de vol parapente avec nous !</p>
    
    <p>Pour garder un souvenir inoubliable de cette journée, nous vous proposons de compléter votre réservation avec :</p>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Options disponibles</h3>
        
        <div style="margin: 20px 0; padding: 20px; background-color: #ffffff; border: 2px solid #667eea; border-radius: 5px;">
            <h4 style="margin-top: 0; color: #667eea;">📷 Pack Photo Professionnel</h4>
            <p>Des photos haute qualité de votre vol, sélectionnées et retouchées par nos photographes professionnels.</p>
            <p><strong>Parfait pour partager sur les réseaux sociaux !</strong></p>
        </div>
        
        <div style="margin: 20px 0; padding: 20px; background-color: #ffffff; border: 2px solid #667eea; border-radius: 5px;">
            <h4 style="margin-top: 0; color: #667eea;">🎥 Pack Vidéo HD</h4>
            <p>Une vidéo complète de votre vol, montée et sonorisée, pour revivre chaque instant de votre expérience.</p>
            <p><strong>Un souvenir à partager en famille !</strong></p>
        </div>
        
        <div style="margin: 20px 0; padding: 20px; background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 5px;">
            <h4 style="margin-top: 0; color: #856404;">⭐ Pack Complet Photo + Vidéo</h4>
            <p><strong>Offre spéciale :</strong> Obtenez les photos ET la vidéo avec une réduction !</p>
            <p>Le meilleur moyen de capturer tous les moments de votre vol.</p>
        </div>
    </div>
    
    <div class="info-box">
        <p style="margin: 0;"><strong>💡 Pourquoi commander maintenant ?</strong></p>
        <ul style="margin: 10px 0 0 20px; padding: 0;">
            <li>Livraison rapide par email</li>
            <li>Qualité professionnelle garantie</li>
            <li>Support pendant 6 mois</li>
            <li>Tarifs préférentiels pour les participants</li>
        </ul>
    </div>
    
    <p>Ne manquez pas cette occasion d'ajouter ces options à votre réservation :</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $addOptionsUrl }}" class="button" style="font-size: 16px; padding: 15px 40px;">Ajouter des options maintenant</a>
    </div>
    
    <p style="font-size: 12px; color: #666; text-align: center;">
        Cette offre est valable pour une durée limitée après votre vol.
    </p>
    
    <p>En espérant vous revoir bientôt pour de nouvelles aventures aériennes ! 🪂</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
@endsection
