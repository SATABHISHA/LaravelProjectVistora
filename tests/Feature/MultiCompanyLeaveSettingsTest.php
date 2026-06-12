<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\LeaveSetting;
use App\Models\EmployeeLeaveBalance;
use App\Models\CorpCompanyTag;
use App\Models\LeaveTypeBasicConfiguration;
use App\Models\UserLogin;
use App\Models\EmployeeDetail;
use App\Models\EmploymentDetail;

class MultiCompanyLeaveSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $corpId = 'CORP_TEST';

    // -------------------------------------------------------------------------
    // Leave Settings Tests
    // -------------------------------------------------------------------------

    /** @test */
    public function it_upserts_leave_settings_for_a_company()
    {
        $payload = [
            'corpId'      => $this->corpId,
            'companyName' => 'Hyderabad Office',
            'year'        => 2026,
            'leaveSettings' => [
                ['leaveType' => 'Sick',   'monthlyAllocation' => 1,   'yearlyAllocation' => 12, 'carryForwardLimit' => 5,  'encashmentLimit' => 0],
                ['leaveType' => 'Paid',   'monthlyAllocation' => 1.5, 'yearlyAllocation' => 18, 'carryForwardLimit' => 10, 'encashmentLimit' => 5],
                ['leaveType' => 'Casual', 'monthlyAllocation' => 1,   'yearlyAllocation' => 12, 'carryForwardLimit' => 3,  'encashmentLimit' => 0],
            ],
        ];

        $response = $this->postJson('/api/leave-settings/upsert', $payload);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => true])
                 ->assertJsonCount(3, 'data');

        $this->assertDatabaseHas('leave_settings', [
            'corp_id'      => $this->corpId,
            'company_name' => 'Hyderabad Office',
            'year'         => 2026,
            'leave_type'   => 'Sick',
        ]);
    }

    /** @test */
    public function same_corp_two_companies_have_independent_leave_settings()
    {
        // Upsert for Company A
        $this->postJson('/api/leave-settings/upsert', [
            'corpId'      => $this->corpId,
            'companyName' => 'Company A',
            'year'        => 2026,
            'leaveSettings' => [
                ['leaveType' => 'Sick', 'monthlyAllocation' => 1, 'yearlyAllocation' => 12, 'carryForwardLimit' => 5, 'encashmentLimit' => 0],
            ],
        ])->assertStatus(200);

        // Upsert for Company B (different monthly allocation)
        $this->postJson('/api/leave-settings/upsert', [
            'corpId'      => $this->corpId,
            'companyName' => 'Company B',
            'year'        => 2026,
            'leaveSettings' => [
                ['leaveType' => 'Sick', 'monthlyAllocation' => 2, 'yearlyAllocation' => 24, 'carryForwardLimit' => 0, 'encashmentLimit' => 0],
            ],
        ])->assertStatus(200);

        // Company A settings unchanged
        $settingA = LeaveSetting::where('corp_id', $this->corpId)
            ->where('company_name', 'Company A')
            ->where('leave_type', 'Sick')
            ->first();
        $this->assertEquals('1.00', $settingA->monthly_allocation);

        // Company B settings independent
        $settingB = LeaveSetting::where('corp_id', $this->corpId)
            ->where('company_name', 'Company B')
            ->where('leave_type', 'Sick')
            ->first();
        $this->assertEquals('2.00', $settingB->monthly_allocation);
    }

    /** @test */
    public function get_leave_settings_filters_by_company_name()
    {
        LeaveSetting::create([
            'corp_id' => $this->corpId, 'company_name' => 'Hyderabad Office',
            'year' => 2026, 'leave_type' => 'Paid',
            'monthly_allocation' => 1.5, 'yearly_allocation' => 18,
            'carry_forward_limit' => 10, 'encashment_limit' => 5,
        ]);

        LeaveSetting::create([
            'corp_id' => $this->corpId, 'company_name' => 'Chennai Office',
            'year' => 2026, 'leave_type' => 'Paid',
            'monthly_allocation' => 1, 'yearly_allocation' => 12,
            'carry_forward_limit' => 5, 'encashment_limit' => 0,
        ]);

        $response = $this->getJson("/api/leave-settings/{$this->corpId}/2026?company_name=Hyderabad+Office");

        $response->assertStatus(200)
                 ->assertJsonFragment(['companyName' => 'Hyderabad Office'])
                 ->assertJsonMissing(['companyName' => 'Chennai Office']);
    }

    /** @test */
    public function upsert_requires_company_name()
    {
        $response = $this->postJson('/api/leave-settings/upsert', [
            'corpId' => $this->corpId,
            'year'   => 2026,
            'leaveSettings' => [
                ['leaveType' => 'Sick', 'monthlyAllocation' => 1, 'yearlyAllocation' => 12, 'carryForwardLimit' => 5, 'encashmentLimit' => 0],
            ],
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['companyName']);
    }

    /** @test */
    public function upsert_requires_valid_leave_type()
    {
        $response = $this->postJson('/api/leave-settings/upsert', [
            'corpId'      => $this->corpId,
            'companyName' => 'Test Company',
            'year'        => 2026,
            'leaveSettings' => [
                ['leaveType' => 'InvalidType', 'monthlyAllocation' => 1, 'yearlyAllocation' => 12, 'carryForwardLimit' => 0, 'encashmentLimit' => 0],
            ],
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['leaveSettings.0.leaveType']);
    }

    /** @test */
    public function get_leave_settings_requires_company_name_query()
    {
        $response = $this->getJson("/api/leave-settings/{$this->corpId}/2026");

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['company_name']);
    }

    // -------------------------------------------------------------------------
    // Leave Balance Tests
    // -------------------------------------------------------------------------

    /** @test */
    public function allot_leaves_requires_company_name()
    {
        $this->createAdminUser();

        $response = $this->postJson('/api/employee-leave-balance/allot', [
            'corp_id'  => $this->corpId,
            'emp_code' => 'ADMIN001',
            'year'     => 2026,
            // company_name missing
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['company_name']);
    }

    /** @test */
    public function process_monthly_requires_company_name()
    {
        $this->createAdminUser();

        $response = $this->postJson('/api/employee-leave-balance/process-monthly', [
            'corp_id'  => $this->corpId,
            'emp_code' => 'ADMIN001',
            'year'     => 2026,
            'month'    => 6,
            // company_name missing
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['company_name']);
    }

    /** @test */
    public function leave_list_filters_by_company_name()
    {
        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId, 'emp_code' => 'EMP001',
            'emp_full_name' => 'Alice', 'company_name' => 'Company A',
            'leave_type_puid' => 'puid-1', 'leave_code' => 'SL', 'leave_name' => 'Sick Leave',
            'total_allotted' => 12, 'used' => 0, 'balance' => 12, 'carry_forward' => 0,
            'year' => 2026, 'credit_type' => 'yearly',
        ]);

        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId, 'emp_code' => 'EMP002',
            'emp_full_name' => 'Bob', 'company_name' => 'Company B',
            'leave_type_puid' => 'puid-1', 'leave_code' => 'SL', 'leave_name' => 'Sick Leave',
            'total_allotted' => 12, 'used' => 0, 'balance' => 12, 'carry_forward' => 0,
            'year' => 2026, 'credit_type' => 'yearly',
        ]);

        $response = $this->getJson("/api/employee-leave-balance/list/{$this->corpId}?year=2026&company_name=Company+A");

        $response->assertStatus(200)
                 ->assertJsonFragment(['emp_code' => 'EMP001'])
                 ->assertJsonMissing(['emp_code' => 'EMP002']);
    }

    /** @test */
    public function leave_summary_filters_by_company_name()
    {
        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId, 'emp_code' => 'EMP001',
            'emp_full_name' => 'Alice', 'company_name' => 'Company A',
            'leave_type_puid' => 'puid-1', 'leave_code' => 'SL', 'leave_name' => 'Sick Leave',
            'total_allotted' => 12, 'used' => 2, 'balance' => 10, 'carry_forward' => 0,
            'year' => 2026, 'credit_type' => 'yearly',
        ]);

        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId, 'emp_code' => 'EMP002',
            'emp_full_name' => 'Bob', 'company_name' => 'Company B',
            'leave_type_puid' => 'puid-1', 'leave_code' => 'SL', 'leave_name' => 'Sick Leave',
            'total_allotted' => 12, 'used' => 5, 'balance' => 7, 'carry_forward' => 0,
            'year' => 2026, 'credit_type' => 'yearly',
        ]);

        $response = $this->getJson("/api/employee-leave-balance/summary/{$this->corpId}?year=2026&company_name=Company+A");

        $response->assertStatus(200)
                 ->assertJsonFragment(['company_name' => 'Company A']);

        // Verify aggregated totals only include Company A data (total_used = 2, not 7)
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('2.00', (string) number_format((float) $data[0]['total_used'], 2));
    }

    /** @test */
    public function leave_names_filters_by_company_name()
    {
        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId, 'emp_code' => 'EMP001',
            'emp_full_name' => 'Alice', 'company_name' => 'Company A',
            'leave_type_puid' => 'puid-1', 'leave_code' => 'SL', 'leave_name' => 'Sick Leave',
            'total_allotted' => 12, 'used' => 0, 'balance' => 12, 'carry_forward' => 0,
            'year' => 2026, 'credit_type' => 'yearly',
        ]);

        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId, 'emp_code' => 'EMP002',
            'emp_full_name' => 'Bob', 'company_name' => 'Company B',
            'leave_type_puid' => 'puid-2', 'leave_code' => 'PL', 'leave_name' => 'Paid Leave',
            'total_allotted' => 12, 'used' => 0, 'balance' => 12, 'carry_forward' => 0,
            'year' => 2026, 'credit_type' => 'yearly',
        ]);

        $response = $this->getJson("/api/employee-leave-balance/leave-names/{$this->corpId}/ALL?year=2026&company_name=Company+A");

        $response->assertStatus(200)
            ->assertJsonFragment(['emp_code' => 'EMP001'])
            ->assertJsonMissing(['emp_code' => 'EMP002']);
    }

    /** @test */
    public function process_monthly_only_updates_selected_company()
    {
        $this->createAdminUser();

        LeaveTypeBasicConfiguration::create([
            'puid' => 'puid-monthly',
            'corpid' => $this->corpId,
            'leaveCode' => 'SL',
            'leaveName' => 'Sick Leave',
            'leaveCycleStartMonth' => 'January',
            'leaveCycleEndMonth' => 'December',
            'leaveTypeTobeCredited' => 'monthly',
            'LimitDays' => '12',
            'LeaveType' => 'Sick',
            'encahsmentAllowedYN' => 0,
            'isConfigurationCompletedYN' => 1,
        ]);

        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId,
            'emp_code' => 'EMP001',
            'emp_full_name' => 'Alice',
            'company_name' => 'Company A',
            'leave_type_puid' => 'puid-monthly',
            'leave_code' => 'SL',
            'leave_name' => 'Sick Leave',
            'total_allotted' => 1,
            'used' => 0,
            'balance' => 1,
            'carry_forward' => 0,
            'year' => 2026,
            'month' => 1,
            'credit_type' => 'monthly',
        ]);

        EmployeeLeaveBalance::create([
            'corp_id' => $this->corpId,
            'emp_code' => 'EMP002',
            'emp_full_name' => 'Bob',
            'company_name' => 'Company B',
            'leave_type_puid' => 'puid-monthly',
            'leave_code' => 'SL',
            'leave_name' => 'Sick Leave',
            'total_allotted' => 1,
            'used' => 0,
            'balance' => 1,
            'carry_forward' => 0,
            'year' => 2026,
            'month' => 1,
            'credit_type' => 'monthly',
        ]);

        $this->postJson('/api/employee-leave-balance/process-monthly', [
            'corp_id' => $this->corpId,
            'emp_code' => 'ADMIN001',
            'company_name' => 'Company A',
            'year' => 2026,
            'month' => 2,
        ])->assertStatus(200);

        $companyA = EmployeeLeaveBalance::where('corp_id', $this->corpId)
            ->where('emp_code', 'EMP001')
            ->first();
        $companyB = EmployeeLeaveBalance::where('corp_id', $this->corpId)
            ->where('emp_code', 'EMP002')
            ->first();

        $this->assertEquals(2.0, (float) $companyA->total_allotted);
        $this->assertEquals(2, (int) $companyA->month);
        $this->assertEquals(1.0, (float) $companyB->total_allotted);
        $this->assertEquals(1, (int) $companyB->month);
    }

    /** @test */
    public function allot_leaves_allows_same_employee_year_leave_type_across_companies()
    {
        $this->createAdminUser();
        $this->createBasicLeaveConfig('puid-duplicate-safe', 'SL', 'Sick Leave');
        $this->createEmployeeForCompanies('EMP001', ['Company A', 'Company B']);

        $payloadA = [
            'corp_id' => $this->corpId,
            'emp_code' => 'ADMIN001',
            'year' => 2026,
            'company_name' => 'Company A',
        ];

        $payloadB = [
            'corp_id' => $this->corpId,
            'emp_code' => 'ADMIN001',
            'year' => 2026,
            'company_name' => 'Company B',
        ];

        $this->postJson('/api/employee-leave-balance/allot', $payloadA)
            ->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->postJson('/api/employee-leave-balance/allot', $payloadB)
            ->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('employee_leave_balances', [
            'corp_id' => $this->corpId,
            'emp_code' => 'EMP001',
            'leave_type_puid' => 'puid-duplicate-safe',
            'year' => 2026,
            'company_name' => 'Company A',
        ]);

        $this->assertDatabaseHas('employee_leave_balances', [
            'corp_id' => $this->corpId,
            'emp_code' => 'EMP001',
            'leave_type_puid' => 'puid-duplicate-safe',
            'year' => 2026,
            'company_name' => 'Company B',
        ]);
    }

    /** @test */
    public function repeat_allot_for_same_company_is_skipped_not_duplicated()
    {
        $this->createAdminUser();
        $this->createBasicLeaveConfig('puid-repeat-skip', 'CL', 'Causal Leave');
        $this->createEmployeeForCompanies('EMP001', ['Company A']);

        $payload = [
            'corp_id' => $this->corpId,
            'emp_code' => 'ADMIN001',
            'year' => 2026,
            'company_name' => 'Company A',
        ];

        $first = $this->postJson('/api/employee-leave-balance/allot', $payload);
        $first->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total_leave_records_created', 1)
            ->assertJsonPath('data.total_records_skipped', 0);

        $second = $this->postJson('/api/employee-leave-balance/allot', $payload);
        $second->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total_leave_records_created', 0)
            ->assertJsonPath('data.total_records_skipped', 1);

        $this->assertEquals(
            1,
            EmployeeLeaveBalance::where('corp_id', $this->corpId)
                ->where('emp_code', 'EMP001')
                ->where('leave_type_puid', 'puid-repeat-skip')
                ->where('year', 2026)
                ->where('company_name', 'Company A')
                ->count()
        );
    }

    // -------------------------------------------------------------------------
    // Corp Company Tag Tests
    // -------------------------------------------------------------------------

    /** @test */
    public function corp_company_tag_crud_create_rename_delete_works()
    {
        $createResponse = $this->postJson('/api/corp-company-tags/upsert', [
            'corpId' => $this->corpId,
            'companyTag' => 'Hyderabad Office',
        ]);

        $createResponse->assertStatus(200)
            ->assertJsonFragment(['status' => true])
            ->assertJsonFragment(['companyTag' => 'Hyderabad Office']);

        $this->assertDatabaseHas('corp_company_tags', [
            'corp_id' => $this->corpId,
            'company_tag' => 'Hyderabad Office',
            'active_yn' => 1,
        ]);

        $renameResponse = $this->postJson('/api/corp-company-tags/upsert', [
            'corpId' => $this->corpId,
            'oldCompanyTag' => 'Hyderabad Office',
            'companyTag' => 'Hyderabad HQ',
        ]);

        $renameResponse->assertStatus(200)
            ->assertJsonFragment(['status' => true])
            ->assertJsonFragment(['companyTag' => 'Hyderabad HQ']);

        $deleteResponse = $this->deleteJson("/api/corp-company-tags/{$this->corpId}/Hyderabad HQ");
        $deleteResponse->assertStatus(200)
            ->assertJsonFragment(['status' => true]);

        $this->assertDatabaseHas('corp_company_tags', [
            'corp_id' => $this->corpId,
            'company_tag' => 'Hyderabad HQ',
            'active_yn' => 0,
        ]);
    }

    /** @test */
    public function corp_company_tag_index_returns_only_active_tags()
    {
        CorpCompanyTag::create([
            'corp_id' => $this->corpId,
            'company_tag' => 'Tag A',
            'active_yn' => 1,
        ]);
        CorpCompanyTag::create([
            'corp_id' => $this->corpId,
            'company_tag' => 'Tag B',
            'active_yn' => 0,
        ]);

        $response = $this->getJson("/api/corp-company-tags/{$this->corpId}");

        $response->assertStatus(200)
            ->assertJsonFragment(['companyTag' => 'Tag A'])
            ->assertJsonMissing(['companyTag' => 'Tag B']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createAdminUser(): void
    {
        UserLogin::create([
            'corp_id'    => $this->corpId,
            'empcode'    => 'ADMIN001',
            'email_id'   => 'admin@test.com',
            'username'   => 'admin',
            'password'   => bcrypt('secret'),
            'admin_yn'   => 1,
        ]);
    }

    private function createBasicLeaveConfig(string $puid, string $leaveCode, string $leaveName): void
    {
        LeaveTypeBasicConfiguration::create([
            'puid' => $puid,
            'corpid' => $this->corpId,
            'leaveCode' => $leaveCode,
            'leaveName' => $leaveName,
            'leaveCycleStartMonth' => 'January',
            'leaveCycleEndMonth' => 'December',
            'leaveTypeTobeCredited' => 'yearly',
            'LimitDays' => '12',
            'LeaveType' => 'Sick',
            'encahsmentAllowedYN' => 0,
            'isConfigurationCompletedYN' => 1,
        ]);
    }

    private function createEmployeeForCompanies(string $empCode, array $companyNames): void
    {
        EmployeeDetail::create([
            'corp_id' => $this->corpId,
            'EmpCode' => $empCode,
            'FirstName' => 'Test',
            'LastName' => 'Employee',
            'DOB' => '1990-01-01',
        ]);

        foreach ($companyNames as $companyName) {
            EmploymentDetail::create([
                'corp_id' => $this->corpId,
                'company_name' => $companyName,
                'dateOfJoining' => '2024-01-01',
                'EmpCode' => $empCode,
                'ActiveYn' => 1,
            ]);
        }
    }
}
