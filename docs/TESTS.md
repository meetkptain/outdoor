# 🧪 Tests - Documentation

## Vue d'Ensemble

Le système dispose d'une suite de tests couvrant les fonctionnalités critiques. Les tests sont organisés en deux catégories principales :

- **Tests Unitaires** : Testent les services et la logique métier isolément
- **Tests Feature** : Testent les flux complets et les endpoints API

## Structure des Tests

```
tests/
├── Unit/
│   └── Services/
│       ├── ReservationServiceTest.php          # Tests création réservation
│       ├── ReservationServiceValidationTest.php # Tests validations contraintes
│       ├── PaymentServiceTest.php             # Tests paiements
│       └── VehicleServiceTest.php              # Tests navettes
├── Feature/
│   ├── Api/
│   │   ├── AuthTest.php                        # Tests authentification
│   │   └── ReservationTest.php                 # Tests endpoints réservation
│   ├── Webhooks/
│   │   └── StripeWebhookTest.php               # Tests webhooks Stripe
│   └── ReservationFlowTest.php                 # Tests flux complets
```

## Tests Unitaires

### ReservationServiceTest

**Fichier** : `tests/Unit/Services/ReservationServiceTest.php`

**Tests inclus** :
- ✅ Création d'une réservation
- ✅ Calcul des montants avec coupon
- ✅ Application des remises

**Exécution** :
```bash
php artisan test --filter ReservationServiceTest
```

### ReservationServiceValidationTest

**Fichier** : `tests/Unit/Services/ReservationServiceValidationTest.php`

**Tests inclus** :
- ✅ Validation poids minimum (40kg)
- ✅ Validation poids maximum (120kg)
- ✅ Validation taille minimum (140cm)
- ✅ Acceptation réservation avec données valides

**Exécution** :
```bash
php artisan test --filter ReservationServiceValidationTest
```

### PaymentServiceTest

**Fichier** : `tests/Unit/Services/PaymentServiceTest.php`

**Tests inclus** :
- ✅ Validation que paiement peut être capturé
- ✅ Validation que paiement ne peut pas être capturé si déjà capturé
- ✅ Validation qu'un paiement peut être remboursé
- ✅ Validation qu'un paiement ne peut pas être remboursé si déjà remboursé

**Note** : Les tests de création PaymentIntent nécessitent un mock Stripe complet (à implémenter).

**Exécution** :
```bash
php artisan test --filter PaymentServiceTest
```

### VehicleServiceTest

**Fichier** : `tests/Unit/Services/VehicleServiceTest.php`

**Tests inclus** :
- ✅ Récupération capacité navette par défaut
- ✅ Récupération capacité depuis spécifications
- ✅ Calcul nombre de passagers maximum
- ✅ Vérification capacité disponible
- ✅ Rejet si capacité dépassée
- ✅ Calcul places disponibles

**Exécution** :
```bash
php artisan test --filter VehicleServiceTest
```

## Tests Feature

### ReservationTest

**Fichier** : `tests/Feature/Api/ReservationTest.php`

**Tests inclus** :
- ✅ Création réservation via API
- ✅ Récupération réservation par UUID
- ✅ Validation des données de réservation

**Exécution** :
```bash
php artisan test --filter ReservationTest
```

### ReservationFlowTest

**Fichier** : `tests/Feature/ReservationFlowTest.php`

**Tests inclus** :
- ✅ Flux complet de réservation (création → assignation → capture → complétion)
- ✅ Ajout d'options après création
- ✅ Validation contraintes biplaceur (limite vols/jour)
- ✅ Validation pause obligatoire entre vols

**Exécution** :
```bash
php artisan test --filter ReservationFlowTest
```

### StripeWebhookTest

**Fichier** : `tests/Feature/Webhooks/StripeWebhookTest.php`

**Tests inclus** :
- ✅ Webhook `payment_intent.succeeded`
- ✅ Webhook `payment_intent.payment_failed`
- ✅ Webhook `payment_intent.requires_capture`
- ✅ Webhook `charge.refunded`
- ✅ Rejet webhook avec signature invalide

**Note** : Les tests de signature nécessitent une configuration appropriée pour les tests.

**Exécution** :
```bash
php artisan test --filter StripeWebhookTest
```

## Exécution des Tests

### Tous les tests
```bash
php artisan test
```

### Tests unitaires uniquement
```bash
php artisan test --testsuite=Unit
```

### Tests feature uniquement
```bash
php artisan test --testsuite=Feature
```

### Un fichier spécifique
```bash
php artisan test tests/Unit/Services/ReservationServiceTest.php
```

### Un test spécifique
```bash
php artisan test --filter test_can_create_reservation
```

### Avec couverture de code
```bash
php artisan test --coverage
```

## Configuration

### Variables d'Environnement pour Tests

Créer un fichier `.env.testing` :
```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_test_...
```

### Base de Données de Test

Par défaut, les tests utilisent `RefreshDatabase`, ce qui :
- Crée une base de données temporaire
- Exécute toutes les migrations
- Nettoie après chaque test

Pour utiliser SQLite en mémoire (plus rapide) :
```php
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## Mocks et Factories

### Factories Laravel

Les factories sont utilisées pour créer des données de test :
- `ReservationFactory`
- `PaymentFactory`
- `OptionFactory`
- `UserFactory`
- `BiplaceurFactory`
- `ResourceFactory`

### Mocks

Les tests utilisent **Mockery** pour mocker les services :
```php
$paymentServiceMock = Mockery::mock(PaymentService::class);
$paymentServiceMock->shouldReceive('createPaymentIntent')
    ->once()
    ->andReturn($payment);
```

## Tests Manquants (À Implémenter)

### Tests Unitaires

1. **BiplaceurServiceTest**
   - Validation limites de vols
   - Validation pauses obligatoires
   - Validation compétences/certifications

2. **NotificationServiceTest**
   - Envoi emails
   - Envoi SMS
   - Programmation rappels

3. **OptionServiceTest**
   - Calcul prix options
   - Validation disponibilité options

### Tests Feature

1. **AdminTest**
   - Dashboard statistiques
   - Gestion biplaceurs
   - Gestion ressources

2. **PaymentFlowTest**
   - Flux paiement complet avec Stripe
   - Tests avec Stripe Test Mode
   - Gestion erreurs paiement

3. **BiplaceurTest**
   - Authentification biplaceur
   - Consultation planning
   - Mise à jour statut vol

### Tests d'Intégration

1. **EndToEndReservationTest**
   - Flux complet depuis création jusqu'à complétion
   - Avec tous les services réels

2. **StripeIntegrationTest**
   - Tests avec Stripe Test Mode
   - Webhooks réels (avec ngrok)
   - Gestion erreurs réseau

## Bonnes Pratiques

1. **Isolation** : Chaque test doit être indépendant
2. **Noms descriptifs** : Utiliser `test_should_...` ou `test_can_...`
3. **Arrange-Act-Assert** : Organiser le code en 3 sections
4. **Mocks appropriés** : Mocker les dépendances externes (Stripe, emails, etc.)
5. **Données réalistes** : Utiliser des factories pour des données cohérentes

## Exemple de Test

```php
public function test_rejects_reservation_with_weight_below_minimum(): void
{
    // Arrange
    $data = [
        'customer_email' => 'test@example.com',
        'customer_weight' => 35, // En dessous du minimum
        'flight_type' => 'tandem',
        'participants_count' => 1,
    ];

    // Act & Assert
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Poids minimum requis: 40kg');

    $this->service->createReservation($data);
}
```

## CI/CD

### GitHub Actions

Exemple de workflow pour exécuter les tests :

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install Dependencies
        run: composer install
      - name: Run Tests
        run: php artisan test
```

## Prochaines Étapes

1. ✅ Tests unitaires services critiques
2. ✅ Tests feature flux réservation
3. ✅ Tests webhooks Stripe
4. ⏳ Tests paiements complets avec mocks Stripe
5. ⏳ Tests d'intégration end-to-end
6. ⏳ Tests performance et charge

---

**Note** : Les tests sont en cours de développement. Certains tests nécessitent une configuration supplémentaire (mocks Stripe, variables d'environnement) pour fonctionner complètement.

