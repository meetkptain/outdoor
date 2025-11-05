

<?php $__env->startSection('title', 'Date assignée pour votre vol'); ?>

<?php $__env->startSection('content'); ?>
    <h2 style="color: #667eea; margin-top: 0;">Date assignée !</h2>
    
    <p>Bonjour <strong><?php echo e($reservation->customer_first_name); ?> <?php echo e($reservation->customer_last_name); ?></strong>,</p>
    
    <p>Excellente nouvelle ! Votre vol parapente est maintenant planifié.</p>
    
    <div class="info-box">
        <h3 style="margin-top: 0; color: #667eea;">🪂 Votre vol est prévu le :</h3>
        <p style="font-size: 20px; margin: 10px 0; font-weight: 600;">
            <?php echo e($reservation->scheduled_at->format('d/m/Y')); ?> à <?php echo e($reservation->scheduled_at->format('H:i')); ?>

        </p>
    </div>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Détails de votre vol</h3>
        <dl>
            <dt>Réservation # :</dt>
            <dd><?php echo e($reservation->uuid); ?></dd>
            
            <?php if($reservation->instructor): ?>
            <dt>Moniteur :</dt>
            <dd><?php echo e($reservation->instructor->name ?? 'À confirmer'); ?></dd>
            <?php endif; ?>
            
            <?php if($reservation->site): ?>
            <dt>Site de décollage :</dt>
            <dd><?php echo e($reservation->site->name); ?></dd>
            <?php endif; ?>
            
            <dt>Type de vol :</dt>
            <dd><?php echo e(ucfirst($reservation->flight_type)); ?></dd>
            
            <dt>Nombre de participants :</dt>
            <dd><?php echo e($reservation->participants_count); ?></dd>
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
        <a href="<?php echo e($addOptionsUrl); ?>" class="button">Ajouter des options</a>
    </div>
    
    <p>Ou suivez l'état de votre réservation :</p>
    
    <div style="text-align: center;">
        <a href="<?php echo e($trackingUrl); ?>" class="button" style="background-color: #6c757d;">Suivre ma réservation</a>
    </div>
    
    <p>Nous avons hâte de vous faire découvrir les sensations du vol libre ! 🪂</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Lenovo\Desktop\parapente\resources\views/emails/assignment-notification.blade.php ENDPATH**/ ?>