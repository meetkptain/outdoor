<?php

namespace App\Modules;

/**
 * Enum des hooks disponibles pour les modules
 * 
 * Permet aux modules de s'intégrer dans le workflow de l'application
 * via des points d'extension standardisés.
 */
enum ModuleHook: string
{
    // Hooks de réservation
    case BEFORE_RESERVATION_CREATE = 'before_reservation_create';
    case AFTER_RESERVATION_CREATE = 'after_reservation_create';
    case BEFORE_RESERVATION_UPDATE = 'before_reservation_update';
    case AFTER_RESERVATION_UPDATE = 'after_reservation_update';
    case BEFORE_RESERVATION_CANCEL = 'before_reservation_cancel';
    case AFTER_RESERVATION_CANCEL = 'after_reservation_cancel';

    // Hooks de session
    case BEFORE_SESSION_SCHEDULE = 'before_session_schedule';
    case AFTER_SESSION_SCHEDULE = 'after_session_schedule';
    case BEFORE_SESSION_COMPLETE = 'before_session_complete';
    case AFTER_SESSION_COMPLETE = 'after_session_complete';
    case BEFORE_SESSION_CANCEL = 'before_session_cancel';
    case AFTER_SESSION_CANCEL = 'after_session_cancel';

    // Hooks de paiement
    case BEFORE_PAYMENT_CAPTURE = 'before_payment_capture';
    case AFTER_PAYMENT_CAPTURE = 'after_payment_capture';
    case BEFORE_PAYMENT_REFUND = 'before_payment_refund';
    case AFTER_PAYMENT_REFUND = 'after_payment_refund';

    // Hooks d'instructeur
    case BEFORE_INSTRUCTOR_ASSIGN = 'before_instructor_assign';
    case AFTER_INSTRUCTOR_ASSIGN = 'after_instructor_assign';

    /**
     * Retourne tous les hooks de réservation
     * 
     * @return array
     */
    public static function reservationHooks(): array
    {
        return [
            self::BEFORE_RESERVATION_CREATE,
            self::AFTER_RESERVATION_CREATE,
            self::BEFORE_RESERVATION_UPDATE,
            self::AFTER_RESERVATION_UPDATE,
            self::BEFORE_RESERVATION_CANCEL,
            self::AFTER_RESERVATION_CANCEL,
        ];
    }

    /**
     * Retourne tous les hooks de session
     * 
     * @return array
     */
    public static function sessionHooks(): array
    {
        return [
            self::BEFORE_SESSION_SCHEDULE,
            self::AFTER_SESSION_SCHEDULE,
            self::BEFORE_SESSION_COMPLETE,
            self::AFTER_SESSION_COMPLETE,
            self::BEFORE_SESSION_CANCEL,
            self::AFTER_SESSION_CANCEL,
        ];
    }

    /**
     * Retourne tous les hooks de paiement
     * 
     * @return array
     */
    public static function paymentHooks(): array
    {
        return [
            self::BEFORE_PAYMENT_CAPTURE,
            self::AFTER_PAYMENT_CAPTURE,
            self::BEFORE_PAYMENT_REFUND,
            self::AFTER_PAYMENT_REFUND,
        ];
    }
}

