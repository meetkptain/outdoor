# 📋 Documentation : Tests E2E (End-to-End)

**Date de création** : 2025-11-07  
**Version** : 1.0.0

---

## 🎯 Objectif

Les tests E2E (End-to-End) valident des scénarios utilisateur complets de bout en bout, en testant plusieurs endpoints API ensemble dans un flux cohérent.

---

## 📁 Structure

Les tests E2E sont situés dans le dossier `tests/E2E/` :

- `CompleteReservationE2ETest.php` - Scénario complet de réservation
- `AuthenticationE2ETest.php` - Scénario d'inscription et connexion
- `AdminWorkflowE2ETest.php` - Scénario de workflow admin
- `MultiActivityE2ETest.php` - Scénario multi-activités

---

## 🧪 Tests Disponibles

### 1. CompleteReservationE2ETest

**Scénario complet de réservation avec coupon et options**

Teste le flux complet :
1. Client crée une réservation avec coupon
2. Admin assigne date et ressources
3. Client ajoute des options
4. Admin capture le paiement
5. Admin marque comme complété

**Méthode de test** : `test_complete_reservation_flow_with_coupon_and_options()`

### 2. AuthenticationE2ETest

**Scénario complet d'inscription et connexion**

Teste le flux complet :
1. Inscription d'un nouvel utilisateur
2. Connexion
3. Récupération du profil
4. Mise à jour du profil
5. Déconnexion

**Méthodes de test** :
- `test_complete_registration_and_login_flow()`
- `test_login_with_wrong_credentials()`

### 3. AdminWorkflowE2ETest

**Scénario complet de workflow admin**

Teste le flux complet :
1. Admin consulte le dashboard
2. Admin liste les réservations
3. Admin consulte une réservation
4. Admin assigne des ressources
5. Admin consulte les statistiques
6. Admin filtre les réservations

**Méthode de test** : `test_complete_admin_workflow()`

### 4. MultiActivityE2ETest

**Scénario multi-activités**

Teste le flux complet :
1. Client consulte les activités disponibles
2. Client crée une réservation paragliding
3. Client crée une réservation surfing
4. Vérification de l'isolation des données
5. Client consulte ses réservations
6. Filtrage par type d'activité

**Méthodes de test** :
- `test_multi_activity_reservation_flow()`
- `test_instructor_supports_multiple_activities()`

---

## 🚀 Exécution

### Exécuter tous les tests E2E

```bash
php artisan test tests/E2E
```

### Exécuter un test spécifique

```bash
php artisan test tests/E2E/CompleteReservationE2ETest.php
```

### Exécuter une méthode spécifique

```bash
php artisan test tests/E2E/CompleteReservationE2ETest.php::test_complete_reservation_flow_with_coupon_and_options
```

### Exécuter avec arrêt sur la première erreur

```bash
php artisan test tests/E2E --stop-on-failure
```

---

## 🔧 Configuration

### Mock PaymentService

Tous les tests E2E mockent le `PaymentService` pour éviter les appels Stripe réels :

```php
protected function mockPaymentService(): void
{
    $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
    
    // Mock createPaymentIntent
    $paymentServiceMock->shouldReceive('createPaymentIntent')
        ->andReturnUsing(function ($reservation, $amount, $type) {
            return Payment::factory()->create([
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'status' => 'authorized',
                'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
            ]);
        });
    
    // Mock capturePayment
    $paymentServiceMock->shouldReceive('capturePayment')
        ->andReturnUsing(function ($payment, $amount = null) {
            $payment->update([
                'status' => 'captured',
                'captured_at' => now(),
            ]);
            return $payment;
        });
    
    $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);
}
```

### Contexte d'Organisation

Tous les tests E2E utilisent le header `X-Organization-ID` pour définir le contexte tenant :

```php
$response = $this->withHeader('X-Organization-ID', $this->organization->id)
    ->getJson('/api/v1/activities');
```

---

## ✅ Bonnes Pratiques

1. **Isolation** : Chaque test est isolé avec `RefreshDatabase`
2. **Setup** : Configuration complète dans `setUp()` pour éviter la duplication
3. **Mocking** : Services externes (Stripe) sont mockés
4. **Assertions** : Vérifications complètes à chaque étape
5. **Documentation** : Commentaires clairs pour chaque étape du scénario

---

## 📊 Couverture

Les tests E2E couvrent :

- ✅ Création de réservation complète
- ✅ Application de coupons
- ✅ Ajout d'options
- ✅ Assignation de ressources
- ✅ Capture de paiement
- ✅ Complétion de réservation
- ✅ Inscription et connexion
- ✅ Workflow admin complet
- ✅ Multi-activités
- ✅ Isolation des données par tenant

---

## 🔍 Dépannage

### Erreur : "Organization not found"

Assurez-vous que le header `X-Organization-ID` est présent dans toutes les requêtes.

### Erreur : "PaymentService not found"

Vérifiez que le mock est correctement configuré dans `setUp()`.

### Erreur : "Table does not exist"

Assurez-vous que `RefreshDatabase` est utilisé et que les migrations sont à jour.

---

## 📚 Références

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- `tests/E2E/` - Implémentation des tests

---

**Date de mise à jour** : 2025-11-07  
**Auteur** : Auto (IA Assistant)

