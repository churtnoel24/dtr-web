<?php

namespace App\Support;

use DateTimeImmutable;

class DtrDataTransformer
{
    private const SUPPORTED_LOG_DATE_FORMATS = [
        'Y-m-d H:i:s',
        'Y-n-j H:i:s',
        'Y/m/d H:i:s',
        'n/j/Y G:i:s',
        'm/d/Y H:i:s',
        'n/j/Y g:i:s A',
        'm/d/Y h:i:s A',
    ];

    public function resolveGlobalMacIds(
        array $bios,
        ?string $officeId,
        ?string $requestedMacId,
        ?string $requestedStatus,
    ): array {
        $officeId = trim((string) $officeId);
        $requestedMacId = trim((string) $requestedMacId);
        $requestedStatus = trim((string) $requestedStatus);

        return collect($bios)
            ->filter(fn (array $bio) => (bool) ($bio['b_is_global'] ?? false))
            ->filter(function (array $bio) use ($officeId) {
                if ($officeId === '') {
                    return true;
                }

                return (string) ($bio['b_office_id'] ?? '') === $officeId;
            })
            ->filter(function (array $bio) use ($requestedStatus) {
                if ($requestedStatus === '') {
                    return true;
                }

                return strcasecmp((string) ($bio['b_job_status'] ?? ''), $requestedStatus) === 0;
            })
            ->filter(function (array $bio) use ($requestedMacId) {
                if ($requestedMacId === '') {
                    return true;
                }

                return (string) ($bio['b_mac_id'] ?? '') === $requestedMacId;
            })
            ->pluck('b_mac_id')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function normalizeLogs(array $logs): array
    {
        return collect($logs)
            ->unique(function (array $log) {
                $id = (int) ($log['id'] ?? 0);
                if ($id > 0) {
                    return 'id:' . $id;
                }

                return implode('|', [
                    trim((string) ($log['mac_id'] ?? '')),
                    trim((string) ($log['datetime_raw'] ?? '')),
                    trim((string) ($log['log_type'] ?? '')),
                    trim((string) ($log['office_id'] ?? '')),
                    trim((string) ($log['device_id'] ?? '')),
                ]);
            })
            ->sort(function (array $left, array $right) {
                $leftDate = $this->parseLogDateTime($left['datetime_raw'] ?? null)?->getTimestamp() ?? 0;
                $rightDate = $this->parseLogDateTime($right['datetime_raw'] ?? null)?->getTimestamp() ?? 0;

                return $rightDate <=> $leftDate;
            })
            ->values()
            ->all();
    }

    public function filterBios(array $bios, ?string $status, ?string $officeId, bool $globalMode): array
    {
        $status = trim((string) $status);
        $officeId = trim((string) $officeId);

        return collect($bios)
            ->filter(function (array $bio) use ($status) {
                if ($status === '') {
                    return true;
                }

                return strcasecmp((string) ($bio['b_job_status'] ?? ''), $status) === 0;
            })
            ->filter(function (array $bio) use ($officeId) {
                if ($officeId === '') {
                    return true;
                }

                return (string) ($bio['b_office_id'] ?? '') === $officeId;
            })
            ->filter(fn (array $bio) => !$globalMode || (bool) ($bio['b_is_global'] ?? false))
            ->values()
            ->all();
    }

    public function buildMonthlyRows(array $logs, int $year, int $month): array
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $rows = [];

        foreach ($logs as $log) {
            $logDateTime = $this->parseLogDateTime($log['datetime_raw'] ?? null);
            if ($logDateTime === null) {
                continue;
            }

            if ((int) $logDateTime->format('Y') !== $year || (int) $logDateTime->format('n') !== $month) {
                continue;
            }

            $macId = trim((string) ($log['mac_id'] ?? ''));
            if ($macId === '') {
                continue;
            }

            if (!isset($rows[$macId])) {
                $rows[$macId] = $this->createMonthlyRowTemplate($year, $month, $daysInMonth);
                $rows[$macId]['m_id'] = $macId;
                $rows[$macId]['m_office_id'] = trim((string) ($log['office_id'] ?? ''));
            } elseif ($rows[$macId]['m_office_id'] === '') {
                $rows[$macId]['m_office_id'] = trim((string) ($log['office_id'] ?? ''));
            }

            $this->applyLogToMonthlyRow($rows[$macId], $log, $logDateTime);
        }

        uasort($rows, function (array $left, array $right) {
            $leftMac = trim((string) ($left['m_id'] ?? ''));
            $rightMac = trim((string) ($right['m_id'] ?? ''));
            $leftNumeric = ctype_digit($leftMac);
            $rightNumeric = ctype_digit($rightMac);

            if ($leftNumeric && $rightNumeric) {
                return (int) $leftMac <=> (int) $rightMac;
            }

            if ($leftNumeric !== $rightNumeric) {
                return $leftNumeric ? -1 : 1;
            }

            return strcasecmp($leftMac, $rightMac);
        });

        return array_values($rows);
    }

    public function mergeBiosAndMonthlyRows(
        array $bios,
        array $monthlyRows,
        bool $globalMode,
        array $signatoryNameById,
    ): array {
        $biosByMacId = collect($bios)
            ->keyBy(fn (array $bio) => (int) ($bio['b_mac_id'] ?? 0))
            ->all();

        $mergedRows = [];

        foreach ($monthlyRows as $monthlyRow) {
            $macId = (int) ($monthlyRow['m_id'] ?? 0);
            if ($macId <= 0 || !isset($biosByMacId[$macId])) {
                continue;
            }

            $bio = $biosByMacId[$macId];

            if ($globalMode && !($bio['b_is_global'] ?? false)) {
                continue;
            }

            if (!$globalMode && ($bio['b_is_global'] ?? false)) {
                continue;
            }

            $signatoryRaw = trim((string) ($bio['b_signatories'] ?? ''));
            $signatoryName = $signatoryRaw;
            if (ctype_digit($signatoryRaw) && isset($signatoryNameById[(int) $signatoryRaw])) {
                $signatoryName = $signatoryNameById[(int) $signatoryRaw];
            }

            $mergedRows[] = array_merge($bio, $monthlyRow, [
                'b_signatory_name' => $signatoryName,
            ]);
        }

        return $mergedRows;
    }

    public function pickSelectedMergedRow(array $mergedRows, ?string $selectedMacId): ?array
    {
        $selectedMacId = trim((string) $selectedMacId);
        if ($selectedMacId !== '') {
            foreach ($mergedRows as $row) {
                if ((string) ($row['b_mac_id'] ?? '') === $selectedMacId) {
                    return $row;
                }
            }
        }

        return $mergedRows[0] ?? null;
    }

    public function buildReportRows(array $mergedRows, array $designationLookupByName): array
    {
        return collect($mergedRows)
            ->map(fn (array $row) => $this->buildReportRow($row, $designationLookupByName))
            ->values()
            ->all();
    }

    public function buildUploadPayload(array $monthlyRows): array
    {
        return collect($monthlyRows)
            ->map(function (array $row) {
                $payload = [
                    'mOfficeId' => (int) ($row['m_office_id'] ?? 0),
                    'mId' => (int) ($row['m_id'] ?? 0),
                    'mYear' => trim((string) ($row['m_year'] ?? '')),
                    'mMonth' => trim((string) ($row['m_month'] ?? '')),
                ];

                for ($day = 1; $day <= 31; $day++) {
                    $payload['mDay' . $day] = trim((string) ($row['m_day' . $day] ?? ''));
                    $payload['mLoginAm' . $day] = trim((string) ($row['m_login_am' . $day] ?? ''));
                    $payload['mLogoutAm' . $day] = trim((string) ($row['m_logout_am' . $day] ?? ''));
                    $payload['mLoginPm' . $day] = trim((string) ($row['m_login_pm' . $day] ?? ''));
                    $payload['mLogoutPm' . $day] = trim((string) ($row['m_logout_pm' . $day] ?? ''));
                    $payload['mRemarks' . $day] = trim((string) ($row['remarks_display' . $day] ?? ''));
                }

                return $payload;
            })
            ->filter(fn (array $row) => $row['mOfficeId'] > 0 && $row['mId'] > 0)
            ->values()
            ->all();
    }

    private function buildReportRow(array $row, array $designationLookupByName): array
    {
        $days = [];
        for ($day = 1; $day <= 31; $day++) {
            $dayLabel = trim((string) ($row['m_day' . $day] ?? ''));
            $isValid = $dayLabel !== '';

            $days[] = [
                'day_number' => $day,
                'day_label' => $dayLabel,
                'am_in' => $this->normalizeLogTime($row['m_login_am' . $day] ?? '', $isValid),
                'am_out' => $this->normalizeLogTime($row['m_logout_am' . $day] ?? '', $isValid),
                'pm_in' => $this->normalizeLogTime($row['m_login_pm' . $day] ?? '', $isValid),
                'pm_out' => $this->normalizeLogTime($row['m_logout_pm' . $day] ?? '', $isValid),
                'remark' => $this->resolveRemark(
                    (string) ($row['m_year'] ?? ''),
                    (string) ($row['m_month'] ?? ''),
                    $dayLabel
                ),
                'is_valid' => $isValid,
            ];
        }

        $groups = [
            [
                'label' => 'Days 1-15',
                'days' => array_values(array_filter(
                    $days,
                    fn (array $day) => $day['is_valid'] && $day['day_number'] <= 15
                )),
            ],
            [
                'label' => 'Days 16-31',
                'days' => array_values(array_filter(
                    $days,
                    fn (array $day) => $day['is_valid'] && $day['day_number'] >= 16
                )),
            ],
        ];

        $signatoryName = trim((string) ($row['b_signatory_name'] ?? ''));
        $signatoryDesignation = $signatoryName !== '' && isset($designationLookupByName[$signatoryName])
            ? $designationLookupByName[$signatoryName]
            : '';

        return [
            'mac_office_display' => trim((string) ($row['b_office_id'] ?? '')) . ' - ' . trim((string) ($row['b_mac_id'] ?? '')),
            'employee_display' => trim((string) ($row['b_lastname'] ?? '')) . ', ' . trim((string) ($row['b_firstname'] ?? '')),
            'employee_name_inline' => trim((string) ($row['b_firstname'] ?? '')) . ' ' . trim((string) ($row['b_lastname'] ?? '')),
            'designation' => trim((string) ($row['b_designation'] ?? '')),
            'job_status' => trim((string) ($row['b_job_status'] ?? '')),
            'month_display' => $this->formatMonthDisplay((string) ($row['m_month'] ?? '')),
            'year_display' => trim((string) ($row['m_year'] ?? '')),
            'signatory_name' => $signatoryName,
            'signatory_designation' => $signatoryDesignation,
            'schedule_am' => $this->formatScheduleRange($row['b_hrs_am_in'] ?? '', $row['b_hrs_am_out'] ?? ''),
            'schedule_pm' => $this->formatScheduleRange($row['b_hrs_pm_in'] ?? '', $row['b_hrs_pm_out'] ?? ''),
            'groups' => $groups,
        ];
    }

    private function createMonthlyRowTemplate(int $year, int $month, int $daysInMonth): array
    {
        $row = [
            'm_office_id' => '',
            'm_id' => '',
            'm_year' => (string) $year,
            'm_month' => (string) $month,
        ];

        for ($day = 1; $day <= 31; $day++) {
            $row['m_day' . $day] = $day <= $daysInMonth ? (string) $day : '';
            $row['m_login_am' . $day] = '';
            $row['m_logout_am' . $day] = '';
            $row['m_login_pm' . $day] = '';
            $row['m_logout_pm' . $day] = '';
            $row['remarks_display' . $day] = $day <= $daysInMonth
                ? $this->getWeekendText($year, $month, $day)
                : '';
        }

        return $row;
    }

    private function applyLogToMonthlyRow(array &$row, array $log, DateTimeImmutable $logDateTime): void
    {
        $columnName = $this->resolveColumnName((string) ($log['log_type'] ?? ''), (int) $logDateTime->format('j'));
        if ($columnName === '') {
            return;
        }

        $timeText = $logDateTime->format('H:i:s');
        $currentValue = trim((string) ($row[$columnName] ?? ''));

        if ($currentValue === '') {
            $row[$columnName] = $timeText;
            return;
        }

        if (str_contains($columnName, 'login')) {
            if (strcmp($timeText, $currentValue) < 0) {
                $row[$columnName] = $timeText;
            }

            return;
        }

        if (strcmp($timeText, $currentValue) > 0) {
            $row[$columnName] = $timeText;
        }
    }

    private function resolveColumnName(string $logType, int $day): string
    {
        $normalizedLogType = strtoupper(str_replace(' ', '', trim($logType)));

        return match ($normalizedLogType) {
            'LOGIN:AM', 'LOGINAM' => 'm_login_am' . $day,
            'LOGOUT:AM', 'LOGOUTAM' => 'm_logout_am' . $day,
            'LOGIN:PM', 'LOGINPM' => 'm_login_pm' . $day,
            'LOGOUT:PM', 'LOGOUTPM' => 'm_logout_pm' . $day,
            default => '',
        };
    }

    private function parseLogDateTime(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (self::SUPPORTED_LOG_DATE_FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseMonth(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $month = (int) $value;
            return $month >= 1 && $month <= 12 ? $month : null;
        }

        foreach (['F', 'M'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false) {
                return (int) $parsed->format('n');
            }
        }

        return null;
    }

    private function normalizeLogTime(string $value, bool $showDashForMissing): string
    {
        $value = trim($value);
        if ($value === '') {
            return $showDashForMissing ? '-' : '';
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false) {
                return $parsed->format('H:i');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('H:i');
        } catch (\Exception) {
            return $value;
        }
    }

    private function resolveRemark(string $yearText, string $monthText, string $dayText): string
    {
        $year = (int) trim($yearText);
        $month = $this->parseMonth($monthText);
        $day = (int) trim($dayText);

        if ($year <= 0 || $month === null || $day <= 0) {
            return '';
        }

        try {
            $weekday = (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)))->format('l');
        } catch (\Exception) {
            return '';
        }

        return in_array($weekday, ['Saturday', 'Sunday'], true) ? $weekday : '';
    }

    private function getWeekendText(int $year, int $month, int $day): string
    {
        return $this->resolveRemark((string) $year, (string) $month, (string) $day);
    }

    private function formatMonthDisplay(string $monthText): string
    {
        $month = $this->parseMonth($monthText);
        if ($month === null) {
            return strtoupper(trim($monthText));
        }

        return strtoupper((new DateTimeImmutable(sprintf('2024-%02d-01', $month)))->format('F'));
    }

    private function formatScheduleRange(string $from, string $to): string
    {
        return $this->formatScheduleTime($from) . ' - ' . $this->formatScheduleTime($to);
    }

    private function formatScheduleTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))->format('H:i');
        } catch (\Exception) {
            return $value;
        }
    }
}
