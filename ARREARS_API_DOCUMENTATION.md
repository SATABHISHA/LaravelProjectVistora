# Salary Sheet with Arrears Export API - Complete Documentation

## Overview
A new API endpoint has been created to export salary sheets with arrears calculations. This API calculates arrears from the `arrearWithEffectFrom` date in the `employee_salary_structures` table and includes comprehensive breakups of all salary components.

## API Endpoint

**Route:** `GET /api/payroll/export-with-arrears`

**Controller Method:** `EmployeePayrollSalaryProcessApiController@exportPayrollWithArrears`

## Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corpId | string | Yes | Corporate ID |
| companyName | string | Yes | Company name |
| year | string | Yes | Year (e.g., "2025") |
| month | string | Yes | Month (e.g., "November") |
| subBranch | string | No | Sub-branch filter |

## Example Request

```
GET /api/payroll/export-with-arrears?corpId=test&companyName=Nippo&year=2025&month=November
```

## Features

### 1. Arrears Calculation
- Automatically calculates arrears from the `arrearWithEffectFrom` column
- Calculates the number of months between effective date and current month
- Example: If arrears effective from August 2025 and current month is November 2025, arrears for 3 months (Aug, Sep, Oct)

### 2. Excel Export Columns

#### Static Information Columns:
1. **S.No.** - Serial number
2. **Employee Code** - Employee code
3. **Employee Name** - Full name of employee
4. **Designation** - Employee designation
5. **Arrear Status** - Status: "Arrears Due", "No Arrears", or "No Revision"
6. **Arrears From** - Effective date of arrears (formatted as "Aug 2025")
7. **Arrear Months** - Number of months for which arrears are due

#### Current Month Salary Columns:
- All gross components (e.g., Basic (Current), HRA (Current), etc.)
- **Current Monthly Gross** - Total of all current gross components
- All deduction components (e.g., PF (Current), ESI (Current), etc.)
- **Current Monthly Deductions** - Total of all current deductions

#### Arrears Breakup Columns:
- All gross components with arrears (e.g., Basic (Arrears), HRA (Arrears), etc.)
- **Total Gross Arrears** - Sum of all gross arrears
- All deduction components with arrears (e.g., PF (Arrears), ESI (Arrears), etc.)
- **Total Deduction Arrears** - Sum of all deduction arrears

#### Summary Columns:
- **Net Arrears Payable** - (Total Gross Arrears - Total Deduction Arrears)
- **Net Take Home (With Arrears)** - Current month net + Net Arrears Payable
- **Status** - Payroll status (Released/Initiated/etc.)

### 3. Excel File Features

#### Header Rows:
1. **Main Title:** "Salary Sheet with Arrears for [Month] [Year]"
2. **Company Info:** Company name
3. **Sub Branch Info:** Sub-branch (or "All SubBranches")
4. **Arrears Summary:**
   - Total employees
   - Employees with salary revision
   - Employees without revision
   - Total employees count

#### Formatting:
- Professional styling with alternating row colors
- Bold headers with background colors
- Totals row with distinct formatting
- Auto-sized columns for better readability
- Number formatting for all currency values

### 4. Response Scenarios

#### Scenario A: Arrears Found
- Returns Excel file with complete salary and arrears data
- Filename format: `SalarySheet_WithArrears_[Company]_[Month]_[Year]_[SubBranch].xlsx`

#### Scenario B: No Arrears Found
Returns JSON response:
```json
{
    "status": false,
    "message": "No arrears found for the current year. Salary revisions have not been initiated.",
    "details": {
        "total_employees": 10,
        "employees_without_salary_revision": 5,
        "employees_without_revision_details": [
            {
                "empCode": "EMP001",
                "empName": "John Doe"
            }
        ]
    }
}
```

## Implementation Details

### Files Created/Modified:

1. **New Export Class:** `app/Exports/PayrollArrearsExport.php`
   - Handles Excel generation with arrears data
   - Implements dynamic column headers
   - Includes comprehensive formatting

2. **Modified Controller:** `app/Http/Controllers/EmployeePayrollSalaryProcessApiController.php`
   - Added `exportPayrollWithArrears()` method
   - Imports `PayrollArrearsExport` class
   - Handles date format parsing (supports "20/8/2025" format)

3. **New Route:** Added in `routes/api.php`
   ```php
   Route::get('/payroll/export-with-arrears', [EmployeePayrollSalaryProcessApiController::class, 'exportPayrollWithArrears']);
   ```

### Database Tables Used:
1. **employee_payroll_salary_process** - Current month salary data
2. **employee_salary_structures** - Salary structure with `arrearWithEffectFrom` column
3. **employee_details** - Employee personal information
4. **employment_details** - Employment information (designation, department, etc.)

## Testing

### Test Data Setup:
The following test data was created:
- Corp ID: test
- Company: Nippo
- Employee: EMP001
- Salary Structure Year: 2025
- Arrears Effective From: 20/8/2025 (August 2025)
- Current Month: November 2025
- Expected Arrears: 3 months (August, September, October)

### Test Files Created:
1. **PHP Test Script:** `test_arrears_api.php`
   - Verifies database setup
   - Creates test payroll data if needed
   - Provides API test URL

2. **HTML Test Page:** `public/test-arrears-api.html`
   - Interactive test interface
   - Direct download link
   - Expected results documentation

### How to Test:

#### Method 1: Using Browser
1. Open: `http://localhost/LaravelProjectVistora/public/test-arrears-api.html`
2. Click "Download Salary Sheet with Arrears" button
3. Excel file will be downloaded

#### Method 2: Direct API Call
```
http://localhost/LaravelProjectVistora/public/api/payroll/export-with-arrears?corpId=test&companyName=Nippo&year=2025&month=November
```

#### Method 3: Using cURL (if available)
```bash
curl -O "http://localhost/LaravelProjectVistora/public/api/payroll/export-with-arrears?corpId=test&companyName=Nippo&year=2025&month=November"
```

## Business Logic

### Arrears Calculation Formula:
```
Monthly Component Value × Number of Arrear Months = Component Arrears
```

### Example:
If Basic Salary = ₹30,000/month and arrears are for 3 months:
- Basic (Arrears) = ₹30,000 × 3 = ₹90,000

### Net Arrears Payable:
```
Net Arrears = Total Gross Arrears - Total Deduction Arrears
```

### Final Take Home:
```
Net Take Home = Current Month Net + Net Arrears Payable
```

## Error Handling

1. **No Payroll Records:** Returns 404 with appropriate message
2. **Invalid Date Format:** Handles multiple date formats (20/8/2025, 2025-08-20, etc.)
3. **Missing Salary Structure:** Shows employee as "No Revision"
4. **No Arrears Found:** Returns JSON with employee details

## Future Enhancements

Possible improvements:
1. Add filtering by employee codes
2. Support for partial month arrears
3. PDF export option
4. Email notification after export
5. Arrears approval workflow
6. Historical arrears tracking

## Support Information

For issues or questions:
1. Check error logs in `storage/logs/laravel.log`
2. Verify database connectivity
3. Ensure all required columns exist in tables
4. Validate date formats in `arrearWithEffectFrom` column

## Conclusion

The Arrears Export API successfully:
✅ Calculates arrears from effective date
✅ Shows comprehensive breakup of all components
✅ Handles employees with and without revisions
✅ Provides professional Excel export
✅ Includes detailed summary statistics
✅ Supports filtering by sub-branch
✅ Handles various date formats

The API is production-ready and has been tested with sample data.
