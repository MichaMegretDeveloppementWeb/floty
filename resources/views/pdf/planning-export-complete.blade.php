<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Planning {{ $year }}</title>
    <style>
        @page { margin: 10mm 8mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            font-size: 8pt;
            line-height: 1.4;
            margin: 0;
        }
        h1 { font-size: 15pt; font-weight: bold; margin: 0; letter-spacing: -0.02em; }
        .doc-header {
            display: table;
            width: 100%;
            margin-bottom: 4mm;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2.5mm;
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
        .doc-scope { font-size: 10pt; font-weight: bold; margin: 0; }
        .doc-generated { font-size: 7.5pt; color: #94a3b8; margin: 0.5mm 0 0; }

        /* Auto layout · DomPDF ignores fixed-layout column widths with this
           many columns, so we let non-collapsing content drive the widths:
           the identity / totals keep their nowrap content width, and each
           week cell carries a fixed-width inline-block so the 53 week
           columns stay uniform. */
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid thead { display: table-header-group; }
        table.grid tr { page-break-inside: avoid; }
        table.grid th, table.grid td {
            border: 0.4pt solid #e2e8f0;
            padding: 0.7mm 0.3mm;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }
        table.grid th { background: #f8fafc; font-weight: bold; color: #475569; }
        table.grid th.week, table.grid td.week { font-size: 5pt; }
        table.grid .wk { display: inline-block; width: 2.6mm; text-align: center; }
        table.grid th.identity, table.grid td.identity {
            text-align: left;
            padding-left: 1.6mm;
            padding-right: 1.6mm;
            white-space: nowrap;
        }
        table.grid th.num, table.grid td.num {
            white-space: nowrap;
            padding-left: 1.6mm;
            padding-right: 1.6mm;
            font-size: 6.5pt;
        }
        table.grid th.identity, table.grid th.num {
            font-size: 5.5pt;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        td.identity .plate { display: block; font-weight: bold; font-size: 7.5pt; white-space: nowrap; }
        td.identity .vlabel { display: block; font-size: 6pt; color: #64748b; white-space: nowrap; }
        td.week.empty { color: #cbd5e1; }
        .empty-state { font-size: 10pt; color: #64748b; margin-top: 6mm; }
    </style>
</head>
<body>
    @include('pdf.partials.planning-export-header')

    @if (count($rows) === 0)
        <p class="empty-state">Aucun véhicule à exporter.</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th class="identity">Véhicule</th>
                    @for ($w = 1; $w <= 53; $w++)
                        <th class="week"><span class="wk">{{ $w }}</span></th>
                    @endfor
                    <th class="num">Jours</th>
                    <th class="num">Taxe pleine</th>
                    <th class="num">Taxe réelle</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="identity">
                            <span class="plate">{{ $row['licensePlate'] }}</span>
                            <span class="vlabel">{{ $row['vehicleLabelShort'] }} · {{ $row['userTypeShort'] }}</span>
                        </td>
                        @foreach ($row['weeks'] as $days)
                            <td class="week {{ $days > 0 ? '' : 'empty' }}"><span class="wk">{{ $days > 0 ? $days : '' }}</span></td>
                        @endforeach
                        <td class="num">{{ $row['daysTotal'] }}</td>
                        <td class="num">{{ $row['fullYearTax'] }}</td>
                        <td class="num">{{ $row['annualTaxDue'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
