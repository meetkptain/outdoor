

<?php $__env->startSection('title', 'Merci pour votre vol'); ?>

<?php $__env->startSection('content'); ?>
    <h2 style="color: #667eea; margin-top: 0;">Merci d'avoir volé avec nous ! 🪂</h2>
    
    <p>Bonjour <strong><?php echo e($reservation->customer_first_name); ?> <?php echo e($reservation->customer_last_name); ?></strong>,</p>
    
    <p>Nous espérons que vous avez passé un moment inoubliable lors de votre vol parapente !</p>
    
    <div class="info-box">
        <p style="font-size: 18px; margin: 0; text-align: center;">
            <strong>Réservation #<?php echo e($reservation->uuid); ?></strong><br>
            <small>Vol effectué le <?php echo e($reservation->scheduled_at->format('d/m/Y')); ?></small>
        </p>
    </div>
    
    <?php if($reservation->payments->where('status', 'succeeded')->count() > 0): ?>
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Récapitulatif de paiement</h3>
        <dl>
            <dt>Montant total :</dt>
            <dd><strong><?php echo e(number_format($reservation->total_amount, 2, ',', ' ')); ?> €</strong></dd>
            
            <?php if($reservation->options->count() > 0): ?>
            <dt>Options incluses :</dt>
            <dd>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <?php $__currentLoopData = $reservation->options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($option->name); ?> (x<?php echo e($option->pivot->quantity); ?>)</li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </dd>
            <?php endif; ?>
        </dl>
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            Une facture détaillée est disponible dans votre espace de suivi.
        </p>
    </div>
    <?php endif; ?>
    
    <h3 style="color: #333; margin-top: 30px;">Partagez votre expérience !</h3>
    
    <p>Votre avis compte énormément pour nous et pour les futurs passionnés de parapente !</p>
    
    <div style="text-align: center; margin: 25px 0;">
        <a href="<?php echo e($reviewUrl); ?>" class="button" style="background-color: #28a745;">Laisser un avis</a>
    </div>
    
    <h3 style="color: #333; margin-top: 30px;">Souvenirs de votre vol</h3>
    
    <p>Vous souhaitez recevoir les photos et vidéos de votre vol ? Ajoutez-les à votre commande :</p>
    
    <div style="text-align: center; margin: 25px 0;">
        <a href="<?php echo e($addOptionsUrl); ?>" class="button">Commander photos/vidéos</a>
    </div>
    
    <div class="info-box" style="background-color: #d1ecf1; border-left-color: #0c5460;">
        <p style="margin: 0;"><strong>💡 Bon à savoir :</strong></p>
        <ul style="margin: 10px 0 0 20px; padding: 0;">
            <li>Vous pouvez commander les photos/vidéos jusqu'à 7 jours après votre vol</li>
            <li>Nos photographes sélectionnent les meilleurs moments</li>
            <li>Livraison par email sous 48h</li>
        </ul>
    </div>
    
    <h3 style="color: #333; margin-top: 30px;">Revenez nous voir !</h3>
    
    <p>Vous avez envie de réitérer l'expérience ou d'essayer un autre type de vol ?</p>
    <p>Consultez nos prochaines disponibilités et réservez votre prochain vol dès maintenant !</p>
    
    <p style="margin-top: 30px;">
        Encore merci pour votre confiance et à très bientôt dans les airs ! 🪂🌤️
    </p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
    
    <p style="margin-top: 20px; font-size: 12px; color: #666; text-align: center;">
        Pour toute question, contactez-nous à <a href="mailto:contact@parapente-club.com">contact@parapente-club.com</a>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Lenovo\Desktop\parapente\resources\views/emails/thank-you.blade.php ENDPATH**/ ?>