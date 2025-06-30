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

// Company Details Routes
Route::post('/company/register_company_details', [CompanyDetailsApiController::class, 'register']);
Route::get('/company/details/{corp_id}', [CompanyDetailsApiController::class, 'show']);
Route::put('/company/update/{company_id}/{corp_id}', [CompanyDetailsApiController::class, 'update']);
Route::delete('/company/delete/{company_id}/{corp_id}', [CompanyDetailsApiController::class, 'destroy']);

// Business Unit Routes
Route::post('/business_unit/add', [BusinessUnitApiController::class, 'store']);
Route::put('/business_unit/update/{business_unit_id}', [BusinessUnitApiController::class, 'update']);
Route::delete('/business_unit/delete/{business_unit_id}', [BusinessUnitApiController::class, 'destroy']);

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
Route::delete('/industry/delete/{industry_id}', [IndustryApiController::class, 'destroy']);
Route::get('/industry/all', [IndustryApiController::class, 'getAllIndustries']);

// Currency
Route::post('/currency/add', [CurrencyApiController::class, 'store']);
Route::get('/currency/all', [CurrencyApiController::class, 'index']);
Route::delete('/currency/delete', [CurrencyApiController::class, 'destroy']); // Pass id or name in body or query

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
Route::get('/employment/{corp_id}/{EmpCode}', [EmploymentDetailApiController::class, 'show']);

// Employee Details APIs
Route::post('/employee/add', [EmployeeDetailApiController::class, 'store']);
Route::put('/employee/update/{corp_id}/{EmpCode}/{id}', [EmployeeDetailApiController::class, 'update']);
Route::delete('/employee/delete/{corp_id}/{EmpCode}/{id}', [EmployeeDetailApiController::class, 'destroy']);
Route::get('/employee/{corp_id}/{EmpCode}', [EmployeeDetailApiController::class, 'show']);

// Employee Statutory Details APIs
Route::post('/statutory/add', [EmployeeStatutoryDetailApiController::class, 'store']);
Route::put('/statutory/update/{corp_id}/{EmpCode}/{id}', [EmployeeStatutoryDetailApiController::class, 'update']);
Route::delete('/statutory/delete/{corp_id}/{EmpCode}/{id}', [EmployeeStatutoryDetailApiController::class, 'destroy']);
Route::get('/statutory/{corp_id}/{EmpCode}', [EmployeeStatutoryDetailApiController::class, 'show']);

// Family Details APIs
Route::post('/family/add', [FamilyDetailApiController::class, 'store']);
Route::put('/family/update/{corp_id}/{EmpCode}', [FamilyDetailApiController::class, 'update']);
Route::delete('/family/delete/{corp_id}/{EmpCode}', [FamilyDetailApiController::class, 'destroy']);
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

