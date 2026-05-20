<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Annexe de facture {{ $invoiceNumber }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            font-size: 9.5pt;
            line-height: 1.5;
            margin: 0;
        }
        h1 {
            font-size: 18pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        h2 {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin: 0 0 2mm 0;
        }
        .header {
            margin-bottom: 8mm;
        }
        .header-title {
            margin-bottom: 5mm;
        }
        .invoice-ref {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 0.02em;
            color: #475569;
            margin-top: 2mm;
        }
        .invoice-tag {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 1.5mm 3mm;
            border-radius: 1.5mm;
            margin-bottom: 1.5mm;
        }
        .parties {
            display: table;
            width: 100%;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 3mm 0;
        }
        .parties > div {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .parties .party-name {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 1mm;
        }
        .parties .party-line {
            color: #475569;
            font-size: 8.5pt;
            line-height: 1.55;
        }
        .meta-block {
            margin: 6mm 0;
            display: table;
            width: 100%;
        }
        .meta-block > div {
            display: table-cell;
            vertical-align: top;
            padding-right: 10mm;
        }
        .meta-block .meta-label {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 1mm;
        }
        .meta-block .meta-value {
            font-size: 10pt;
            color: #0f172a;
            font-weight: bold;
        }
        .lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
            table-layout: fixed;
        }
        .lines col.col-vehicle { width: 28%; }
        .lines col.col-days { width: 12%; }
        .lines col.col-detail { width: 42%; }
        .lines col.col-total { width: 18%; }
        .lines thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 2mm 2.5mm;
            text-align: left;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .lines thead th.num {
            text-align: right;
        }
        .lines tbody td {
            padding: 2.2mm 2.5mm;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9pt;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .lines tbody td.num {
            text-align: right;
            white-space: nowrap;
        }
        .lines tbody td.label {
            color: #0f172a;
            font-weight: bold;
        }
        .lines tbody td.detail {
            color: #64748b;
            font-size: 8pt;
            line-height: 1.45;
        }
        .total-block {
            margin-top: 3mm;
            text-align: right;
        }
        .total-block .total-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            font-weight: bold;
        }
        .total-block .total-value {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1mm;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 5mm;
            margin-top: 6mm;
            color: #94a3b8;
            font-size: 7.5pt;
            line-height: 1.5;
        }
        .footer .footer-line + .footer-line { margin-top: 0.5mm; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-title">
            <span class="invoice-tag">Annexe de facture</span>
            <h1>{{ $periodLabel }}</h1>
            <div class="invoice-ref">{{ $invoiceNumber }}</div>
        </div>

        <div class="parties">
            <div>
                <h2>Émetteur</h2>
                <div class="party-name">{{ $issuer['name'] }}</div>
                <div class="party-line">
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
                        SIREN {{ $issuer['siren'] }}<br>
                    @endif
                    @if(!empty($issuer['contactEmail']))
                        {{ $issuer['contactEmail'] }}
                    @endif
                </div>
            </div>
            <div>
                <h2>Destinataire</h2>
                <div class="party-name">{{ $company['legalName'] }}</div>
                <div class="party-line">
                    @if(!empty($company['siren']))
                        SIREN {{ $company['siren'] }}<br>
                    @endif
                    @if(!empty($company['city']))
                        {{ $company['city'] }}
                    @endif
                </div>
            </div>
        </div>
    </header>

    <div class="meta-block">
        <div>
            <div class="meta-label">Numéro d'annexe</div>
            <div class="meta-value">{{ $invoiceNumber }}</div>
        </div>
        <div>
            <div class="meta-label">Période facturée</div>
            <div class="meta-value">{{ $periodLabel }}</div>
        </div>
        <div>
            <div class="meta-label">Date d'émission</div>
            <div class="meta-value">{{ $generatedAtLabel }}</div>
        </div>
    </div>

    <table class="lines">
        <colgroup>
            <col class="col-vehicle">
            <col class="col-days">
            <col class="col-detail">
            <col class="col-total">
        </colgroup>
        <thead>
            <tr>
                <th>Véhicule</th>
                <th class="num">Jours utilisés</th>
                <th>Décomposition tarifaire</th>
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
                        @if($line['hasDiscount'])
                            <br>
                            <span style="color:#0f766e;font-weight:bold;">
                                Réduction
                                @if(!empty($line['discountName']))
                                    « {{ $line['discountName'] }} »
                                @endif
                                {{ $line['discountPercentLabel'] }} · -{{ $line['discountLabel'] }}
                            </span>
                        @endif
                    </td>
                    <td class="num">
                        @if($line['hasDiscount'])
                            <span style="color:#64748b;text-decoration:line-through;font-size:8pt;">{{ $line['grossTotalLabel'] }}</span><br>
                        @endif
                        {{ $line['totalLabel'] }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-block">
        @if($hasDiscount)
            <div style="margin-bottom:1.5mm;">
                <span style="font-size:7.5pt;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:bold;">Total brut</span>
                <span style="font-size:10pt;color:#475569;margin-left:3mm;">{{ $grossTotalLabel }}</span>
            </div>
            <div style="margin-bottom:1.5mm;">
                <span style="font-size:7.5pt;text-transform:uppercase;letter-spacing:0.08em;color:#0f766e;font-weight:bold;">Réductions appliquées</span>
                <span style="font-size:10pt;color:#0f766e;margin-left:3mm;">-{{ $totalDiscountLabel }}</span>
            </div>
        @endif
        <div class="total-label">Total HT à régler</div>
        <div class="total-value">{{ $totalLabel }}</div>
    </div>

    <footer class="footer">
        <div class="footer-line">
            Document numérique non modifiable après émission.
        </div>
    </footer>
</body>
</html>
