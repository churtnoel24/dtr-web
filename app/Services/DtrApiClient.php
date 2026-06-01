<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DtrApiClient
{
    public function getStatuses(): array
    {
        $rows = $this->request()->get('api/TblEmpstatus')->json() ?? [];

        return collect($rows)
            ->map(fn (array $row) => [
                'id' => (int) ($row['sId'] ?? 0),
                'name' => trim((string) ($row['sEmp'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    public function getOffices(): array
    {
        $rows = $this->request()->get('api/SdoOffice')->json() ?? [];

        return collect($rows)
            ->map(fn (array $row) => [
                'office_id' => (int) ($row['officeId'] ?? 0),
                'office_os_id' => (int) ($row['officeOsId'] ?? 0),
                'office_name' => trim((string) ($row['officeName'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['office_os_id'] > 0)
            ->sortBy('office_os_id')
            ->values()
            ->all();
    }

    public function getBios(): array
    {
        $rows = $this->request()->get('api/TblBio')->json() ?? [];

        return collect($rows)
            ->map(fn (array $row) => [
                'b_id' => (int) ($row['bId'] ?? 0),
                'b_guid' => trim((string) ($row['bGuid'] ?? '')),
                'b_firstname' => trim((string) ($row['bFirstname'] ?? '')),
                'b_lastname' => trim((string) ($row['bLastname'] ?? '')),
                'b_designation' => trim((string) ($row['bDesignation'] ?? '')),
                'b_office_id' => (int) ($row['bOfficeId'] ?? 0),
                'b_mac_id' => (int) ($row['bMacId'] ?? 0),
                'b_hrs_am_in' => (string) ($row['bHrsAmIn'] ?? ''),
                'b_hrs_am_out' => (string) ($row['bHrsAmOut'] ?? ''),
                'b_hrs_pm_in' => (string) ($row['bHrsPmIn'] ?? ''),
                'b_hrs_pm_out' => (string) ($row['bHrsPmOut'] ?? ''),
                'b_signatories' => trim((string) ($row['bSignatories'] ?? '')),
                'b_unit' => trim((string) ($row['bUnit'] ?? '')),
                'b_job_status' => trim((string) ($row['bJobStatus'] ?? '')),
                'b_is_global' => (bool) ($row['bIsGlobal'] ?? false),
                'b_is_active' => (bool) ($row['bIsActive'] ?? false),
            ])
            ->values()
            ->all();
    }

    public function getSignatories(): array
    {
        $rows = $this->request()->get('api/TblSchoolSignatories')->json() ?? [];

        return collect($rows)
            ->map(fn (array $row) => [
                'id' => (int) ($row['fldId'] ?? 0),
                'name' => trim((string) ($row['fldPname'] ?? '')),
                'designation' => trim((string) ($row['fldPdesignation'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['name'] !== '')
            ->values()
            ->all();
    }

    public function searchLogs(int $year, int $month, ?string $officeId, ?string $macId): array
    {
        $query = [
            'year' => $year,
            'month' => $month,
        ];

        if ($officeId !== null && $officeId !== '') {
            $query['fldOfficeId'] = trim($officeId);
        }

        if ($macId !== null && $macId !== '') {
            $query['fldMacId'] = trim($macId);
        }

        $rows = $this->request()->get('api/TblLogs/search-by-filters', $query)->json() ?? [];

        return collect($rows)
            ->map(fn (array $row) => [
                'id' => (int) ($row['fldId'] ?? 0),
                'mac_id' => trim((string) ($row['fldMacId'] ?? '')),
                'datetime_raw' => trim((string) ($row['fldDatetime'] ?? '')),
                'log_type' => trim((string) ($row['fldLog'] ?? '')),
                'office_id' => trim((string) ($row['fldOfficeId'] ?? '')),
                'device_id' => trim((string) ($row['deviceId'] ?? '')),
            ])
            ->values()
            ->all();
    }

    public function deleteMonthlyLogsByFilters(string $year, string $month, ?int $officeId, ?int $macId): void
    {
        $query = [
            'mYear' => trim($year),
            'mMonth' => trim($month),
        ];

        if ($officeId !== null) {
            $query['mOfficeId'] = $officeId;
        }

        if ($macId !== null) {
            $query['mId'] = $macId;
        }

        $this->request()->delete('api/TblIoTdtrTest/by-filter?' . http_build_query($query));
    }

    public function bulkUploadMonthlyLogs(array $records): void
    {
        if ($records === []) {
            return;
        }

        $this->request()->post('api/TblIoTdtrTest/bulk-upload', $records);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('dtr.api_base_url'), '/'))
            ->timeout((int) config('dtr.api_timeout', 300))
            ->acceptJson()
            ->asJson()
            ->throw(function ($response, RequestException $exception) {
                $message = trim((string) $response->body());
                throw new RuntimeException($message !== '' ? $message : $exception->getMessage(), 0, $exception);
            })
            ->retry(1, 250, function ($exception) {
                return $exception instanceof ConnectionException;
            });
    }
}
