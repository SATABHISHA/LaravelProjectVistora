<?php

use App\Http\Controllers\UserLoginApiController;
use App\Http\Controllers\CompanyDetailsApiController;
use App\Http\Controllers\BusinessUnitApiController;
use App\Http\Controllers\DepartmentApiController;
use App\Http\Controllers\LocationApiController;
use App\Http\Controllers\IndustryApiController;
use App\Http\Controllers\CurrencyApiController;
use App\Http\Controllers\SubDepartmentApiController;
use App\Http\Controllers\DesignationApiController;
use App\Http\Controllers\GradeApiController;
use App\Http\Controllers\DocumentTypeApiController;
use App\Http\Controllers\DocumentFormListApiController;
use App\Http\Controllers\DocumentFieldListApiController;
use App\Http\Controllers\DocumentApiController;
use App\Http\Controllers\QualificationApiController;
use App\Http\Controllers\SpecializationApiController;
use App\Http\Controllers\BlacklistApiController;
use App\Http\Controllers\JobFunctionApiController;
use App\Http\Controllers\SkillApiController;
use App\Http\Controllers\SkillProficiencyApiController;
use App\Http\Controllers\BankApiController;
use App\Http\Controllers\BankChallanReportApiController;
use App\Http\Controllers\PaymentBankApiController;
use App\Http\Controllers\VendorApiController;
use App\Http\Controllers\CustomerDetailApiController;
use App\Http\Controllers\ProfileAccessSettingApiController;
use App\Http\Controllers\SocialProfileAccessApiController;
use App\Http\Controllers\EmploymentDetailApiController;
use App\Http\Controllers\EmployeeDetailApiController;
use App\Http\Controllers\EmployeeStatutoryDetailApiController;
use App\Http\Controllers\FamilyDetailApiController;
use App\Http\Controllers\ChildApiController;
use App\Http\Controllers\EmployeeWorkExperienceApiController;
use App\Http\Controllers\RelationApiController;
use App\Http\Controllers\EmployeeEducationApiController;
use App\Http\Controllers\EmployeeSkillApiController;
use App\Http\Controllers\EmployeeInsurancePolicyApiController;
use App\Http\Controllers\EmployeeNomineeDetailApiController;
use App\Http\Controllers\EmployeeBankDetailApiController;
use App\Http\Controllers\RegionApiController;
use App\Http\Controllers\BranchApiController;
use App\Http\Controllers\SubBranchApiController;
use App\Http\Controllers\EmploymentTypeApiController;
use App\Http\Controllers\EmploymentStatusApiController;
use App\Http\Controllers\ConfirmationStatusApiController;
use App\Http\Controllers\CorporateIdApiController;
use App\Http\Controllers\RoleApiController;
use App\Http\Controllers\EmployeeAssignedRoleApiController;
use App\Http\Controllers\LevelApiController;
use App\Http\Controllers\WorkflowApiController;
use App\Http\Controllers\RequestTypeApiController;
use App\Http\Controllers\ApproverApiController;
use App\Http\Controllers\ConditionalWorkflowApiController;
use App\Http\Controllers\ConditionTypeApiController;
use App\Http\Controllers\WorkflowAutomationApiController;
use App\Http\Controllers\CustomCountryApiController;
use App\Http\Controllers\CustomStateApiController;
use App\Http\Controllers\CustomCityApiController;
use App\Http\Controllers\ShiftPolicyApiController;
use App\Http\Controllers\ShiftPolicyWeeklyScheduleApiController;
use App\Http\Controllers\CheckinPolicyApiController;
use App\Http\Controllers\CheckinPolicyOnDutyTypeApiController;
use App\Http\Controllers\LeaveTypeBasicConfigurationApiController;
use App\Http\Controllers\LeaveTypeFullConfigurationApiController;
use App\Http\Controllers\LeavePolicyApiController;
use App\Http\Controllers\EmployeeProfilePhotoApiController;
use App\Http\Controllers\PayComponentApiController;
use App\Http\Controllers\ComponentTypeApiController;
use App\Http\Controllers\PaygroupConfigurationApiController;
use App\Http\Controllers\FormulaBuilderApiController;
use App\Http\Controllers\EmployeeSalaryStructureApiController;
use App\Http\Controllers\AttendanceApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// User Login Routes
Route::post('/userlogin/register', [UserLoginApiController::class, 'register']);
Route::post('/userlogin/login', [UserLoginApiController::class, 'login']);
Route::get('/userlogin/userslist', [UserLoginApiController::class, 'index']);
Route::put('/users/update/{corp_id}/{email_id}', [UserLoginApiController::class, 'update']);
Route::get('/users/{corp_id}/{email_id}', [UserLoginApiController::class, 'show']);
Route::get('/user/check-details/{corp_id}/{email_id}', [UserLoginApiController::class, 'checkUserDetails']); // New route added

// Company Details Routes
Route::post('/company/register_company_details', [CompanyDetailsApiController::class, 'register']);
Route::get('/company/details/{corp_id}', [CompanyDetailsApiController::class, 'show']);
Route::put('/company/update/{company_id}/{corp_id}', [CompanyDetailsApiController::class, 'update']);
Route::delete('/company/delete/{company_id}/{corp_id}', [CompanyDetailsApiController::class, 'destroy']);
Route::get('/company/name/{corp_id}', [CompanyDetailsApiController::class, 'getCompanyNameByCorpId']); // New route added

// Business Unit Routes
Route::post('/business_unit/add', [BusinessUnitApiController::class, 'store']);
Route::put('/business_unit/update/{corp_id}/{business_unit_id}', [BusinessUnitApiController::class, 'update']);
Route::delete('/business_unit/delete/{corp_id}/{business_unit_id}', [BusinessUnitApiController::class, 'destroy']);

// Fetch all business units by corp_id
Route::get('/business_unit/all/{corp_id}', [BusinessUnitApiController::class, 'getAllByCorpId']);

// Department Routes
Route::post('/department/add', [DepartmentApiController::class, 'store']);
Route::put('/department/update/{department_id}', [DepartmentApiController::class, 'update']);
Route::delete('/department/delete/{department_id}', [DepartmentApiController::class, 'destroy']);

// Fetch all departments by corp_id
Route::get('/department/all/{corp_id}', [DepartmentApiController::class, 'getAllByCorpId']);

// Location Routes
// Country
Route::post('/location/add-country', [LocationApiController::class, 'addCountry']);
Route::delete('/location/delete-country/{corp_id}/{country_id}', [LocationApiController::class, 'deleteCountry']);
Route::get('/location/countries/{corp_id}', [LocationApiController::class, 'getAllCountries']);

// State
Route::post('/location/add-state', [LocationApiController::class, 'addState']);
Route::delete('/location/delete-state/{corp_id}/{state_id}', [LocationApiController::class, 'deleteState']);
Route::get('/location/states/{corp_id}/{country_id}', [LocationApiController::class, 'getStates']);

// City
Route::post('/location/add-city', [LocationApiController::class, 'addCity']);
Route::delete('/location/delete-city/{corp_id}/{city_id}', [LocationApiController::class, 'deleteCity']);
Route::get('/location/cities/{corp_id}/{state_id}', [LocationApiController::class, 'getCities']);

// Industry Routes
Route::post('/industry/add', [IndustryApiController::class, 'store']);
Route::delete('/industry/delete/{corp_id}/{industry_id}', [IndustryApiController::class, 'destroy']);
Route::get('/industry/all/{corp_id}', [IndustryApiController::class, 'getAllByCorpId']);

// Currency
Route::post('/currency/add', [CurrencyApiController::class, 'store']);
Route::get('/currency/all/{corp_id}', [CurrencyApiController::class, 'index']);
Route::delete('/currency/delete/{corp_id}', [CurrencyApiController::class, 'destroy']); // Pass id or name in body

// Sub-Department Routes
Route::post('/subdepartment/add', [SubDepartmentApiController::class, 'store']);
Route::get('/subdepartment/all/{corp_id}', [SubDepartmentApiController::class, 'getByCorpId']);
Route::put('/subdepartment/update/{sub_dept_id}', [SubDepartmentApiController::class, 'update']);
Route::delete('/subdepartment/delete/{corp_id}/{sub_dept_id}', [SubDepartmentApiController::class, 'deleteByCorpIdAndSubDeptId']);

// Designation Routes
Route::post('/designation/add', [DesignationApiController::class, 'store']);
Route::get('/designation/all/{corp_id}', [DesignationApiController::class, 'getByCorpId']);
Route::put('/designation/update/{id}/{corp_id}', [DesignationApiController::class, 'update']);
Route::delete('/designation/delete/{corp_id}/{id}', [DesignationApiController::class, 'destroy']);

// Grade Routes
Route::post('/grade/add', [GradeApiController::class, 'store']);
Route::get('/grade/all/{corp_id}', [GradeApiController::class, 'getByCorpId']);
Route::delete('/grade/delete/{corp_id}/{grade_id}', [GradeApiController::class, 'destroy']);

// Document Type Routes
Route::post('/document_type/add', [DocumentTypeApiController::class, 'store']);
Route::get('/document_type/all/{corp_id}', [DocumentTypeApiController::class, 'getByCorpId']);
Route::delete('/document_type/delete/{corp_id}/{id}', [DocumentTypeApiController::class, 'destroy']);

// Document Form List Routes
Route::post('/document_form_list/add', [DocumentFormListApiController::class, 'store']);
Route::get('/document_form_list/all/{corp_id}', [DocumentFormListApiController::class, 'getByCorpId']);
Route::delete('/document_form_list/delete/{corp_id}/{id}', [DocumentFormListApiController::class, 'destroy']);

// Document Field List Routes
Route::post('/document_field_list/add', [DocumentFieldListApiController::class, 'store']);
Route::get('/document_field_list/all/{corp_id}', [DocumentFieldListApiController::class, 'getByCorpId']);
Route::delete('/document_field_list/delete/{corp_id}/{id}', [DocumentFieldListApiController::class, 'destroy']);

// Document Routes
Route::post('/document/add', [DocumentApiController::class, 'store']);
Route::get('/document/all/{corp_id}', [DocumentApiController::class, 'getByCorpId']);
Route::delete('/document/delete/{corp_id}/{id}', [DocumentApiController::class, 'destroy']);
Route::post('/document/update/{corp_id}/{id}', [DocumentApiController::class, 'update']);

// Qualification Routes
Route::post('/qualification/add', [QualificationApiController::class, 'store']);
Route::get('/qualification/all/{corp_id}', [QualificationApiController::class, 'getByCorpId']);
Route::delete('/qualification/delete/{corp_id}/{id}', [QualificationApiController::class, 'destroy']);

// Specialization Routes
Route::post('/specialization/add', [SpecializationApiController::class, 'store']);
Route::delete('/specialization/delete/{corp_id}/{id}', [SpecializationApiController::class, 'destroy']);
Route::get('/specialization/qualifications/{corp_id}', [SpecializationApiController::class, 'getQualificationsWithSpecializationCount']);

// Blacklist Routes
Route::post('/blacklist/add', [BlacklistApiController::class, 'store']);
Route::delete('/blacklist/delete/{corp_id}/{id}', [BlacklistApiController::class, 'destroy']);
Route::get('/blacklist/all/{corp_id}', [BlacklistApiController::class, 'getByCorpId']);

// Job Function Routes
Route::post('/jobfunction/add', [JobFunctionApiController::class, 'store']);
Route::delete('/jobfunction/delete/{corp_id}/{id}', [JobFunctionApiController::class, 'destroy']);
Route::get('/jobfunction/all/{corp_id}', [JobFunctionApiController::class, 'getByCorpId']);

// Skill Routes
Route::post('/skill/add', [SkillApiController::class, 'store']);
Route::delete('/skill/delete/{corp_id}/{id}', [SkillApiController::class, 'destroy']);
Route::get('/skill/all/{corp_id}', [SkillApiController::class, 'getByCorpId']);

// Skill Proficiency Routes
Route::post('/skillproficiency/add', [SkillProficiencyApiController::class, 'store']);
Route::delete('/skillproficiency/delete/{corp_id}/{id}', [SkillProficiencyApiController::class, 'destroy']);
Route::get('/skillproficiency/all/{corp_id}', [SkillProficiencyApiController::class, 'getByCorpId']);

// Bank Routes
Route::post('/bank/add', [BankApiController::class, 'store']);
Route::delete('/bank/delete/{corp_id}/{id}', [BankApiController::class, 'destroy']);
Route::get('/bank/all/{corp_id}', [BankApiController::class, 'getByCorpId']);

// Bank Challan Report Routes
Route::post('/bankchallanreport/add', [BankChallanReportApiController::class, 'store']);
Route::delete('/bankchallanreport/delete/{corp_id}/{id}', [BankChallanReportApiController::class, 'destroy']);
Route::get('/bankchallanreport/all/{corp_id}', [BankChallanReportApiController::class, 'getByCorpId']);

// Payment Bank Routes
Route::post('/paymentbank/add', [PaymentBankApiController::class, 'store']);
Route::get('/paymentbank/all/{corp_id}', [PaymentBankApiController::class, 'getByCorpId']);
Route::put('/paymentbank/update/{corp_id}/{id}', [PaymentBankApiController::class, 'update']);
Route::delete('/paymentbank/delete/{corp_id}/{id}', [PaymentBankApiController::class, 'destroy']);

// Vendor Routes
Route::post('/vendor/add', [VendorApiController::class, 'store']);
Route::put('/vendor/update/{corp_id}/{id}', [VendorApiController::class, 'update']);
Route::delete('/vendor/delete/{corp_id}/{id}', [VendorApiController::class, 'destroy']);
Route::get('/vendor/all/{corp_id}', [VendorApiController::class, 'getByCorpId']);

// Customer Routes
Route::post('/customer/add', [CustomerDetailApiController::class, 'store']);
Route::get('/customer/all/{corp_id}', [CustomerDetailApiController::class, 'getByCorpId']);
Route::put('/customer/update/{corp_id}/{id}', [CustomerDetailApiController::class, 'update']);
Route::delete('/customer/delete/{corp_id}/{id}', [CustomerDetailApiController::class, 'destroy']);

// Profile Access Setting Routes
Route::post('/profileaccess/add', [ProfileAccessSettingApiController::class, 'store']);
Route::get('/profileaccess/all/{corp_id}', [ProfileAccessSettingApiController::class, 'getByCorpId']);
Route::put('/profileaccess/update/{corp_id}/{id}', [ProfileAccessSettingApiController::class, 'update']);
Route::delete('/profileaccess/delete/{corp_id}/{id}', [ProfileAccessSettingApiController::class, 'destroy']);

// Social Profile Access Routes
Route::post('/socialprofileaccess/add', [SocialProfileAccessApiController::class, 'store']);
Route::get('/socialprofileaccess/all/{corp_id}', [SocialProfileAccessApiController::class, 'getByCorpId']);
Route::put('/socialprofileaccess/update/{corp_id}/{id}', [SocialProfileAccessApiController::class, 'update']);
Route::delete('/socialprofileaccess/delete/{corp_id}/{id}', [SocialProfileAccessApiController::class, 'destroy']);

// Employment Details APIs
Route::post('/employment/add', [EmploymentDetailApiController::class, 'store']);
Route::put('/employment/update/{corp_id}/{EmpCode}', [EmploymentDetailApiController::class, 'update']);
Route::delete('/employment/delete/{corp_id}/{EmpCode}', [EmploymentDetailApiController::class, 'destroy']);
Route::get('/employment/next-empcode/{corp_id}', [EmploymentDetailApiController::class, 'getNextEmpCode']);
Route::get('/employment/summary/{corp_id}/{company_name}', [EmploymentDetailApiController::class, 'fetchEmploymentAndEmployeeDetails']);
Route::post('/employment/check-empcode', [EmploymentDetailApiController::class, 'checkEmpCodeExists']);
Route::get('/employment/summary-by-corp/{corpid}', [EmploymentDetailApiController::class, 'summaryByCorpId']);
Route::get('/employment/{corp_id}/{EmpCode}', [EmploymentDetailApiController::class, 'show']);





// Employee Details APIs
Route::post('/employee/add', [EmployeeDetailApiController::class, 'store']);
Route::put('/employee/update/{corp_id}/{EmpCode}/{id}', [EmployeeDetailApiController::class, 'update']);
Route::delete('/employee/delete/{corp_id}/{EmpCode}/{id}', [EmployeeDetailApiController::class, 'destroy']);
Route::post('/employee/check-empcode', [EmployeeDetailApiController::class, 'checkEmpCodeExists']);
Route::get('/employee/empcode-fullname/{corp_id}', [EmployeeDetailApiController::class, 'getEmpCodeWithFullNameByCorpId']);
Route::get('/employee/{corp_id}/{EmpCode}', [EmployeeDetailApiController::class, 'show']);
Route::get('/employee-details/by-corp/{corp_id}', [EmployeeDetailApiController::class, 'getByCorpId']);


// Employee Statutory Details APIs
Route::post('/statutory/add', [EmployeeStatutoryDetailApiController::class, 'store']);
Route::put('/statutory/update/{corp_id}/{EmpCode}/{id}', [EmployeeStatutoryDetailApiController::class, 'update']);
Route::delete('/statutory/delete/{corp_id}/{EmpCode}/{id}', [EmployeeStatutoryDetailApiController::class, 'destroy']);
Route::get('/statutory/exists/{corp_id}/{EmpCode}', [EmployeeStatutoryDetailApiController::class, 'exists']);
Route::get('/statutory/{corp_id}/{EmpCode}', [EmployeeStatutoryDetailApiController::class, 'show']);


// Family Details APIs
Route::post('/family/add', [FamilyDetailApiController::class, 'store']);
Route::put('/family/update/{corp_id}/{EmpCode}', [FamilyDetailApiController::class, 'update']);
Route::delete('/family/delete/{corp_id}/{EmpCode}', [FamilyDetailApiController::class, 'destroy']);
Route::get('/family/exists/{corp_id}/{EmpCode}', [FamilyDetailApiController::class, 'exists']);
Route::get('/family/{corp_id}/{EmpCode}', [FamilyDetailApiController::class, 'show']);

// Child Details APIs
Route::post('/child/add', [ChildApiController::class, 'store']);
Route::put('/child/update/{corp_id}/{EmpCode}', [ChildApiController::class, 'update']);
Route::delete('/child/delete/{corp_id}/{EmpCode}/{id}', [ChildApiController::class, 'destroy']);
Route::get('/child/{corp_id}/{EmpCode}', [ChildApiController::class, 'show']);

// Work Experience Routes
Route::post('/workexperience/add', [EmployeeWorkExperienceApiController::class, 'store']);
Route::put('/workexperience/update/{corp_id}/{empcode}/{id}', [EmployeeWorkExperienceApiController::class, 'update']);
Route::delete('/workexperience/delete/{corp_id}/{empcode}/{id}', [EmployeeWorkExperienceApiController::class, 'destroy']);
Route::get('/workexperience/{corp_id}/{empcode}', [EmployeeWorkExperienceApiController::class, 'show']);

// Relation Routes
Route::post('/relation/add', [RelationApiController::class, 'store']);
Route::delete('/relation/delete/{corp_id}', [RelationApiController::class, 'destroy']);
Route::get('/relation/all/{corp_id}', [RelationApiController::class, 'getByCorpId']);

// Education Routes
Route::post('/education/add', [EmployeeEducationApiController::class, 'store']);
Route::put('/education/update/{corp_id}/{empcode}/{id}', [EmployeeEducationApiController::class, 'update']);
Route::delete('/education/delete/{corp_id}/{empcode}/{id}', [EmployeeEducationApiController::class, 'destroy']);
Route::get('/education/{corp_id}/{empcode}', [EmployeeEducationApiController::class, 'show']);

// Employee Skills Routes
Route::post('/employee/skills/add', [EmployeeSkillApiController::class, 'store']);
Route::put('/employee/skills/update/{corp_id}/{empcode}/{id}', [EmployeeSkillApiController::class, 'update']);
Route::delete('/employee/skills/delete/{corp_id}/{empcode}/{id}', [EmployeeSkillApiController::class, 'destroy']);
Route::get('/employee/skills/{corp_id}/{empcode}', [EmployeeSkillApiController::class, 'show']);

// Employee Insurance Policy Routes
Route::post('/employee/insurance-policy/add', [EmployeeInsurancePolicyApiController::class, 'store']);
Route::put('/employee/insurance-policy/update/{corp_id}/{empcode}/{id}', [EmployeeInsurancePolicyApiController::class, 'update']);
Route::delete('/employee/insurance-policy/delete/{corp_id}/{empcode}/{id}', [EmployeeInsurancePolicyApiController::class, 'destroy']);
Route::get('/employee/insurance-policy/{corp_id}/{empcode}', [EmployeeInsurancePolicyApiController::class, 'show']);

// Employee Nominee Details Routes
Route::post('/employee/nominee/add', [EmployeeNomineeDetailApiController::class, 'store']);
Route::put('/employee/nominee/update/{corp_id}/{empcode}/{id}', [EmployeeNomineeDetailApiController::class, 'update']);
Route::delete('/employee/nominee/delete/{corp_id}/{empcode}/{id}', [EmployeeNomineeDetailApiController::class, 'destroy']);
Route::get('/employee/nominee/{corp_id}/{empcode}', [EmployeeNomineeDetailApiController::class, 'show']);

// Employee Bank Details Routes
Route::post('/employee/bank/add', [EmployeeBankDetailApiController::class, 'store']);
Route::put('/employee/bank/update/{corp_id}/{empcode}', [EmployeeBankDetailApiController::class, 'update']);
Route::delete('/employee/bank/delete/{corp_id}/{empcode}', [EmployeeBankDetailApiController::class, 'destroy']);
Route::post('/employee/bank/check-empcode', [EmployeeBankDetailApiController::class, 'checkEmpCodeExists']);
Route::get('/employee/bank/{corp_id}/{empcode}', [EmployeeBankDetailApiController::class, 'show']);


// Region APIs
Route::post('/region/add', [RegionApiController::class, 'store']);
Route::put('/region/update/{corp_id}/{id}', [RegionApiController::class, 'update']);
Route::delete('/region/delete/{corp_id}/{id}', [RegionApiController::class, 'destroy']);
Route::get('/region/all/{corp_id}', [RegionApiController::class, 'getByCorpId']);

// Branch APIs
Route::post('/branch/add', [BranchApiController::class, 'store']);
Route::put('/branch/update/{corp_id}/{id}', [BranchApiController::class, 'update']);
Route::delete('/branch/delete/{corp_id}/{id}', [BranchApiController::class, 'destroy']);
Route::get('/branch/all/{corp_id}', [BranchApiController::class, 'getByCorpId']);

// Sub-Branch APIs
Route::post('/subbranch/add', [SubBranchApiController::class, 'store']);
Route::put('/subbranch/update/{corp_id}/{id}', [SubBranchApiController::class, 'update']);
Route::delete('/subbranch/delete/{corp_id}/{id}', [SubBranchApiController::class, 'destroy']);
Route::get('/subbranch/all/{corp_id}', [SubBranchApiController::class, 'getByCorpId']);

// Employment Type Routes
Route::post('/employment-type/add', [EmploymentTypeApiController::class, 'store']);
Route::put('/employment-type/update/{corp_id}/{id}', [EmploymentTypeApiController::class, 'update']);
Route::delete('/employment-type/delete/{corp_id}/{id}', [EmploymentTypeApiController::class, 'destroy']);
Route::get('/employment-type/all/{corp_id}', [EmploymentTypeApiController::class, 'getByCorpId']);

// Employment Status Routes
Route::post('/employment-status/add', [EmploymentStatusApiController::class, 'store']);
Route::put('/employment-status/update/{corp_id}/{id}', [EmploymentStatusApiController::class, 'update']);
Route::delete('/employment-status/delete/{corp_id}/{id}', [EmploymentStatusApiController::class, 'destroy']);
Route::get('/employment-status/all/{corp_id}', [EmploymentStatusApiController::class, 'getByCorpId']);

// Confirmation Status Routes
Route::post('/confirmation-status/add', [ConfirmationStatusApiController::class, 'store']);
Route::put('/confirmation-status/update/{corp_id}/{id}', [ConfirmationStatusApiController::class, 'update']);
Route::delete('/confirmation-status/delete/{corp_id}/{id}', [ConfirmationStatusApiController::class, 'destroy']);
Route::get('/confirmation-status/all/{corp_id}', [ConfirmationStatusApiController::class, 'getByCorpId']);

// Corporate ID Routes
Route::post('/corporateid/add', [CorporateIdApiController::class, 'store']);
Route::delete('/corporateid/delete/{corp_id_name}', [CorporateIdApiController::class, 'destroy']);
Route::get('/corporateid/all', [CorporateIdApiController::class, 'getAll']);

// Role Routes
Route::post('/role/add', [RoleApiController::class, 'store']);
Route::get('/role/all/{corp_id}', [RoleApiController::class, 'getByCorpId']);
Route::delete('/role/delete/{corp_id}/{role_name}', [RoleApiController::class, 'destroy']);

// Employee Assigned Role Routes
Route::post('/employee-assigned-role/add', [EmployeeAssignedRoleApiController::class, 'store']);
Route::put('/employee-assigned-role/update/{corp_id}/{id}', [EmployeeAssignedRoleApiController::class, 'update']);
Route::delete('/employee-assigned-role/delete/{corp_id}/{id}', [EmployeeAssignedRoleApiController::class, 'destroy']);
Route::get('/employee-assigned-role/all/{corp_id}', [EmployeeAssignedRoleApiController::class, 'getByCorpId']);

// Level Routes
Route::post('/level/add', [LevelApiController::class, 'store']);
Route::get('/level/all/{corp_id}', [LevelApiController::class, 'getByCorpId']);
Route::delete('/level/delete/{corp_id}/{id}', [LevelApiController::class, 'destroy']);

// Workflow Routes
Route::post('/workflow/add', [WorkflowApiController::class, 'store']);
Route::delete('/workflow/delete/{corpid}', [WorkflowApiController::class, 'destroyByCorpId']);
Route::get('/workflow/{corpid}', [WorkflowApiController::class, 'getByCorpId']);

// Request Type Routes
Route::post('/requesttype/add', [RequestTypeApiController::class, 'store']);
Route::delete('/requesttype/delete/{corp_id}', [RequestTypeApiController::class, 'destroyByCorpId']);
Route::get('/requesttype/{corp_id}', [RequestTypeApiController::class, 'getByCorpId']);

// Approver Routes
Route::post('/approver/add', [ApproverApiController::class, 'store']);
Route::delete('/approver/delete/{puid}', [ApproverApiController::class, 'destroy']);
Route::post('/approver/fetch', [ApproverApiController::class, 'fetch']); // pass puid in body to fetch by puid
Route::put('/approver/update/{corp_id}/{puid}', [ApproverApiController::class, 'update']);

// Conditional Workflow Routes
Route::post('/conditional-workflow/add', [ConditionalWorkflowApiController::class, 'store']);
Route::delete('/conditional-workflow/delete/{puid}', [ConditionalWorkflowApiController::class, 'destroy']);
Route::put('/conditional-workflow/update/{puid}', [ConditionalWorkflowApiController::class, 'update']);
Route::post('/conditional-workflow/fetch', [ConditionalWorkflowApiController::class, 'fetch']);

// Condition Type Routes
Route::post('/condition-type/add', [ConditionTypeApiController::class, 'store']);
Route::get('/condition-type/all/{corp_id}', [ConditionTypeApiController::class, 'getByCorpId']);
Route::delete('/condition-type/delete/{corp_id}/{id}', [ConditionTypeApiController::class, 'destroy']);

// Workflow Automation Routes
Route::post('/workflow-automation/add', [WorkflowAutomationApiController::class, 'store']);
Route::post('/workflow-automation/fetch', [WorkflowAutomationApiController::class, 'fetchAutomationData']);
Route::get('/workflow-automation/generate-public-uid', [WorkflowAutomationApiController::class, 'generatePublicUid']);
Route::delete('/workflow-automation/delete/{puid}', [WorkflowAutomationApiController::class, 'deleteByPuid']);
Route::put('/workflow-automation/update/{puid}', [WorkflowAutomationApiController::class, 'updateByPuid']);

// Custom Country Routes
Route::post('/custom-country/add', [CustomCountryApiController::class, 'store']);
Route::get('/custom-country/{corp_id}', [CustomCountryApiController::class, 'getByCorpId']);
Route::delete('/custom-country/delete/{corp_id}/{country_name}', [CustomCountryApiController::class, 'destroyByCorpIdAndCountryName']);

// Custom State Routes
Route::post('/custom-state/add', [CustomStateApiController::class, 'store']);
Route::get('/custom-state/{corp_id}', [CustomStateApiController::class, 'getByCorpId']);
Route::delete('/custom-state/delete/{corp_id}/{state_name}', [CustomStateApiController::class, 'destroyByCorpIdAndStateName']);

// Custom City Routes
Route::post('/custom-city/add', [CustomCityApiController::class, 'store']);
Route::get('/custom-city/{corp_id}', [CustomCityApiController::class, 'getByCorpId']);
Route::delete('/custom-city/delete/{corp_id}/{city_name}', [CustomCityApiController::class, 'destroyByCorpIdAndCityName']);

// Shift Policy Routes
Route::post('/shiftpolicy/add', [ShiftPolicyApiController::class, 'store']);
Route::put('/shiftpolicy/update/{corp_id}/{puid}', [ShiftPolicyApiController::class, 'update']);
Route::get('/shiftpolicy/all/{corp_id}', [ShiftPolicyApiController::class, 'getAllByCorpId']);
Route::delete('/shiftpolicy/delete/{puid}', [ShiftPolicyApiController::class, 'deleteByPuid']);

// Shift Policy Weekly Schedule Routes
Route::post('/shift-policy-weekly-schedule/add', [ShiftPolicyWeeklyScheduleApiController::class, 'store']);
Route::put('/shift-policy-weekly-schedule/update/{puid}/{id}', [ShiftPolicyWeeklyScheduleApiController::class, 'update']);
Route::delete('/shift-policy-weekly-schedule/delete/{puid}/{id}', [ShiftPolicyWeeklyScheduleApiController::class, 'destroyByPuidAndId']);
Route::delete('/shift-policy-weekly-schedule/delete/{puid}', [ShiftPolicyWeeklyScheduleApiController::class, 'destroyByPuid']);
Route::get('/shift-policy-weekly-schedule/{puid}', [ShiftPolicyWeeklyScheduleApiController::class, 'fetchByPuid']);

// Check-in Policy Routes
Route::post('/checkin-policy/add', [CheckinPolicyApiController::class, 'store']);
Route::delete('/checkin-policy/delete/{puid}', [CheckinPolicyApiController::class, 'destroy']);
Route::put('/checkin-policy/update/{puid}', [CheckinPolicyApiController::class, 'update']);
Route::get('/checkin-policy/all/{corp_id}', [CheckinPolicyApiController::class, 'getByCorpId']);

// Check-in Policy On Duty Type Routes
Route::post('/checkin-policy-on-duty-type/add', [CheckinPolicyOnDutyTypeApiController::class, 'store']);
Route::delete('/checkin-policy-on-duty-type/delete/{puid}', [CheckinPolicyOnDutyTypeApiController::class, 'destroy']);
Route::get('/checkin-policy-on-duty-type/{puid}', [CheckinPolicyOnDutyTypeApiController::class, 'fetchByPuid']);

// Leave Type Basic Configuration Routes
Route::post('/leave-type-basic-configuration/add', [LeaveTypeBasicConfigurationApiController::class, 'store']);
Route::put('/leave-type-basic-configuration/update/{puid}', [LeaveTypeBasicConfigurationApiController::class, 'update']);
Route::delete('/leave-type-basic-configuration/delete/{puid}', [LeaveTypeBasicConfigurationApiController::class, 'destroy']);
Route::get('/leave-type-basic-configuration/all/{corpid}', [LeaveTypeBasicConfigurationApiController::class, 'fetchByCorpid']);

// Leave Type Full Configuration Routes
Route::post('/leave-type-full-configuration/add', [LeaveTypeFullConfigurationApiController::class, 'store']);
Route::put('/leave-type-full-configuration/update/{puid}', [LeaveTypeFullConfigurationApiController::class, 'update']);
Route::delete('/leave-type-full-configuration/delete/{puid}', [LeaveTypeFullConfigurationApiController::class, 'destroy']);
Route::get('/leave-type-full-configuration/all/{corpid}', [LeaveTypeFullConfigurationApiController::class, 'fetchByCorpid']);
Route::get('/leave-type-full-configuration/{puid}', [LeaveTypeFullConfigurationApiController::class, 'fetchByPuid']);

// Leave Policy Routes
Route::post('/leave-policy/add', [LeavePolicyApiController::class, 'store']);
Route::put('/leave-policy/update/{puid}', [LeavePolicyApiController::class, 'update']);
Route::delete('/leave-policy/delete/{puid}', [LeavePolicyApiController::class, 'destroy']);
Route::get('/leave-policy/by-corpid/{corpid}', [LeavePolicyApiController::class, 'getByCorpid']);

// Employee Profile Photo Routes
Route::post('/employee-profile-photo/add', [EmployeeProfilePhotoApiController::class, 'store']);
Route::post('/employee-profile-photo/update/{corp_id}/{emp_code}', [EmployeeProfilePhotoApiController::class, 'update']);
Route::get('/employee-profile-photo/{corp_id}/{emp_code}', [EmployeeProfilePhotoApiController::class, 'fetch']);
Route::delete('/employee-profile-photo/delete/{corp_id}/{emp_code}', [EmployeeProfilePhotoApiController::class, 'destroy']);

// Pay Component Routes
Route::post('/paycomponent/add-or-update', [PayComponentApiController::class, 'storeOrUpdate']);
Route::get('/paycomponent/all/{corpId}', [PayComponentApiController::class, 'getByCorpId']);
Route::get('/paycomponent/{puid}', [PayComponentApiController::class, 'getByPuid']);
Route::delete('/paycomponent/delete/{puid}', [PayComponentApiController::class, 'destroy']);

// Component Type Routes
Route::post('/component-type/add-or-update', [ComponentTypeApiController::class, 'storeOrUpdate']);
Route::get('/component-type/all/{corpId}', [ComponentTypeApiController::class, 'getByCorpId']);
Route::delete('/component-type/delete/{corpId}/{id}', [ComponentTypeApiController::class, 'destroy']);

// Paygroup Configuration Routes
Route::post('/paygroup-configuration/add-or-update', [PaygroupConfigurationApiController::class, 'storeOrUpdate']);
Route::get('/paygroup-configuration/by-corpid/{corpId}', [PaygroupConfigurationApiController::class, 'fetchByCorpId']);
Route::get('/paygroup-configuration/{puid}', [PaygroupConfigurationApiController::class, 'fetchByPuid']);
Route::delete('/paygroup-configuration/delete/{puid}', [PaygroupConfigurationApiController::class, 'destroy']);
Route::get('/paygroup/included-components/{puid}', [PaygroupConfigurationApiController::class, 'fetchIncludedComponents']);
Route::get('/paygroup-configuration/groupnames/by-employment-details/{corp_id}/{EmpCode}', [PaygroupConfigurationApiController::class, 'fetchGroupNamesByEmploymentDetails']);
Route::get('/paygroup-configuration/gross/{groupName}/{basicSalary}', [PaygroupConfigurationApiController::class, 'fetchGrossByGroupName']);

// Formula Builder Routes
Route::post('/formula-builder/add-or-update', [FormulaBuilderApiController::class, 'storeOrUpdate']);
Route::get('/formula-builder/{corpId}/{componentGroupName}', [FormulaBuilderApiController::class, 'fetchByCorpIdAndGroup']);
Route::delete('/formula-builder/delete/{puid}/{paygroupPuid}', [FormulaBuilderApiController::class, 'destroy']);

// Other Benefits Allowances Route
Route::get('/paygroup-configuration/other-benefits-allowances/{groupName}/{corpId}', [PaygroupConfigurationApiController::class, 'fetchOtherBenefitsAllowances']);

// Deductions Route
Route::get('/paygroup-configuration/deductions/{groupName}/{basicSalary}', [PaygroupConfigurationApiController::class, 'fetchDeductionsByGroupName']);

// Save Payroll Data Route
Route::post('/payroll-data/save', [PaygroupConfigurationApiController::class, 'savePayrollData']); //--not required

// Employee Salary Structure Routes
Route::post('/employee-salary-structure/add-or-update', [EmployeeSalaryStructureApiController::class, 'storeOrUpdate']);
Route::get('/employee-salary-structure/{empCode}/{companyName}/{corpId}', [EmployeeSalaryStructureApiController::class, 'fetchByEmpDetails']);
Route::delete('/employee-salary-structure/delete/{puid}', [EmployeeSalaryStructureApiController::class, 'destroy']);

// Attendance Routes
Route::post('/attendance/check', [AttendanceApiController::class, 'checkInOrOut']);
Route::get('/attendance/today/{corpId}/{userName}/{empCode}/{companyName}', [AttendanceApiController::class, 'fetchTodayAttendance']);
Route::get('/attendance/check-exists/{corpId}/{empCode}/{companyName}', [AttendanceApiController::class, 'checkTodayAttendanceExists']);
Route::get('/attendance/history/{corpId}/{filter?}', [AttendanceApiController::class, 'fetchAttendanceHistory']); // New route added
// Add this route for bulk insert
Route::post('/attendance/bulk-insert', [AttendanceApiController::class, 'bulkInsertAttendance']);

// Monthly Attendance Route
Route::get('/attendance/monthly', [AttendanceApiController::class, 'fetchMonthlyAttendance']);
Route::post('/attendance/monthly', [AttendanceApiController::class, 'fetchMonthlyAttendance']); // New POST route

// Get current month and year
Route::get('/attendance/current-month-year', [AttendanceApiController::class, 'getCurrentMonthYear']);
