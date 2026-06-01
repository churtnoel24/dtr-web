<?php

namespace Tests\Feature;

use App\Services\DtrWorkflowService;
use Mockery\MockInterface;
use Tests\TestCase;

class DtrReportControllerTest extends TestCase
{
    public function test_report_route_renders_the_laravel_dtr_preview(): void
    {
        $document = $this->fakeDocument();

        $this->mock(DtrWorkflowService::class, function (MockInterface $mock) use ($document): void {
            $mock->shouldReceive('buildReportDocument')->once()->andReturn($document);
        });

        $response = $this->get('/report?fetch=1&year=2026&month=5&office=1&selected_mac_id=11');

        $response->assertOk();
        $response->assertSee('DAILY TIME RECORD');
        $response->assertSee('Aquino Arthur');
        $response->assertDontSee('FastReport');
    }

    private function fakeDocument(): array
    {
        return [
            'report_rows' => [[
                'mac_office_display' => 'Mac ID 11 / Office 1',
                'employee_name_inline' => 'Aquino Arthur',
                'schedule_am' => '08:00 - 12:00',
                'schedule_pm' => '13:00 - 17:00',
                'month_display' => 'May',
                'year_display' => '2026',
                'designation' => 'ITO I',
                'job_status' => 'PERMANENT',
                'groups' => [[
                    'label' => 'Days 1-15',
                    'days' => [[
                        'day_label' => '1',
                        'am_in' => '08:00',
                        'am_out' => '12:00',
                        'pm_in' => '13:00',
                        'pm_out' => '17:00',
                        'remark' => '',
                    ]],
                ]],
                'signatory_name' => 'Supervisor 48',
                'signatory_designation' => 'SCHOOL PRINCIPAL II',
            ]],
        ];
    }
}
