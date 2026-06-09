<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Planning {{ $year }} · véhicules</title>
    <style>
        @page { margin: 16mm 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            font-size: 9.5pt;
            line-height: 1.5;
            margin: 0;
        }
        h1 { font-size: 17pt; font-weight: bold; margin: 0; letter-spacing: -0.02em; }
        .doc-header {
            display: table;
            width: 100%;
            margin-bottom: 6mm;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3mm;
        }
        .doc-header-main { display: table-cell; vertical-align: bottom; width: 60%; }
        .doc-header-meta { display: table-cell; vertical-align: bottom; width: 40%; text-align: right; }
        .doc-subtitle {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin: 1mm 0 0;
        }
        .doc-scope { font-size: 10.5pt; font-weight: bold; margin: 0; }
        .doc-generated { font-size: 7.5pt; color: #94a3b8; margin: 0.5mm 0 0; }

        .vehicle-card {
            border: 1px solid #e2e8f0;
            border-radius: 2mm;
            padding: 4mm 5mm 4.5mm;
            margin-bottom: 5mm;
            page-break-inside: avoid;
        }
        .card-title { font-size: 12.5pt; font-weight: bold; margin: 0; }
        .card-sub { font-size: 8.5pt; color: #64748b; margin: 0.5mm 0 0; }
        .section-label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin: 4mm 0 1.5mm;
        }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 1mm 0; font-size: 9pt; vertical-align: top; width: 50%; }
        table.kv .k { color: #64748b; font-size: 8pt; }
        table.kv .v { font-weight: bold; }
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
                <p class="card-title">{{ $row['licensePlate'] }}</p>
                <p class="card-sub">{{ $row['vehicleLabel'] }} · {{ $row['userType'] }}</p>

                <p class="section-label">Caractéristiques</p>
                <table class="kv">
                    <tr>
                        <td><span class="k">Énergie</span><br><span class="v">{{ $row['energy'] }}</span></td>
                        <td><span class="k">Catégorie polluants</span><br><span class="v">{{ $row['pollutantCategory'] }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="k">CO₂ ({{ $row['co2Method'] }})</span><br><span class="v">{{ $row['co2Value'] !== null ? $row['co2Value'].' g/km' : 'n.c.' }}</span></td>
                        <td><span class="k">Puissance fiscale</span><br><span class="v">{{ $row['taxableHorsepower'] !== null ? $row['taxableHorsepower'].' CV' : 'n.c.' }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="k">1re immatriculation</span><br><span class="v">{{ $row['firstRegistration'] }}</span></td>
                        <td></td>
                    </tr>
                </table>

                <p class="section-label">Montants {{ $year }}</p>
                <table class="kv">
                    <tr>
                        <td><span class="k">Taxe pleine annuelle</span><br><span class="v">{{ $row['fullYearTax'] }}</span></td>
                        <td><span class="k">Taxe réelle annuelle</span><br><span class="v">{{ $row['annualTaxDue'] }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="k">Total jours d'utilisation</span><br><span class="v">{{ $row['daysTotal'] }}</span></td>
                        <td><span class="k">Tarifs location (J / S / M)</span><br><span class="v">{{ $row['dailyRate'] ?? '-' }} / {{ $row['weeklyRate'] ?? '-' }} / {{ $row['monthlyRate'] ?? '-' }}</span></td>
                    </tr>
                </table>
            </div>
        @endforeach
    @endif
</body>
</html>
