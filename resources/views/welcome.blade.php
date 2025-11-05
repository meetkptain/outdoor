<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🪂 Système de Réservation Parapente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
        .emoji {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .status {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        .links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .link {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .link-secondary {
            background: #e5e7eb;
            color: #333;
        }
        .link-secondary:hover {
            background: #d1d5db;
        }
        .info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.9rem;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">🪂</div>
        <h1>Système de Réservation Parapente</h1>
        <div class="status">✅ Application Opérationnelle</div>
        <p>
            Bienvenue dans votre système de gestion de réservations parapente.
            L'application est prête à être utilisée.
        </p>
        <div class="links">
            <a href="/api/v1/reservations" class="link">API Documentation</a>
            <a href="/api/v1/admin" class="link link-secondary">Admin Panel</a>
        </div>
        <div class="info">
            <p>
                <strong>Laravel</strong> {{ app()->version() }} | 
                <strong>PHP</strong> {{ PHP_VERSION }} | 
                <strong>Environnement</strong> {{ app()->environment() }}
            </p>
        </div>
    </div>
</body>
</html>

