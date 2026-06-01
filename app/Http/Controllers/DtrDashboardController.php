<?php

namespace App\Http\Controllers;

use App\Services\DtrWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class DtrDashboardController extends Controller
{
    public function __construct(
        private readonly DtrWorkflowService $workflowService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);
        $dashboard = null;
        $errorMessage = null;

        try {
            $dashboard = $this->workflowService->buildDashboard($filters);
        } catch (RuntimeException $exception) {
            $errorMessage = $exception->getMessage();

            try {
                $dashboard = $this->workflowService->buildDashboard(array_merge($filters, ['fetch' => false]));
            } catch (RuntimeException) {
                $dashboard = $this->emptyDashboard();
            }
        }

        return view('dtr.dashboard', [
            'dashboard' => $dashboard,
            'filters' => $filters,
            'months' => $this->monthOptions(),
            'years' => $this->yearOptions(),
            'errorMessage' => $errorMessage,
        ]);
    }

    public function report(Request $request): Response
    {
        $filters = $this->filtersFromRequest($request, forceFetch: true);
        $scope = $request->query('scope', 'selected') === 'all' ? 'all' : 'selected';

        try {
            return $this->htmlReportResponse(
                $filters,
                $scope,
                $request->boolean('print'),
                $request->boolean('embed')
            );
        } catch (RuntimeException $exception) {
            return $this->inlineReportErrorResponse($exception->getMessage());
        }
    }

    private function filtersFromRequest(Request $request, bool $forceFetch = false): array
    {
        $now = now();

        return [
            'fetch' => $forceFetch || $request->boolean('fetch'),
            'year' => (int) $request->input('year', $now->year),
            'month' => (int) $request->input('month', $now->month),
            'office' => trim((string) $request->input('office', '')),
            'mac_id' => trim((string) $request->input('mac_id', '')),
            'status' => trim((string) $request->input('status', '')),
            'global' => $request->boolean('global'),
            'selected_mac_id' => trim((string) $request->input('selected_mac_id', '')),
        ];
    }

    private function yearOptions(): array
    {
        $currentYear = now()->year;

        return range(2024, $currentYear);
    }

    private function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [
                $month => \Carbon\CarbonImmutable::create(2024, $month, 1)->format('F'),
            ])
            ->all();
    }

    private function emptyDashboard(): array
    {
        return [
            'statuses' => [],
            'offices' => [],
            'merged_rows' => [],
            'selected_row' => null,
            'selected_mac_id' => '',
            'dtr_logs_count_text' => 'Employees ready: 0',
            'status_message' => 'Production API is unavailable right now.',
        ];
    }

    private function inlineReportErrorResponse(string $message): Response
    {
        $safeMessage = e($message);

        return response(
            <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DTR Preview Error</title>
    <style>
        body { margin: 0; background: #f6f4ef; color: #17304d; font-family: "Segoe UI", Arial, sans-serif; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { max-width: 560px; padding: 24px; border-radius: 20px; background: #fff; box-shadow: 0 16px 38px rgba(23, 48, 77, 0.08); }
        h1 { margin: 0 0 10px; font-size: 20px; }
        p { margin: 0; line-height: 1.6; color: #5c6b78; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>DTR preview could not be generated.</h1>
            <p>{$safeMessage}</p>
        </div>
    </div>
</body>
</html>
HTML,
            500
        );
    }

    private function htmlReportResponse(array $filters, string $scope, bool $autoPrint, bool $embedded = false): Response
    {
        $document = $this->workflowService->buildReportDocument($filters, $scope);

        return response()->view('dtr.report', [
            'document' => $document,
            'autoPrint' => $autoPrint,
            'embedded' => $embedded,
        ]);
    }
}
