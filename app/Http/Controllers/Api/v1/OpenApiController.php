<?php

namespace App\Http\Controllers\Api\v1;

/**
 * @OA\Info(
 *     title="SaaS Multi-Niche API",
 *     version="1.0.0",
 *     description="API RESTful pour système de réservation multi-niche (paragliding, surfing, diving, etc.)",
 *     @OA\Contact(
 *         email="support@example.com"
 *     ),
 *     @OA\License(
 *         name="Proprietary"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="API Server (Développement)"
 * )
 * @OA\Server(
 *     url="https://api.example.com",
 *     description="API Server (Production)"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoints d'authentification"
 * )
 *
 * @OA\Tag(
 *     name="Reservations",
 *     description="Gestion des réservations"
 * )
 *
 * @OA\Tag(
 *     name="Activities",
 *     description="Gestion des activités (paragliding, surfing, etc.)"
 * )
 *
 * @OA\Tag(
 *     name="Instructors",
 *     description="Gestion des instructeurs"
 * )
 *
 * @OA\Tag(
 *     name="Payments",
 *     description="Gestion des paiements"
 * )
 *
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Statistiques et tableaux de bord (Admin)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum Bearer Token"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="organization",
 *     type="apiKey",
 *     in="header",
 *     name="X-Organization-ID",
 *     description="ID de l'organisation (tenant) pour l'isolation multi-tenant"
 * )
 */
class OpenApiController
{
    // Ce contrôleur sert uniquement de conteneur pour les annotations OpenAPI globales
}

