

<?php $__env->startSection('title', 'Confirmation de réservation'); ?>

<?php $__env->startSection('content'); ?>
    <h2 style="color: #667eea; margin-top: 0;">Réservation confirmée !</h2>
    
    <p>Bonjour <strong><?php echo e($reservation->customer_first_name); ?> <?php echo e($reservation->customer_last_name); ?></strong>,</p>
    
    <p>Nous avons bien reçu votre réservation de vol parapente. Votre réservation est enregistrée sous le numéro :</p>
    
    <div class="info-box">
        <strong style="font-size: 18px; color: #667eea;">#<?php echo e($reservation->uuid); ?></strong>
    </div>
    
    <div class="reservation-details">
        <h3 style="margin-top: 0; color: #333;">Détails de votre réservation</h3>
        <dl>
            <dt>Type de vol :</dt>
            <dd><?php echo e(ucfirst($reservation->flight_type)); ?></dd>
            
            <dt>Nombre de participants :</dt>
            <dd><?php echo e($reservation->participants_count); ?></dd>
            
            <?php if($reservation->options->count() > 0): ?>
            <dt>Options sélectionnées :</dt>
            <dd>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <?php $__currentLoopData = $reservation->options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($option->name); ?> (x<?php echo e($option->pivot->quantity); ?>)</li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </dd>
            <?php endif; ?>
            
            <dt>Montant total :</dt>
            <dd><strong><?php echo e(number_format($reservation->total_amount, 2, ',', ' ')); ?> €</strong></dd>
            
            <?php if($reservation->deposit_amount > 0): ?>
            <dt>Acompte payé :</dt>
            <dd><?php echo e(number_format($reservation->deposit_amount, 2, ',', ' ')); ?> €</dd>
            <?php endif; ?>
            
            <?php if($reservation->coupon_code): ?>
            <dt>Code promo appliqué :</dt>
            <dd><?php echo e($reservation->coupon_code); ?></dd>
            <?php endif; ?>
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
        <a href="<?php echo e($trackingUrl); ?>" class="button">Suivre ma réservation</a>
    </div>
    
    <p>Si vous avez des questions ou des demandes spéciales, n'hésitez pas à nous contacter.</p>
    
    <p>À très bientôt dans les airs ! 🪂</p>
    
    <p style="margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe Parapente Club</strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Lenovo\Desktop\parapente\resources\views/emails/reservation-confirmation.blade.php ENDPATH**/ ?>