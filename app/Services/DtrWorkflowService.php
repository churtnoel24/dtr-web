<?php

namespace App\Services;

use App\Support\DtrDataTransformer;
use RuntimeException;

class DtrWorkflowService
{
    public function __construct(
        private readonly DtrApiClient $apiClient,
        private readonly DtrDataTransformer $transformer,
    ) {
    }

    public function buildDashboard(array $filters): array
    {
        $statuses = $this->apiClient->getStatuses();
        $offices = $this->apiClient->getOffices();

        $base = [
            'statuses' => $statuses,
            'offices' => $offices,
            'merged_rows' => [],
            'selected_row' => null,
            'selected_mac_id' => trim((string) ($filters['selected_mac_id'] ?? '')),
            'dtr_logs_count_text' => 'Employees ready: 0',
            'status_message' => 'Select filters and click GET LOGS to preview the whole month.',
        ];

        if (!($filters['fetch'] ?? false)) {
            return $base;
        }

        $loaded = $this->buildLoadedData($filters);
        $selectedRow = $this->transformer->pickSelectedMergedRow(
            $loaded['merged_rows'],
            $filters['selected_mac_id'] ?? null
        );
        $mergedRowCount = count($loaded['merged_rows']);

        return array_merge($base, [
            'merged_rows' => $loaded['merged_rows'],
            'selected_row' => $selectedRow,
            'selected_mac_id' => $selectedRow['b_mac_id'] ?? '',
            'dtr_logs_count_text' => 'Employees ready: ' . $mergedRowCount,
            'status_message' => $selectedRow === null
                ? 'No merged DTR rows are available for the current filters.'
                : 'DTR preview loaded for the whole month.',
        ]);
    }

    public function buildReportDocument(array $filters, string $scope): array
    {
        $loaded = $this->buildLoadedData($filters);
        $mergedRows = $loaded['merged_rows'];

        if ($scope === 'selected') {
            $selectedRow = $this->transformer->pickSelectedMergedRow(
                $mergedRows,
                $filters['selected_mac_id'] ?? null
            );

            if ($selectedRow === null) {
                throw new RuntimeException('Select a merged DTR row first.');
            }

            $mergedRows = [$selectedRow];
        }

        if ($mergedRows === []) {
            throw new RuntimeException('No merged DTR rows are available for the current filters.');
        }

        return [
            'scope' => $scope,
            'filters' => $filters,
            'report_rows' => $this->transformer->buildReportRows(
                $mergedRows,
                $loaded['signatory_designation_by_name']
            ),
        ];
    }

    private function buildLoadedData(array $filters): array
    {
        $year = (int) ($filters['year'] ?? 0);
        $month = (int) ($filters['month'] ?? 0);
        $office = trim((string) ($filters['office'] ?? ''));
        $macId = trim((string) ($filters['mac_id'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $global = (bool) ($filters['global'] ?? false);

        if ($year <= 0) {
            throw new RuntimeException('Invalid Year value.');
        }

        if ($month < 1 || $month > 12) {
            throw new RuntimeException('Invalid Month value.');
        }

        if (!$global && $office === '' && $macId === '') {
            throw new RuntimeException('Do not proceed: missing Office or MacID.');
        }

        $bios = $this->apiClient->getBios();
        $signatories = $this->apiClient->getSignatories();

        $logs = $global
            ? $this->loadGlobalLogs($bios, $year, $month, $office, $macId, $status)
            : $this->apiClient->searchLogs(
                $year,
                $month,
                $office !== '' ? $office : null,
                $macId !== '' ? $macId : null
            );

        $normalizedLogs = $this->transformer->normalizeLogs($logs);
        $filteredBios = $this->transformer->filterBios($bios, $status, $office, $global);
        $monthlyRows = $this->transformer->buildMonthlyRows($normalizedLogs, $year, $month);
        $signatoryNameById = collect($signatories)
            ->mapWithKeys(fn (array $row) => [$row['id'] => $row['name']])
            ->all();
        $signatoryDesignationByName = collect($signatories)
            ->mapWithKeys(fn (array $row) => [$row['name'] => $row['designation']])
            ->all();

        return [
            'bios' => $filteredBios,
            'monthly_rows' => $monthlyRows,
            'merged_rows' => $this->transformer->mergeBiosAndMonthlyRows(
                $filteredBios,
                $monthlyRows,
                $global,
                $signatoryNameById
            ),
            'signatory_designation_by_name' => $signatoryDesignationByName,
        ];
    }

    private function loadGlobalLogs(
        array $bios,
        int $year,
        int $month,
        string $office,
        string $macId,
        string $status,
    ): array {
        $macIds = $this->transformer->resolveGlobalMacIds(
            $bios,
            $office,
            $macId,
            $status
        );

        $logs = [];
        foreach ($macIds as $resolvedMacId) {
            $logs = array_merge(
                $logs,
                $this->apiClient->searchLogs($year, $month, null, $resolvedMacId)
            );
        }

        return $logs;
    }
}
