# ✅ Checklist Déploiement Production

## 📋 Avant Déploiement

### Configuration Serveur
- [ ] Serveur avec PHP 8.2+ installé
- [ ] PostgreSQL 14+ installé et configuré
- [ ] Redis installé et configuré
- [ ] Nginx/Apache installé et configuré
- [ ] SSL/TLS configuré (Let's Encrypt)
- [ ] Firewall configuré (UFW)

### Configuration Application
- [ ] Fichier `.env` créé avec toutes les variables
- [ ] `APP_ENV=production` défini
- [ ] `APP_DEBUG=false` défini
- [ ] `APP_KEY` généré
- [ ] Clés Stripe **LIVE** configurées
- [ ] Webhook Stripe configuré dans dashboard
- [ ] Mailgun configuré
- [ ] Twilio configuré (si SMS activé)

### Base de Données
- [ ] Base de données PostgreSQL créée
- [ ] Utilisateur DB créé avec permissions
- [ ] Migrations exécutées (`php artisan migrate --force`)
- [ ] Seed production exécuté (si nécessaire)

### Code
- [ ] Dernière version du code déployée
- [ ] Dépendances installées (`composer install --no-dev`)
- [ ] Assets compilés (`npm run build`)
- [ ] Caches optimisés (`config:cache`, `route:cache`, `view:cache`)

### Permissions
- [ ] Permissions `storage/` correctes (755)
- [ ] Permissions `bootstrap/cache/` correctes (755)
- [ ] Propriétaire correct (www-data)

---

## 🚀 Déploiement

### Queue Workers
- [ ] Supervisor configuré
- [ ] Workers démarrés (`supervisorctl start`)
- [ ] Workers vérifiés (`supervisorctl status`)
- [ ] Logs workers vérifiés

### Scheduler
- [ ] Crontab configuré (`* * * * * php artisan schedule:run`)
- [ ] Scheduler testé (`php artisan schedule:run`)
- [ ] Liste des tâches vérifiée (`php artisan schedule:list`)

### Serveur Web
- [ ] Configuration Nginx/Apache vérifiée
- [ ] Site activé
- [ ] HTTPS fonctionnel
- [ ] Redirection HTTP → HTTPS active

---

## 🧪 Tests Post-Déploiement

### Tests Fonctionnels
- [ ] Accès au site (HTTP → HTTPS redirect)
- [ ] Création réservation test
- [ ] Paiement Stripe test (mode test d'abord)
- [ ] Webhook Stripe reçu
- [ ] Email confirmation envoyé
- [ ] Dashboard admin accessible
- [ ] Authentification admin fonctionnelle

### Tests Techniques
- [ ] Queue workers traitent les jobs
- [ ] Scheduler exécute les tâches
- [ ] Logs écrits correctement
- [ ] Cache fonctionne
- [ ] Base de données accessible
- [ ] Redis accessible

---

## 🔒 Sécurité

### Configuration
- [ ] `.env` non committé
- [ ] Mots de passe forts utilisés
- [ ] Firewall actif
- [ ] SSH sécurisé (clés uniquement)
- [ ] Fail2ban configuré (optionnel)

### Vérifications
- [ ] `APP_DEBUG=false` confirmé
- [ ] `APP_ENV=production` confirmé
- [ ] HTTPS forcé
- [ ] Headers sécurité configurés
- [ ] Logs ne contiennent pas d'informations sensibles

---

## 📊 Monitoring

### Configuration
- [ ] Logs rotation configurée
- [ ] Monitoring système configuré (optionnel)
- [ ] Alertes configurées (optionnel)

### Vérifications
- [ ] Logs accessibles
- [ ] Queue monitoring fonctionnel
- [ ] Espace disque suffisant
- [ ] CPU/Mémoire OK

---

## 💾 Backup

### Configuration
- [ ] Script backup base de données créé
- [ ] Crontab backup configuré
- [ ] Backup test restauré avec succès

### Vérifications
- [ ] Backups s'exécutent automatiquement
- [ ] Backups stockés dans emplacement sécurisé
- [ ] Rétention backups configurée (30 jours)

---

## 📝 Documentation

### Mise à Jour
- [ ] Documentation équipe mise à jour
- [ ] Accès serveur documenté
- [ ] Procédures d'urgence documentées
- [ ] Contacts support documentés

---

## ✅ Validation Finale

### Checklist Complète
- [ ] Tous les éléments ci-dessus cochés
- [ ] Tests fonctionnels passés
- [ ] Équipe formée
- [ ] Support prêt

### Go Live
- [ ] Client informé
- [ ] Mode Stripe changé en **LIVE**
- [ ] Monitoring actif
- [ ] Support disponible

---

**Date de validation** : _______________  
**Validé par** : _______________  
**Signature** : _______________

