# 📅 Scheduler Laravel - Tâches Automatiques

## Vue d'ensemble

Le système utilise le **Scheduler Laravel** pour exécuter automatiquement des tâches récurrentes. Toutes les tâches sont configurées dans `routes/console.php`.

## Tâches Configurées

### 1. Rappels 24h avant les vols
**Commande**: `reminders:send`  
**Fréquence**: Tous les jours à 8h00  
**Description**: Envoie automatiquement les emails de rappel aux clients qui ont un vol programmé dans les 24 prochaines heures.

**Configuration**:
```php
Schedule::command(SendRemindersCommand::class)
    ->dailyAt('08:00')
    ->description('Envoyer les rappels 24h avant les vols');
```

**Fonctionnement**:
- Récupère toutes les réservations avec statut `scheduled` ou `confirmed`
- Vérifie que `reminder_sent = false`
- Vérifie que le vol est dans les 24 prochaines heures
- Envoie l'email via `NotificationService::sendReminder()`
- Met à jour `reminder_sent = true` et `reminder_sent_at = now()`

**Options**:
- `--hours=24` : Nombre d'heures avant le vol (par défaut: 24)

**Exécution manuelle**:
```bash
php artisan reminders:send
php artisan reminders:send --hours=48  # Pour 48h avant
```

---

### 2. Vérification autorisations expirées
**Commande**: `payments:check-expired-auths`  
**Fréquence**: Toutes les heures  
**Description**: Vérifie les autorisations Stripe qui ont expiré (> 7 jours) et nécessitent une réautorisation.

**Configuration**:
```php
Schedule::command(CheckExpiredAuthorizationsCommand::class)
    ->hourly()
    ->description('Vérifier les autorisations Stripe expirées');
```

**Fonctionnement**:
- Récupère les paiements avec `status = 'requires_capture'` et `type = 'authorization'`
- Vérifie que `created_at < now() - 7 days`
- Log les autorisations expirées pour action manuelle
- TODO: Implémenter réautorisation automatique si SetupIntent sauvegardé

**Exécution manuelle**:
```bash
php artisan payments:check-expired-auths
```

---

### 3. Nettoyage données anciennes
**Commande**: `cleanup:old-data`  
**Fréquence**: Hebdomadaire (dimanche à 2h00)  
**Description**: Supprime les données anciennes (réservations annulées, notifications) pour maintenir la base de données propre.

**Configuration**:
```php
Schedule::command(CleanupOldDataCommand::class, ['--days' => 365])
    ->weeklyOn(0, '02:00')
    ->description('Nettoyer les anciennes données');
```

**Fonctionnement**:
- Supprime les réservations avec statut `cancelled` ou `refunded` de plus de 365 jours
- Supprime les notifications envoyées (`status = 'sent'`) de plus de 365 jours
- Optionnel: Nettoyage des logs (si table existe)

**Options**:
- `--days=365` : Nombre de jours de conservation (par défaut: 365)

**Exécution manuelle**:
```bash
php artisan cleanup:old-data
php artisan cleanup:old-data --days=180  # Conserver seulement 6 mois
```

---

### 4. Rapport quotidien
**Commande**: `reports:daily`  
**Fréquence**: Tous les jours à 20h00  
**Description**: Génère un rapport quotidien avec statistiques (réservations, vols, CA) et l'envoie par email.

**Configuration**:
```php
Schedule::command(GenerateDailyReportCommand::class, ['--email'])
    ->dailyAt('20:00')
    ->description('Générer et envoyer le rapport quotidien');
```

**Fonctionnement**:
- Calcule les statistiques du jour:
  - Nouvelles réservations
  - Vols planifiés
  - Vols complétés
  - Annulations
  - Chiffre d'affaires
  - Évolution vs hier
- Affiche un tableau dans la console
- Envoie par email si option `--email` activée
- Log les résultats

**Options**:
- `--email` : Envoyer le rapport par email au admin

**Exécution manuelle**:
```bash
php artisan reports:daily
php artisan reports:daily --email
```

---

## Configuration du Cron

Pour que le scheduler fonctionne, vous devez ajouter cette ligne dans votre crontab:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Sur Windows (avec Task Scheduler)

1. Créez un fichier batch `run-scheduler.bat`:
```batch
cd C:\path-to-project
php artisan schedule:run
```

2. Configurez une tâche planifiée Windows pour exécuter ce script toutes les minutes.

### Sur Linux/Mac

```bash
crontab -e
```

Ajoutez:
```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Vérification du Scheduler

### Voir les tâches planifiées
```bash
php artisan schedule:list
```

### Tester l'exécution (sans attendre)
```bash
php artisan schedule:run
```

### Voir les logs d'exécution
Les logs sont dans `storage/logs/laravel.log` ou via:
```bash
tail -f storage/logs/laravel.log
```

---

## Personnalisation

### Modifier les horaires

Éditez `routes/console.php`:

```php
// Rappels à 7h au lieu de 8h
Schedule::command(SendRemindersCommand::class)
    ->dailyAt('07:00');

// Rapport à 19h au lieu de 20h
Schedule::command(GenerateDailyReportCommand::class, ['--email'])
    ->dailyAt('19:00');
```

### Ajouter une nouvelle tâche

1. Créez la commande dans `app/Console/Commands/`:
```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class MyCustomCommand extends Command
{
    protected $signature = 'my:custom-command';
    protected $description = 'Ma tâche personnalisée';

    public function handle(): int
    {
        // Votre logique ici
        return Command::SUCCESS;
    }
}
```

2. Ajoutez dans `routes/console.php`:
```php
Schedule::command(MyCustomCommand::class)
    ->daily()
    ->description('Ma tâche personnalisée');
```

---

## Troubleshooting

### Le scheduler ne s'exécute pas
- Vérifiez que le cron est configuré: `crontab -l`
- Vérifiez les permissions: `chmod +x artisan`
- Vérifiez les logs: `tail -f storage/logs/laravel.log`

### Une commande échoue
- Exécutez-la manuellement pour voir l'erreur: `php artisan reminders:send`
- Vérifiez les dépendances (services, modèles, etc.)
- Vérifiez les variables d'environnement

### Les rappels ne sont pas envoyés
- Vérifiez que les réservations ont `scheduled_at` défini
- Vérifiez que `reminder_sent = false`
- Vérifiez la configuration email dans `.env`
- Testez manuellement: `php artisan reminders:send --hours=24`

---

## Commandes Disponibles

| Commande | Description | Options |
|----------|------------|---------|
| `reminders:send` | Envoyer rappels | `--hours=24` |
| `payments:check-expired-auths` | Vérifier autorisations | - |
| `cleanup:old-data` | Nettoyer données | `--days=365` |
| `reports:daily` | Rapport quotidien | `--email` |

---

## Notes Importantes

1. **Performance**: Les commandes sont exécutées en série. Si une commande est lente, elle peut bloquer les suivantes.

2. **Queue Workers**: Pour les tâches lourdes (envoi d'emails en masse), considérez utiliser des Jobs Laravel avec Queue.

3. **Timezone**: Assurez-vous que `APP_TIMEZONE` dans `.env` est correctement configuré.

4. **Logs**: Toutes les commandes loggent leurs actions. Surveillez les logs pour détecter les problèmes.

5. **Tests**: Testez toujours les commandes manuellement avant de les mettre en production.

---

## Prochaines Améliorations

- [ ] Implémenter réautorisation automatique pour autorisations expirées
- [ ] Envoyer rapport quotidien par email avec template HTML
- [ ] Ajouter notifications SMS pour rappels
- [ ] Créer dashboard pour visualiser l'exécution des tâches
- [ ] Ajouter métriques et monitoring (Sentry, etc.)

