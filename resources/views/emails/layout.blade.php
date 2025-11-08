<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $branding['name'] ?? 'Parapente Club')</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            border-top: 1px solid #e9ecef;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #667eea;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #5568d3;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
        }
        .reservation-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .reservation-details dt {
            font-weight: 600;
            margin-top: 10px;
        }
        .reservation-details dd {
            margin-left: 0;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    @php
        $brandName = data_get($branding ?? [], 'name', 'Parapente Club');
        $brandEmoji = data_get($branding ?? [], 'emoji', '🪂');
        $supportEmail = data_get($branding ?? [], 'support.email', 'contact@parapente-club.com');
        $signatureCompany = data_get($branding ?? [], 'signature.company', $brandName);
    @endphp
    <div class="email-container">
        <div class="email-header">
            <h1>{{ trim($brandEmoji.' '.$brandName) }}</h1>
        </div>
        
        <div class="email-body">
            @yield('content')
        </div>
        
        <div class="email-footer">
            <p><strong>{{ $signatureCompany }}</strong></p>
            <p>Pour toute question, contactez-nous à <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
            <p style="margin-top: 15px; font-size: 11px; color: #999;">
                © {{ date('Y') }} {{ $brandName }}. Tous droits réservés.
            </p>
        </div>
    </div>
</body>
</html>
