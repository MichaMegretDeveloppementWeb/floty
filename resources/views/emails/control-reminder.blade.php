<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rappel de contrôle Floty</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px; }
        h1 { font-size: 20px; font-weight: 600; margin: 0 0 16px; color: #0f172a; }
        p { line-height: 1.6; margin: 0 0 16px; font-size: 15px; }
        .button { display: inline-block; background: #0f172a; color: #fff !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 15px; }
        .meta { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Rappel de contrôle réglementaire</h1>

        <p>
            @if ($recipientName !== ''){{ 'Bonjour '.$recipientName.',' }}@else{{ 'Bonjour,' }}@endif
        </p>

        <p>{{ $bodySentence }}</p>

        <p>
            <a href="{{ $url }}" class="button">Voir le contrôle</a>
        </p>

        <div class="meta">
            Floty · Gestion de flotte partagée
        </div>
    </div>
</body>
</html>
