# API Frontend Reference (SaaS Multi-niche)

## Principes Généraux
- **Base URL** : /api/v1/
- **Auth** : endpoints uth/login, uth/me, uth/logout (token Sanctum). Envoyer le token dans Authorization: Bearer {token}.
- **Multi-tenant** :
  - Header obligatoire : X-Organization-ID: <tenant_id> (cf. tests MultiTenantTest).
  - Optionnel: conserver organization_id en session (admin). Pour les frontends SPA, stocker dans store global.
- **Branding & features** : GET /branding (voir BrandingResolverTest) pour thèmes UI ; fallback global si non configuré.

## Auth & Profil
### POST /auth/login
Payload : { "email": "user@tenant.com", "password": "secret" }
Réponse : { success, data: { token, user, instructor?, client?, organization } }
- Si le user est instructeur, data.instructor hydrate agenda.

### GET /auth/me
Retourne le profil enrichi (	ests/Feature/AuthControllerGeneralizedTest).

### POST /auth/logout
Invalidate token.

## Activités & Modules
### GET /activities
Query : ctivity_type, is_active. Retourne les activités du tenant (+ pricing_config, constraints_config, metadata).
- Utiliser pour construire les écrans catalogues, labels et prix dynamiques.

### GET /activities/{id}
Retour complet + sessions (ctivitySessions).

## Réservations
### POST /reservations
Payload minimal :
`
{
  "customer_email": "client@example.com",
  "customer_first_name": "Alice",
  "customer_last_name": "Wave",
  "activity_id": 2,
  "participants_count": 2,
  "metadata": {
    "swimming_level": "advanced"
  },
  "payment_type": "deposit",
  "payment_method_id": "pm_xxx"
}
`
- metadata doit inclure les contraintes requises (ex. swimming_level pour surf).
- options peut être transmis dès la création.
- Réponse : { reservation, payment: { status, client_secret } }.

### POST /reservations/{uuid}/add-options
Pour client/instructor (public side). Utiliser stage pending, efore_flight, fter_flight (conversion gérée côté backend).

### GET /reservations/{uuid}
Inclut ctivitySessions, instructor, ctivity, site, payments.

## Admin Réservations
### GET /admin/reservations
Query : status, ctivity_type, instructor_id, pagination (page, per_page).
Réponse : { success, data: [ ... ], pagination: { current_page, per_page, total, ... } } (cf. ReservationAdminControllerGeneralizedTest).

### POST /admin/reservations/{id}/add-options
- Payload : { options: [{ id, quantity }], stage?: 'before_flight' }
- Retour : réservation avec options mises à jour.

### POST /admin/reservations/{id}/schedule
- Payload : scheduled_at (ISO 8601), scheduled_time (HH:mm), instructor_id, site_id.
- Gère validations (qualifications, disponibilités).

### PUT /admin/reservations/{id}/assign
Alias legacy (mêmes règles).

### POST /admin/reservations/{id}/complete
Marque la réservation + sessions completed, déclenche capture paiement si besoin (tests E2E).

## Activity Sessions
### GET /activity-sessions
Query : ctivity_id, instructor_id, status, start_date, nd_date. Retour paginé : { data: [...], pagination: {...} }.

### GET /activity-sessions/{id}
Détails session (inclut eservation, ctivity, instructor).

## Instructors
### GET /instructors
Query : ctivity_type, is_active. Utilisé pour roster / assignations.

### GET /admin/instructors/{id}/calendar
Vue planning instructeur (tests InstructorServiceTest).

### POST /admin/instructors
Créer/mettre à jour instructeur (activité multiples via ctivity_types).

## Sites & Ressources
### GET /sites
- Public : seuls is_active = true sont renvoyés.
- Admin : ajouter ?is_active=false pour voir l’ensemble.
- Query : difficulty_level, search.
- Réponse paginée { data, pagination }.

### POST /admin/sites
Créer site (besoin pour back-office).

### GET /admin/resources
Gestion navettes, équipements (types : ehicle, 	andem_glider, quipment).

## Stats & Dashboard
### GET /admin/dashboard/summary
KPI multi-activité (Sessions, conversions). À exploiter dans dmin-portal.

### GET /admin/dashboard/top-instructors
Optionnel : query ctivity_type.

## Limitations & Headers
- Toujours inclure Accept: application/json, Content-Type: application/json.
- Temps de réponse dépend des filtres ; prévoir loaders + caches.
- Rate limit : endpoints publics 60/min, auth 30/min (tests RateLimitingTest). Monitorer X-RateLimit-* dans le front.

## Ressources complémentaires
- docs/PLAN_CORRECTION_INCOHERENCES.md : détail rétrocompatibilité & modules.
- docs/GUIDE_MIGRATION_MULTI_NICHE.md : examples API multi-activité.
- Tests 	ests/Feature/... : se référer aux payloads exacts.

