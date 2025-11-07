<?php

namespace App\Models;

/**
 * @OA\Schema(
 *     schema="Reservation",
 *     type="object",
 *     title="Reservation",
 *     description="Modèle de réservation",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="activity_id", type="integer", example=1),
 *     @OA\Property(property="activity_type", type="string", example="paragliding"),
 *     @OA\Property(property="customer_email", type="string", format="email", example="john.doe@example.com"),
 *     @OA\Property(property="customer_first_name", type="string", example="John"),
 *     @OA\Property(property="customer_last_name", type="string", example="Doe"),
 *     @OA\Property(property="customer_phone", type="string", nullable=true, example="+33612345678"),
 *     @OA\Property(property="customer_weight", type="integer", nullable=true, example=75),
 *     @OA\Property(property="customer_height", type="integer", nullable=true, example=175),
 *     @OA\Property(property="participants_count", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"pending", "authorized", "scheduled", "confirmed", "completed", "cancelled", "rescheduled", "refunded"}, example="pending"),
 *     @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="base_amount", type="number", format="float", example=120.00),
 *     @OA\Property(property="options_amount", type="number", format="float", example=25.00),
 *     @OA\Property(property="discount_amount", type="number", format="float", example=10.00),
 *     @OA\Property(property="total_amount", type="number", format="float", example=135.00),
 *     @OA\Property(property="deposit_amount", type="number", format="float", example=40.50),
 *     @OA\Property(property="payment_status", type="string", enum={"pending", "authorized", "partially_captured", "captured", "failed", "refunded"}, example="pending"),
 *     @OA\Property(property="payment_type", type="string", enum={"deposit", "authorization", "both"}, example="deposit"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Activity",
 *     type="object",
 *     title="Activity",
 *     description="Modèle d'activité (paragliding, surfing, etc.)",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="organization_id", type="integer", example=1),
 *     @OA\Property(property="activity_type", type="string", example="paragliding"),
 *     @OA\Property(property="name", type="string", example="Vol Tandem Parapente"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="base_price", type="number", format="float", example=120.00),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="constraints_config", type="object", nullable=true),
 *     @OA\Property(property="pricing_config", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Instructor",
 *     type="object",
 *     title="Instructor",
 *     description="Modèle d'instructeur",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="organization_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="activity_types", type="array", @OA\Items(type="string"), example={"paragliding", "surfing"}),
 *     @OA\Property(property="license_number", type="string", nullable=true),
 *     @OA\Property(property="experience_years", type="integer", nullable=true, example=5),
 *     @OA\Property(property="max_sessions_per_day", type="integer", nullable=true, example=8),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="can_accept_instant_bookings", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Payment",
 *     type="object",
 *     title="Payment",
 *     description="Modèle de paiement",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="reservation_id", type="integer", example=1),
 *     @OA\Property(property="amount", type="number", format="float", example=120.00),
 *     @OA\Property(property="status", type="string", enum={"pending", "authorized", "partially_captured", "captured", "failed", "refunded"}, example="authorized"),
 *     @OA\Property(property="stripe_payment_intent_id", type="string", nullable=true, example="pi_1234567890"),
 *     @OA\Property(property="payment_method", type="string", nullable=true, example="card"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     title="Error",
 *     description="Réponse d'erreur standardisée",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Erreur de validation"),
 *     @OA\Property(property="errors", type="object", nullable=true,
 *         @OA\Property(property="field", type="array", @OA\Items(type="string"))
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Success",
 *     type="object",
 *     title="Success",
 *     description="Réponse de succès standardisée",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", type="object"),
 *     @OA\Property(property="message", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Pagination",
 *     type="object",
 *     title="Pagination",
 *     description="Informations de pagination standardisées",
 *     @OA\Property(property="current_page", type="integer", example=1, description="Page actuelle"),
 *     @OA\Property(property="per_page", type="integer", example=15, description="Nombre d'éléments par page"),
 *     @OA\Property(property="total", type="integer", example=100, description="Nombre total d'éléments"),
 *     @OA\Property(property="last_page", type="integer", example=7, description="Dernière page"),
 *     @OA\Property(property="from", type="integer", nullable=true, example=1, description="Index du premier élément de la page"),
 *     @OA\Property(property="to", type="integer", nullable=true, example=15, description="Index du dernier élément de la page"),
 *     @OA\Property(property="has_more_pages", type="boolean", example=true, description="Indique s'il y a d'autres pages")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     title="Paginated Response",
 *     description="Réponse paginée standardisée",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Données de la page"),
 *     @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
 * )
 */
class OpenApiSchemas
{
    // Ce fichier sert uniquement de conteneur pour les schémas OpenAPI
}

