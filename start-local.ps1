# Script PowerShell pour démarrer l'application en local (Windows)

# S'assurer qu'on est dans le bon répertoire
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptPath

# Vérifier que artisan existe
if (-not (Test-Path artisan)) {
    Write-Host "❌ Erreur : Fichier artisan non trouvé !" -ForegroundColor Red
    Write-Host "   Assurez-vous d'exécuter le script depuis la racine du projet Laravel." -ForegroundColor Yellow
    Write-Host "   Répertoire actuel : $(Get-Location)" -ForegroundColor Yellow
    exit 1
}

Write-Host "🚀 Démarrage application locale..." -ForegroundColor Green
Write-Host "📁 Répertoire : $(Get-Location)" -ForegroundColor Cyan
Write-Host ""

# Vérifier que .env existe
if (-not (Test-Path .env)) {
    Write-Host "⚠️  Fichier .env manquant. Création..." -ForegroundColor Yellow
    if (Test-Path .env.example) {
        Copy-Item .env.example .env
        Write-Host "✅ Copié depuis .env.example" -ForegroundColor Green
    } else {
        "APP_ENV=local" | Out-File -FilePath .env -Encoding UTF8
        Write-Host "✅ Fichier .env créé" -ForegroundColor Green
    }
    php artisan key:generate
    Write-Host "✅ Clé application générée" -ForegroundColor Green
}

# Créer la base SQLite si nécessaire
if (-not (Test-Path database/database.sqlite)) {
    Write-Host "📦 Création base SQLite..." -ForegroundColor Cyan
    New-Item -ItemType File -Path database/database.sqlite -Force | Out-Null
    Write-Host "✅ Base SQLite créée" -ForegroundColor Green
}

# Exécuter les migrations
Write-Host "🔄 Exécution migrations..." -ForegroundColor Cyan
php artisan migrate --force

# Compiler les assets
Write-Host "📦 Compilation assets..." -ForegroundColor Cyan
npm run build 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠️  npm run build a échoué, continuons quand même..." -ForegroundColor Yellow
}

# Nettoyer les caches
Write-Host "🧹 Nettoyage caches..." -ForegroundColor Cyan
php artisan optimize:clear

# Démarrer le serveur
Write-Host ""
Write-Host "✅ Application prête !" -ForegroundColor Green
Write-Host "🌐 Serveur démarré sur http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "📝 Commandes utiles :" -ForegroundColor Yellow
Write-Host "   - Voir les routes : php artisan route:list"
Write-Host "   - Lancer les tests : php artisan test"
Write-Host "   - Tinker : php artisan tinker"
Write-Host ""
php artisan serve

