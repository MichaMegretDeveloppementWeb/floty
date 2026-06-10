<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Planning {{ $year }} · véhicules</title>
    <style>
        @page { margin: 12mm 13mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            font-size: 8pt;
            line-height: 1.25;
            margin: 0;
        }
        h1 { font-size: 16pt; font-weight: bold; margin: 0; letter-spacing: -0.02em; }
        .doc-header {
            display: table;
            width: 100%;
            margin-bottom: 5mm;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3mm;
        }
        .doc-header-main { display: table-cell; vertical-align: top; width: 62%; }
        .doc-header-meta { display: table-cell; vertical-align: top; width: 38%; text-align: right; }
        .doc-subtitle { font-size: 9pt; color: #64748b; margin: 1mm 0 0; }
        .doc-meta-line { font-size: 8.5pt; color: #475569; margin: 0 0 0.5mm; }
        .doc-meta-strong { font-weight: bold; color: #0f172a; }
        .doc-generated { font-size: 7.5pt; color: #94a3b8; margin-top: 1mm; }

        .vehicle-card {
            border: 0.75pt solid #e2e8f0;
            border-radius: 1.5mm;
            padding: 1.3mm 2.2mm 1.4mm;
            margin-bottom: 1.4mm;
            page-break-inside: avoid;
        }
        .card-head { margin: 0 0 0.8mm; }
        .card-head .plate { font-size: 9.5pt; font-weight: bold; color: #0f172a; }
        .card-head .vlabel { font-size: 7.5pt; color: #64748b; }
        table.kv { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.kv td {
            width: 33.33%;
            padding: 0.25mm 3mm 0.25mm 0;
            vertical-align: top;
        }
        table.kv .k {
            display: block;
            font-size: 5.5pt;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #94a3b8;
            margin-bottom: 0.1mm;
        }
        table.kv .v { display: block; font-size: 8pt; font-weight: bold; color: #0f172a; }
        .card-exit {
            margin: 0 0 1.2mm;
            font-size: 7.5pt;
            font-weight: bold;
            color: #be123c;
        }
        .card-exemptions {
            margin: 1.2mm 0 0;
            padding-top: 1.2mm;
            border-top: 0.5pt dotted #cbd5e1;
            font-size: 7.5pt;
            color: #334155;
        }
        .card-exemptions .k-inline {
            font-size: 6pt;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: bold;
            color: #0f766e;
        }
        .empty-state { font-size: 10pt; color: #64748b; margin-top: 6mm; }
    </style>
</head>
<body>
    @include('pdf.partials.planning-export-header')

    @if (count($rows) === 0)
        <p class="empty-state">Aucun véhicule à exporter.</p>
    @else
        @foreach ($rows as $row)
            <div class="vehicle-card">
                <p class="card-head">
                    <span class="plate">{{ $row['licensePlate'] }}</span>
                    <span class="vlabel">· {{ $row['vehicleLabel'] }} · {{ $row['userType'] }}</span>
                </p>
                @if ($row['exitDate'] !== null)
                    <p class="card-exit">Sorti de la flotte le {{ $row['exitDate'] }}@if ($row['exitReason'] !== null) · {{ $row['exitReason'] }}@endif</p>
                @endif
                <table class="kv">
                    <tr>
                        <td><span class="k">Énergie</span><span class="v">{{ $row['energy'] }}</span></td>
                        <td><span class="k">CO₂ ({{ $row['co2Method'] }})</span><span class="v">{{ $row['co2Value'] !== null ? $row['co2Value'].' g/km' : 'n.c.' }}</span></td>
                        <td><span class="k">Puissance fiscale</span><span class="v">{{ $row['taxableHorsepower'] !== null ? $row['taxableHorsepower'].' CV' : 'n.c.' }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="k">Catégorie polluants</span><span class="v">{{ $row['pollutantCategory'] }}</span></td>
                        <td><span class="k">1re immatriculation</span><span class="v">{{ $row['firstRegistration'] }}</span></td>
                        <td><span class="k">Total jours d'utilisation</span><span class="v">{{ $row['daysTotal'] }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="k">Taxe pleine annuelle</span><span class="v">{{ $row['fullYearTax'] }}</span></td>
                        <td><span class="k">Taxe réelle annuelle</span><span class="v">{{ $row['annualTaxDue'] }}</span></td>
                        <td><span class="k">Tarifs location (J / S / M)</span><span class="v">{{ $row['dailyRate'] ?? '-' }} / {{ $row['weeklyRate'] ?? '-' }} / {{ $row['monthlyRate'] ?? '-' }}</span></td>
                    </tr>
                </table>
                @if (count($row['exemptions']) > 0)
                    <p class="card-exemptions"><span class="k-inline">Exonérations</span> {{ implode(' · ', $row['exemptions']) }}</p>
                @endif
            </div>
        @endforeach
    @endif
</body>
</html>
