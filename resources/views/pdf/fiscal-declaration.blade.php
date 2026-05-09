<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Déclaration fiscale {{ $reference }}</title>
    <style>
        /*
         * Police DejaVu Sans embarquée par dompdf, UTF-8 native (rend
         * correctement é/è/à/É/€/etc. là où Helvetica les transforme en `?`).
         * CSS basé sur display: table car DomPDF ne supporte ni flexbox ni grid.
         */
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
            font-size: 22pt;
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
            margin-bottom: 10mm;
        }
        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 6mm;
        }
        .header-top > div {
            display: table-cell;
            vertical-align: top;
        }
        .header-top .title-cell { width: 60%; }
        .header-top .ref-cell {
            width: 40%;
            text-align: right;
        }
        .doc-tag {
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
        .doc-ref {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.02em;
            color: #475569;
            margin-top: 2mm;
        }
        .meta-block {
            margin: 6mm 0;
            display: table;
            width: 100%;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 5mm 0;
        }
        .meta-block > div {
            display: table-cell;
            vertical-align: top;
            width: 50%;
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
            font-size: 11pt;
            color: #0f172a;
            font-weight: bold;
        }
        .meta-block .meta-secondary {
            font-size: 9pt;
            color: #475569;
            margin-top: 1mm;
        }
        section {
            margin-bottom: 8mm;
            page-break-inside: avoid;
        }
        section h2 {
            margin-bottom: 3mm;
        }
        table.summary, table.lines {
            width: 100%;
            border-collapse: collapse;
        }
        table.summary td {
            padding: 2mm 3mm;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10pt;
        }
        table.summary td.label {
            color: #475569;
            width: 70%;
        }
        table.summary td.amount {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
        }
        table.summary tr.total td {
            border-top: 2px solid #0f172a;
            border-bottom: none;
            font-size: 11pt;
            padding-top: 3mm;
        }
        table.summary tr.total td.amount {
            font-size: 13pt;
            color: #0f172a;
        }
        table.lines th {
            background: #f8fafc;
            color: #475569;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 2mm 3mm;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
        }
        table.lines td {
            padding: 2mm 3mm;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9pt;
            color: #0f172a;
            vertical-align: top;
        }
        table.lines td.numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        table.lines tr:last-child td {
            border-bottom: none;
        }
        .empty {
            font-style: italic;
            color: #94a3b8;
            padding: 3mm 0;
        }
        .pill {
            display: inline-block;
            padding: 0.5mm 2mm;
            border-radius: 1mm;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .pill-requalified { background: #fee2e2; color: #991b1b; }
        .pill-conserved { background: #dcfce7; color: #166534; }
        .legal {
            margin-top: 8mm;
            padding-top: 4mm;
            border-top: 1px solid #e2e8f0;
            font-size: 8pt;
            color: #64748b;
            line-height: 1.6;
        }
        .legal strong { color: #475569; }
        .seal {
            margin-top: 4mm;
            padding: 3mm 4mm;
            background: #f8fafc;
            border-left: 3px solid #475569;
            font-size: 8.5pt;
            color: #475569;
        }
        .seal strong {
            color: #0f172a;
            font-size: 9pt;
        }
        .mono {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8.5pt;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-top">
            <div class="title-cell">
                <span class="doc-tag">Annexe documentaire</span>
                <h1>Déclaration fiscale</h1>
            </div>
            <div class="ref-cell">
                <h2>Référence</h2>
                <div class="doc-ref">{{ $reference }}</div>
            </div>
        </div>

        <div class="meta-block">
            <div>
                <div class="meta-label">Entreprise utilisatrice</div>
                <div class="meta-value">{{ $companyLegalName }}</div>
                <div class="meta-secondary">Code court : {{ $companyShortCode }}</div>
            </div>
            <div>
                <div class="meta-label">Exercice fiscal</div>
                <div class="meta-value">{{ $fiscalYear }}</div>
                <div class="meta-secondary">Générée le {{ $generatedAtLabel }}</div>
            </div>
        </div>
    </header>

    <section>
        <h2>Synthèse fiscale</h2>
        <table class="summary">
            <tr>
                <td class="label">Taxe CO₂ (CIBS L. 421-29)</td>
                <td class="amount">{{ $co2DueTotal }}</td>
            </tr>
            <tr>
                <td class="label">Taxe sur les polluants atmosphériques (CIBS L. 421-58)</td>
                <td class="amount">{{ $pollutantsDueTotal }}</td>
            </tr>
            <tr class="total">
                <td class="label">Total dû</td>
                <td class="amount">{{ $totalDue }}</td>
            </tr>
        </table>
    </section>

    <section>
        <h2>Détail par véhicule</h2>
        @if (count($vehicleRows) === 0)
            <p class="empty">Aucun véhicule attribué sur cet exercice.</p>
        @else
            <table class="lines">
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th class="numeric">Jours</th>
                        <th class="numeric">CO₂</th>
                        <th class="numeric">Polluants</th>
                        <th class="numeric">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicleRows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="numeric">{{ $row['daysAssigned'] }}</td>
                            <td class="numeric">{{ $row['co2Due'] }}</td>
                            <td class="numeric">{{ $row['pollutantsDue'] }}</td>
                            <td class="numeric">{{ $row['totalDue'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <h2>Décisions de revue appliquées</h2>
        @if (count($decisionRows) === 0)
            <p class="empty">Aucune chaîne LCD à risque détectée sur cet exercice.</p>
        @else
            <table class="lines">
                <thead>
                    <tr>
                        <th>Cluster</th>
                        <th>Risque</th>
                        <th>Décision</th>
                        <th class="numeric">Contrats</th>
                        <th>Justification</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($decisionRows as $row)
                        <tr>
                            <td class="mono">{{ $row['fingerprintShort'] }}…</td>
                            <td>{{ $row['riskCodeLabel'] }}</td>
                            <td>
                                @if ($row['decisionLabel'] === 'requalified')
                                    <span class="pill pill-requalified">Requalifié</span>
                                @else
                                    <span class="pill pill-conserved">Conservé</span>
                                @endif
                            </td>
                            <td class="numeric">{{ $row['contractsCount'] }}</td>
                            <td>{{ $row['justification'] ?? '·' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($optOutContractsCount > 0)
                <p style="margin-top: 3mm; font-size: 8.5pt; color: #475569;">
                    {{ $optOutContractsCount }} contrat(s) requalifié(s) ont été retirés de l'exonération
                    R-2024-021 (locations courtes durées).
                </p>
            @endif
        @endif
    </section>

    <div class="legal">
        <strong>Mentions légales.</strong>
        Le présent document est une <strong>annexe documentaire interne</strong> à valeur
        d'audit. Il ne se substitue pas à la déclaration officielle de la
        Contribution sur la mise en service de véhicules (CIBS) à effectuer sur
        impots.gouv.fr conformément aux articles CIBS L. 421-29 (taxe CO₂) et
        CIBS L. 421-58 (taxe polluants atmosphériques). Les montants exposés
        ci-dessus sont calculés au prorata des jours d'attribution réelle par
        entreprise utilisatrice (R-2024-002) avec un arrondi half-up unique en
        sortie (R-2024-003). Les décisions de revue « Requalifié » retirent
        l'exonération LCD aux contrats concernés (CIBS L. 421-141, BOFiP § 180-190).
    </div>

    <div class="seal">
        <strong>Sceau de génération.</strong>
        Document {{ $reference }} produit le {{ $generatedAtLabel }}.
    </div>
</body>
</html>
