<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR PREVIEW</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body class="dtr-page">
@php
    $dashboardQuery = array_filter([
        'fetch' => 1,
        'year' => $filters['year'],
        'month' => $filters['month'],
        'office' => $filters['office'],
        'mac_id' => $filters['mac_id'],
        'status' => $filters['status'],
        'global' => $filters['global'] ? 1 : null,
        'selected_mac_id' => $dashboard['selected_mac_id'] ?? '',
    ], fn ($value) => $value !== null && $value !== '');

    $reportQuery = array_merge($dashboardQuery, [
        'scope' => 'selected',
        'selected_mac_id' => $dashboard['selected_mac_id'] ?? '',
    ]);

    $reportPreviewQuery = array_merge($reportQuery, [
        'embed' => 1,
    ]);

    $selectedReportReady = $dashboard['selected_row'] !== null;
    $monthLabel = $months[$filters['month']] ?? $filters['month'];
@endphp

<div class="app-shell">
    @if (session('status'))
        <div class="flash flash--success">{{ session('status') }}</div>
    @endif

    @if (session('error') || $errorMessage)
        <div class="flash flash--error">{{ session('error') ?? "Error :P" }}</div>
    @endif

    <section class="panel panel--filters">
        <div class="panel__heading">
            <div>
                <p class="panel__eyebrow">Preview</p>
                <h2>Build DTR Preview</h2>
            </div>
            <div class="panel__meta">
                <span>{{ $dashboard['dtr_logs_count_text'] }}</span>
                <span>{{ $monthLabel }} {{ $filters['year'] }}</span>
            </div>
        </div>

        <form method="get" action="{{ route('dtr.dashboard') }}" class="filters-form">
            <input type="hidden" name="fetch" value="1">

            <label>
                <span>Year</span>
                <select name="year">
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected($filters['year'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Month</span>
                <select name="month">
                    @foreach ($months as $monthNumber => $monthName)
                        <option value="{{ $monthNumber }}" @selected($filters['month'] === $monthNumber)>{{ $monthName }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Office</span>
                <select name="office">
                    <option value="">Select office</option>
                    @foreach ($dashboard['offices'] as $office)
                        <option value="{{ $office['office_os_id'] }}" @selected((string) $filters['office'] === (string) $office['office_os_id'])>
                            {{ $office['office_os_id'] }} - {{ $office['office_name'] }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Mac ID</span>
                <input type="text" name="mac_id" value="{{ $filters['mac_id'] }}" placeholder="e.g. 11">
            </label>

            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach ($dashboard['statuses'] as $status)
                        <option value="{{ $status['name'] }}" @selected($filters['status'] === $status['name'])>{{ $status['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="toggle">
                <input type="checkbox" name="global" value="1" @checked($filters['global'])>
                <span>Global logs</span>
            </label>

            <div class="filters-actions">
                <button type="submit" class="button button--primary">GET LOGS</button>
                <a href="{{ route('dtr.dashboard') }}" class="button button--ghost">CLEAR GRID</a>
            </div>
        </form>
    </section>

    <div class="workspace-stack">
        <section class="panel panel--report">
            <div class="panel__heading">
                <div>
                    <p class="panel__eyebrow">Daily Time Record</p>
                    <h2>Monthly Preview</h2>
                </div>
                <div class="panel__meta">
                    <span>{{ $dashboard['dtr_logs_count_text'] }}</span>
                    <span>Whole Month</span>
                </div>
            </div>

            <form method="get" action="{{ route('dtr.dashboard') }}" class="report-controls">
                <input type="hidden" name="fetch" value="1">
                <input type="hidden" name="year" value="{{ $filters['year'] }}">
                <input type="hidden" name="month" value="{{ $filters['month'] }}">
                <input type="hidden" name="office" value="{{ $filters['office'] }}">
                <input type="hidden" name="mac_id" value="{{ $filters['mac_id'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @if ($filters['global'])
                    <input type="hidden" name="global" value="1">
                @endif

                <label>
                    <span>Preview Employee</span>
                    <select name="selected_mac_id" onchange="this.form.submit()">
                        <option value="">Auto-select first merged row</option>
                        @foreach ($dashboard['merged_rows'] as $row)
                            <option value="{{ $row['b_mac_id'] }}" @selected((string) ($dashboard['selected_mac_id'] ?? '') === (string) $row['b_mac_id'])>
                                {{ $row['b_lastname'] }}, {{ $row['b_firstname'] }} ({{ $row['b_mac_id'] }})
                            </option>
                        @endforeach
                    </select>
                </label>
            </form>

            <div class="report-preview-caption">{{ $dashboard['status_message'] }}</div>

            @if ($selectedReportReady)
                <div class="report-frame">
                    <iframe
                        class="report-frame__browser"
                        src="{{ route('dtr.report', $reportPreviewQuery) }}"
                        title="Laravel DTR Preview"></iframe>
                </div>
            @else
                <div class="report-sheet__empty">
                    <div>
                        <h3>DTR Template Ready</h3>
                        <p>Fetch logs to populate the monthly Laravel DTR preview from `SDODTR2026`.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
</body>
</html>
