<?php

namespace Tests\Unit;

use App\Support\DtrDataTransformer;
use PHPUnit\Framework\TestCase;

class DtrDataTransformerTest extends TestCase
{
    public function test_it_builds_monthly_rows_and_keeps_earliest_login_and_latest_logout(): void
    {
        $transformer = new DtrDataTransformer();

        $rows = $transformer->buildMonthlyRows([
            [
                'mac_id' => '11',
                'datetime_raw' => '2026-05-05 08:11:00',
                'log_type' => 'LOGIN:AM',
                'office_id' => '1',
            ],
            [
                'mac_id' => '11',
                'datetime_raw' => '2026-05-05 07:59:00',
                'log_type' => 'LOGIN:AM',
                'office_id' => '1',
            ],
            [
                'mac_id' => '11',
                'datetime_raw' => '2026-05-05 17:01:00',
                'log_type' => 'LOGOUT:PM',
                'office_id' => '1',
            ],
            [
                'mac_id' => '11',
                'datetime_raw' => '2026-05-05 17:45:00',
                'log_type' => 'LOGOUT:PM',
                'office_id' => '1',
            ],
        ], 2026, 5);

        $this->assertCount(1, $rows);
        $this->assertSame('07:59:00', $rows[0]['m_login_am5']);
        $this->assertSame('17:45:00', $rows[0]['m_logout_pm5']);
    }

    public function test_it_merges_non_global_rows_and_skips_global_employees_in_school_mode(): void
    {
        $transformer = new DtrDataTransformer();

        $bios = [
            [
                'b_mac_id' => 11,
                'b_firstname' => 'Arthur',
                'b_lastname' => 'Aquino',
                'b_office_id' => 1,
                'b_designation' => 'ITO I',
                'b_job_status' => 'PERMANENT',
                'b_signatories' => '49',
                'b_is_global' => true,
            ],
            [
                'b_mac_id' => 20,
                'b_firstname' => 'Jannsen',
                'b_lastname' => 'Bayog',
                'b_office_id' => 1,
                'b_designation' => 'ADMINISTRATIVE AIDE III',
                'b_job_status' => 'CASUAL',
                'b_signatories' => '48',
                'b_is_global' => false,
            ],
        ];

        $monthlyRows = [
            ['m_id' => '11', 'm_office_id' => '1', 'm_year' => '2026', 'm_month' => '5'],
            ['m_id' => '20', 'm_office_id' => '1', 'm_year' => '2026', 'm_month' => '5'],
        ];

        $mergedRows = $transformer->mergeBiosAndMonthlyRows($bios, $monthlyRows, false, [
            48 => 'Supervisor 48',
            49 => 'Supervisor 49',
        ]);

        $this->assertCount(1, $mergedRows);
        $this->assertSame(20, $mergedRows[0]['b_mac_id']);
        $this->assertSame('Supervisor 48', $mergedRows[0]['b_signatory_name']);
    }

    public function test_it_builds_whole_month_report_rows_with_dashes_for_missing_times(): void
    {
        $transformer = new DtrDataTransformer();

        $reportRows = $transformer->buildReportRows([
            [
                'b_office_id' => 1,
                'b_mac_id' => 20,
                'b_firstname' => 'Jannsen',
                'b_lastname' => 'Bayog',
                'b_designation' => 'ADMINISTRATIVE AIDE III',
                'b_job_status' => 'CASUAL',
                'b_hrs_am_in' => '1753-01-01T08:00:00',
                'b_hrs_am_out' => '1753-01-01T12:00:00',
                'b_hrs_pm_in' => '1753-01-01T13:00:00',
                'b_hrs_pm_out' => '1753-01-01T17:00:00',
                'b_signatory_name' => 'Supervisor 48',
                'm_year' => '2026',
                'm_month' => '5',
                'm_day1' => '1',
                'm_login_am1' => '08:01:00',
                'm_logout_am1' => '',
                'm_login_pm1' => '',
                'm_logout_pm1' => '17:03:00',
                'm_day2' => '2',
                'm_login_am2' => '',
                'm_logout_am2' => '',
                'm_login_pm2' => '',
                'm_logout_pm2' => '',
                'm_day16' => '16',
                'm_login_am16' => '08:05:00',
                'm_logout_am16' => '12:01:00',
                'm_login_pm16' => '13:02:00',
                'm_logout_pm16' => '17:10:00',
            ],
        ], [
            'Supervisor 48' => 'SCHOOL PRINCIPAL II',
        ]);

        $this->assertCount(1, $reportRows);
        $this->assertCount(2, $reportRows[0]['groups']);
        $this->assertSame('Days 1-15', $reportRows[0]['groups'][0]['label']);
        $this->assertSame('Days 16-31', $reportRows[0]['groups'][1]['label']);
        $this->assertSame('SCHOOL PRINCIPAL II', $reportRows[0]['signatory_designation']);
        $this->assertSame('08:01', $reportRows[0]['groups'][0]['days'][0]['am_in']);
        $this->assertSame('-', $reportRows[0]['groups'][0]['days'][0]['am_out']);
        $this->assertSame('-', $reportRows[0]['groups'][0]['days'][1]['am_in']);
        $this->assertSame('08:05', $reportRows[0]['groups'][1]['days'][0]['am_in']);
    }
}
