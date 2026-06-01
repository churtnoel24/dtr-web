<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Report</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17304d;
            --line: #b7c3d1;
            --paper: #ffffff;
            --page: #eef3f7;
            --glow: rgba(34, 89, 135, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            overflow-x: hidden;
            background:
                radial-gradient(circle at top, rgba(154, 60, 35, 0.09), transparent 26%),
                linear-gradient(180deg, #f7faf6 0%, var(--page) 100%);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body.report-body--embedded {
            background:
                radial-gradient(circle at top, rgba(154, 60, 35, 0.12), transparent 30%),
                linear-gradient(180deg, #f4f8f4 0%, #eaf1f6 100%);
        }

        .report-document {
            width: min(100%, 980px);
            margin: 0 auto;
            padding: clamp(12px, 2vw, 24px);
        }

        body.report-body--embedded .report-document {
            width: min(100%, 930px);
            padding: clamp(12px, 2vw, 18px);
        }

        .report-document__page {
            width: min(210mm, 100%);
            min-height: 297mm;
            margin: 0 auto 24px;
            padding: clamp(18px, 3vw, 14mm);
            background: var(--paper);
            box-shadow: 0 18px 40px rgba(18, 34, 52, 0.12);
            page-break-after: always;
        }

        body.report-body--embedded .report-document__page {
            border-radius: 28px;
            box-shadow:
                0 22px 54px rgba(18, 34, 52, 0.14),
                0 0 0 1px rgba(23, 48, 77, 0.06),
                0 0 0 12px var(--glow);
        }

        .report-document__page:last-child {
            page-break-after: auto;
        }

        .report-sheet {
            width: 100%;
        }

        .report-sheet__empty {
            display: grid;
            place-items: center;
            min-height: 220px;
            border: 1px dashed var(--line);
            text-align: center;
        }

        .report-sheet__topline,
        .report-sheet__heading,
        .report-sheet__identity,
        .report-sheet__footnote,
        .report-sheet__signature {
            width: 100%;
        }

        .report-sheet__topline {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .report-sheet__heading {
            text-align: center;
            margin-bottom: 14px;
        }

        .report-sheet__brand {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
        }

        .report-sheet__title {
            font-size: 20px;
            font-weight: 700;
            margin-top: 4px;
        }

        .report-sheet__identity {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
        }

        .identity-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 12px;
            align-items: end;
            border-bottom: 1px solid var(--line);
            padding-bottom: 4px;
        }

        .identity-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .identity-value {
            font-size: 13px;
            font-weight: 600;
        }

        .report-sheet__tables {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .report-sheet__tables--single {
            grid-template-columns: minmax(0, 1fr);
        }

        .report-table-card__title {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid var(--line);
            padding: 4px 5px;
            text-align: center;
        }

        .report-sheet__footnote {
            margin-top: 20px;
            font-size: 11px;
            line-height: 1.5;
        }

        .report-sheet__signature {
            margin-top: 22px;
            text-align: center;
        }

        .signature-line {
            width: 320px;
            max-width: 100%;
            margin: 0 auto 8px;
            border-top: 1px solid var(--ink);
        }

        .signature-name,
        .signature-designation,
        .signature-role {
            font-size: 11px;
        }

        .signature-name {
            font-weight: 700;
        }

        @media (max-width: 860px) {
            .report-sheet__tables,
            .report-sheet__tables--single {
                grid-template-columns: minmax(0, 1fr);
            }

            .identity-row {
                grid-template-columns: 110px 1fr;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .report-document {
                width: auto;
                padding: 0;
            }

            .report-document__page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body class="report-body{{ !empty($embedded) ? ' report-body--embedded' : '' }}">
    <div class="report-document">
        @foreach ($document['report_rows'] as $reportRow)
            <div class="report-document__page">
                @include('dtr.partials.report-sheet', ['reportRow' => $reportRow])
            </div>
        @endforeach
    </div>

    @if (!empty($autoPrint))
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
