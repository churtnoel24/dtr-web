@php
    $sheet = $reportRow ?? null;
@endphp

<section class="report-sheet">
    @if ($sheet === null)
        <div class="report-sheet__empty">
            <h3>DTR Template Ready</h3>
            <p>Fetch logs to populate the selected employee's DTR preview.</p>
        </div>
    @else
        <div class="report-sheet__topline">
            <span>Civil Service Form No. 48</span>
            <span>{{ $sheet['mac_office_display'] }}</span>
        </div>

        <div class="report-sheet__heading">
            <div class="report-sheet__brand">Republic of the Philippines</div>
            <div class="report-sheet__title">DAILY TIME RECORD</div>
        </div>

        <div class="report-sheet__identity">
            <div class="identity-row">
                <span class="identity-label">Name</span>
                <span class="identity-value">{{ $sheet['employee_name_inline'] }}</span>
            </div>
            <div class="identity-row">
                <span class="identity-label">Official Hours</span>
                <span class="identity-value">{{ $sheet['schedule_am'] }} / {{ $sheet['schedule_pm'] }}</span>
            </div>
            <div class="identity-row">
                <span class="identity-label">For The Month Of</span>
                <span class="identity-value">{{ $sheet['month_display'] }} {{ $sheet['year_display'] }}</span>
            </div>
            <div class="identity-row">
                <span class="identity-label">Position</span>
                <span class="identity-value">{{ $sheet['designation'] }}</span>
            </div>
            <div class="identity-row">
                <span class="identity-label">Employment Status</span>
                <span class="identity-value">{{ $sheet['job_status'] }}</span>
            </div>
        </div>

        <div class="report-sheet__tables {{ count($sheet['groups']) === 1 ? 'report-sheet__tables--single' : '' }}">
            @foreach ($sheet['groups'] as $group)
                <div class="report-table-card">
                    <div class="report-table-card__title">{{ $group['label'] }}</div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>AM In</th>
                                <th>AM Out</th>
                                <th>PM In</th>
                                <th>PM Out</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['days'] as $day)
                                <tr>
                                    <td>{{ $day['day_label'] }}</td>
                                    <td>{{ $day['am_in'] }}</td>
                                    <td>{{ $day['am_out'] }}</td>
                                    <td>{{ $day['pm_in'] }}</td>
                                    <td>{{ $day['pm_out'] }}</td>
                                    <td>{{ $day['remark'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

        <div class="report-sheet__footnote">
            I certify on my honor that the above is a true and correct report of the hours worked,
            recorded daily at the time of arrival and departure from office.
        </div>

        <div class="report-sheet__signature">
            <div class="signature-line"></div>
            <div class="signature-name">{{ $sheet['signatory_name'] }}</div>
            <div class="signature-designation">{{ $sheet['signatory_designation'] }}</div>
            <div class="signature-role">Immediate Supervisor / Grade Leader / Department Head</div>
        </div>
    @endif
</section>
