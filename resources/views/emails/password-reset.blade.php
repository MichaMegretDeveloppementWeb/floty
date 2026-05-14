<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation mot de passe Floty</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 24px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px; }
        h1 { font-size: 20px; font-weight: 600; margin: 0 0 16px; color: #0f172a; }
        p { line-height: 1.6; margin: 0 0 16px; font-size: 15px; }
        .button { display: inline-block; background: #0f172a; color: #fff !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 15px; }
        .meta { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
        .url-fallback { word-break: break-all; font-family: monospace; font-size: 12px; color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Réinitialisation de votre mot de passe</h1>

        <p>
            @if ($firstName !== '')Bonjour {{ $firstName }},@else Bonjour, @endif
        </p>

        <p>
            Vous recevez cet e-mail car une demande de réinitialisation de
            mot de passe a été effectuée pour votre compte Floty.
        </p>

        <p>
            <a href="{{ $resetUrl }}" class="button">Réinitialiser mon mot de passe</a>
        </p>

        <p>
            Ce lien est valide pendant <strong>{{ $expireMinutes }} minutes</strong>.
            Si vous n'êtes pas à l'origine de cette demande, vous pouvez
            ignorer cet e-mail · votre mot de passe actuel reste inchangé.
        </p>

        <div class="meta">
            <p style="margin: 0 0 8px;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur ·</p>
            <p class="url-fallback">{{ $resetUrl }}</p>
        </div>

        <div class="meta">
            Floty · Gestion de flotte partagée
        </div>
    </div>
</body>
</html>
