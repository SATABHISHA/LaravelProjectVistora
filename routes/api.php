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

