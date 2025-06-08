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
Route::delete('/location/delete-country/{country_id}', [LocationApiController::class, 'deleteCountry']);
Route::get('/location/countries', [LocationApiController::class, 'getAllCountries']);

// State
Route::post('/location/add-state', [LocationApiController::class, 'addState']);
Route::delete('/location/delete-state/{state_id}', [LocationApiController::class, 'deleteState']);
Route::get('/location/states', [LocationApiController::class, 'getAllStates']);

// City
Route::post('/location/add-city', [LocationApiController::class, 'addCity']);
Route::delete('/location/delete-city/{city_id}', [LocationApiController::class, 'deleteCity']);
Route::get('/location/cities', [LocationApiController::class, 'getAllCities']);

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
Route::delete('/subdepartment/delete/{corp_id}', [SubDepartmentApiController::class, 'deleteByCorpId']);

// Designation Routes
Route::post('/designation/add', [DesignationApiController::class, 'store']);
Route::get('/designation/all/{corp_id}', [DesignationApiController::class, 'getByCorpId']);
Route::put('/designation/update/{id}/{corp_id}', [DesignationApiController::class, 'update']);
Route::delete('/designation/delete/{corp_id}', [DesignationApiController::class, 'deleteByCorpId']);

// Grade Routes
Route::post('/grade/add', [GradeApiController::class, 'store']);
Route::get('/grade/all/{corp_id}', [GradeApiController::class, 'getByCorpId']);

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
Route::put('/document/update/{corp_id}/{id}', [DocumentApiController::class, 'update']);

// Qualification Routes
Route::post('/qualification/add', [QualificationApiController::class, 'store']);
Route::get('/qualification/all/{corp_id}', [QualificationApiController::class, 'getByCorpId']);
Route::delete('/qualification/delete/{corp_id}/{id}', [QualificationApiController::class, 'destroy']);

// Specialization Routes
Route::post('/specialization/add', [SpecializationApiController::class, 'store']);
Route::delete('/specialization/delete/{corp_id}/{id}', [SpecializationApiController::class, 'destroy']);
Route::get('/specialization/qualifications/{corp_id}', [SpecializationApiController::class, 'getQualificationsWithSpecializationCount']);

