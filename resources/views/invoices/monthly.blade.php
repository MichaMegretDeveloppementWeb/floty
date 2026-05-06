<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $invoiceNumber }}</title>
    <style>
        @page { margin: 32mm 18mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            color: #0f172a;
            font-size: 10pt;
            line-height: 1.45;
            margin: 0;
        }
        h1 {
            font-size: 18pt;
            font-weight: 600;
            margin: 0 0 4mm 0;
            letter-spacing: -0.01em;
        }
        h2 {
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin: 0 0 2mm 0;
        }
        .header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8mm;
            margin-bottom: 10mm;
        }
        .header-grid { display: table; width: 100%; }
        .header-grid > div {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .header-grid > div + div { text-align: right; }
        .meta-table { margin-bottom: 6mm; }
        .meta-table td {
            padding: 1mm 0;
            font-size: 9pt;
        }
        .meta-table td:first-child {
            color: #64748b;
            padding-right: 6mm;
        }
        .lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
        }
        .lines thead th {
            background: #f1f5f9;
            color: #475569;
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 3mm 2mm;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
        }
        .lines thead th.num,
        .lines tbody td.num { text-align: right; font-family: 'Courier New', monospace; }
        .lines tbody td {
            padding: 3mm 2mm;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9pt;
        }
        .lines tbody td.label {
            color: #0f172a;
        }
        .lines tbody td.detail {
            color: #64748b;
            font-size: 8pt;
        }
        .total-row td {
            border-top: 2px solid #0f172a;
            border-bottom: none !important;
            font-weight: 600;
            padding-top: 4mm !important;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 6mm;
            margin-top: 8mm;
            color: #94a3b8;
            font-size: 7.5pt;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>Facture</h1>
        <div class="header-grid">
            <div>
                <h2>Émetteur</h2>
                <strong>{{ $issuer['name'] }}</strong><br>
                @if(!empty($issuer['addressLine1']))
                    {{ $issuer['addressLine1'] }}<br>
                @endif
                @if(!empty($issuer['addressLine2']))
                    {{ $issuer['addressLine2'] }}<br>
                @endif
                @if(!empty($issuer['postalCode']) || !empty($issuer['city']))
                    {{ trim(($issuer['postalCode'] ?? '').' '.($issuer['city'] ?? '')) }}<br>
                @endif
                @if(!empty($issuer['siren']))
                    SIREN&nbsp;: {{ $issuer['siren'] }}<br>
                @endif
                @if(!empty($issuer['contactEmail']))
                    {{ $issuer['contactEmail'] }}
                @endif
            </div>
            <div>
                <h2>Destinataire</h2>
                <strong>{{ $company['legalName'] }}</strong><br>
                @if(!empty($company['siren']))
                    SIREN&nbsp;: {{ $company['siren'] }}<br>
                @endif
                @if(!empty($company['city']))
                    {{ $company['city'] }}
                @endif
            </div>
        </div>
    </header>

    <table class="meta-table">
        <tr>
            <td>Numéro de facture</td>
            <td><strong>{{ $invoiceNumber }}</strong></td>
        </tr>
        <tr>
            <td>Période facturée</td>
            <td>{{ $periodLabel }}</td>
        </tr>
        <tr>
            <td>Date d'émission</td>
            <td>{{ $generatedAtLabel }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Véhicule</th>
                <th class="num">Jours utilisés</th>
                <th>Décomposition</th>
                <th class="num">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td class="label">{{ $line['vehicleLabel'] }}</td>
                    <td class="num">{{ $line['daysUsed'] }}</td>
                    <td class="detail">
                        @php
                            $parts = [];
                            if ($line['monthsBilled'] > 0) {
                                $parts[] = $line['monthsBilled'].' mois × '.$line['monthlyRate'];
                            }
                            if ($line['weeksBilled'] > 0) {
                                $parts[] = $line['weeksBilled'].' sem × '.$line['weeklyRate'];
                            }
                            if ($line['daysBilled'] > 0) {
                                $parts[] = $line['daysBilled'].' j × '.$line['dailyRate'];
                            }
                        @endphp
                        {{ implode(' + ', $parts) }}
                    </td>
                    <td class="num">{{ $line['totalLabel'] }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">Total HT</td>
                <td class="num">{{ $totalLabel }}</td>
            </tr>
        </tbody>
    </table>

    <footer class="footer">
        Facture générée automatiquement par Floty (gestion de flotte)&nbsp;·
        Document numérique — non modifiable post-émission&nbsp;·
        Empreinte d'intégrité&nbsp;: {{ substr($pdfHashPlaceholder, 0, 16) }}…
    </footer>
</body>
</html>
