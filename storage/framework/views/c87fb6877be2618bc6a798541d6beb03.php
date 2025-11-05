

<?php $__env->startSection('title', 'Rappel : Votre vol demain'); ?>

<?php $__env->startSection('content'); ?>
    <h2 style="color: #667eea; margin-top: 0;">Rappel : Votre vol demain ! 🪂</h2>
    
    <p>Bonjour <strong><?php echo e($reservation->customer_first_name); ?> <?php echo e($reservation->customer_last_name); ?></strong>,</p>
    
    <p>Ceci est un rappel amical : <strong>votre vol parapente est prévu demain !</strong></p>
    
    <div class="info-box">
        <h3 style="margin-top: 0; color: #667eea;">📅 Date et heure :</h3>
        <p style="font-size: 20px; margin: 10px 0; font-weight: 600;">
            <?php echo e($reservation->scheduled_at->format('d/m/Y')); ?> à <?php echo e($reservation->scheduled_at->format('H:i')); ?>

        </p>
    </div>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Informations pratiques</h3>
        <dl>
            <dt>Réservation # :</dt>
            <dd><?php echo e($reservation->uuid); ?></dd>
            
            <?php if($reservation->site): ?>
            <dt>Lieu de rendez-vous :</dt>
            <dd><?php echo e($reservation->site->name); ?><br>
                <?php if($reservation->site->location): ?>
                    <small><?php echo e($reservation->site->location); ?></small>
                <?php endif; ?>
            </dd>
            <?php endif; ?>
            
            <?php if($reservation->instructor): ?>
            <dt>Moniteur :</dt>
            <dd><?php echo e($reservation->instructor->name); ?></dd>
            <?php endif; ?>
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
    
    <?php if($reservation->special_requests): ?>
    <div class="info-box" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p style="margin: 0;"><strong>📝 Vos demandes spéciales :</strong></p>
        <p style="margin: 10px 0 0 0;"><?php echo e($reservation->special_requests); ?></p>
    </div>
    <?php endif; ?>
    
    <p>En cas d'empêchement ou de questions, n'hésitez pas à nous contacter rapidement.</p>
    
    <div style="text-align: center;">
        <a href="<?php echo e($trackingUrl); ?>" class="button">Voir les détails de ma réservation</a>
    </div>
    
    <p>Nous avons hâte de vous voir demain pour ce moment magique ! 🌤️</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Lenovo\Desktop\parapente\resources\views/emails/reminder.blade.php ENDPATH**/ ?>