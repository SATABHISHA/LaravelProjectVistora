# Attendance Summary Export/Import API Documentation

## Overview
This document describes the two new APIs created for exporting and importing employee attendance summary data in Excel format.

---

## 1. Export Attendance Summary API

### Endpoint
```
POST /api/attendance-summary/export
```

### Description
Exports employee attendance summary data to an Excel file (.xlsx) based on filtering criteria. The exported file includes employee details, attendance metrics, and can be modified and re-imported.

### Request

#### Headers
```
Content-Type: application/json
```

#### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corpId | string | Yes | Corporate ID (max 10 chars) |
| companyName | string | Yes | Company name (max 100 chars) |
| year | string | Yes | Year (4 digits) |
| month | string | Yes | Month name (e.g., "October", "January") |

#### Example Request
```json
{
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "year": "2025",
    "month": "October"
}
```

### Response

#### Success Response (200 OK)
- **Type**: Excel file download (.xlsx)
- **Filename Format**: `Attendance_Summary_{corpId}_{companyName}_{month}_{year}.xlsx`
- **Example**: `Attendance_Summary_maco_IMS MACO SERVICES INDIA PVT. LTD._October_2025.xlsx`

#### Excel File Structure
The exported Excel file contains the following columns:

| Column | Description |
|--------|-------------|
| ID | Database record ID |
| Corp ID | Corporate identifier |
| Employee Code | Unique employee code |
| Employee Name | Full name of employee |
| Designation | Employee designation/position |
| Department | Department name |
| Company Name | Company name |
| Total Present | Number of days present |
| Working Days | Total working days in the period |
| Holidays | Number of holidays |
| Week Off | Number of week-off days |
| Leave | Number of leave days |
| Paid Days | Total paid days |
| Absent | Number of absent days |
| Month | Month name |
| Year | Year |

#### Error Responses

**404 Not Found** - No data found
```json
{
    "status": false,
    "message": "No attendance summary found for the specified criteria",
    "filter": "corpId: maco, companyName: IMS MACO SERVICES INDIA PVT. LTD., year: 2025, month: October"
}
```

**422 Validation Error**
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "corpId": ["The corp id field is required."]
    }
}
```

**500 Internal Server Error**
```json
{
    "status": false,
    "message": "An error occurred while exporting attendance summary",
    "error": "Error details..."
}
```

### PowerShell Example
```powershell
$body = @{
    corpId = 'maco'
    companyName = 'IMS MACO SERVICES INDIA PVT. LTD.'
    year = '2025'
    month = 'October'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8000/api/attendance-summary/export' `
    -Method Post `
    -Body $body `
    -ContentType 'application/json' `
    -OutFile 'attendance_export.xlsx'
```

### cURL Example
```bash
curl -X POST http://localhost:8000/api/attendance-summary/export \
  -H "Content-Type: application/json" \
  -d '{
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "year": "2025",
    "month": "October"
  }' \
  --output attendance_export.xlsx
```

---

## 2. Import Attendance Summary API

### Endpoint
```
POST /api/attendance-summary/import
```

### Description
Imports and updates employee attendance summary data from an Excel file. The file is processed in memory without being saved to the server. Updates existing records based on the ID column.

### Request

#### Headers
```
Content-Type: multipart/form-data
```

#### Form Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| file | file | Yes | Excel file (.xlsx or .xls, max 10MB) |

#### Excel File Requirements
1. **Must contain the exact header row** as exported:
   - ID, Corp ID, Employee Code, Employee Name, Designation, Department, Company Name, Total Present, Working Days, Holidays, Week Off, Leave, Paid Days, Absent, Month, Year

2. **ID column must match existing database records**
   - The import uses the ID to find and update records

3. **Updatable columns**:
   - Total Present
   - Working Days
   - Holidays
   - Week Off
   - Leave
   - Paid Days
   - Absent
   - Month
   - Year

### Response

#### Success Response (200 OK)
```json
{
    "status": true,
    "message": "Successfully updated 24 attendance records",
    "summary": {
        "total_rows_processed": 24,
        "successfully_updated": 24,
        "errors": 0
    },
    "updated_records": [
        {
            "id": "128",
            "empCode": "IMS0002",
            "companyName": "IMS MACO SERVICES INDIA PVT. LTD."
        },
        {
            "id": "129",
            "empCode": "IMS0006",
            "companyName": "IMS MACO SERVICES INDIA PVT. LTD."
        }
        // ... more records
    ],
    "errors": []
}
```

#### Partial Success (with errors)
```json
{
    "status": true,
    "message": "Successfully updated 20 attendance records",
    "summary": {
        "total_rows_processed": 24,
        "successfully_updated": 20,
        "errors": 4
    },
    "updated_records": [...],
    "errors": [
        {
            "row": 5,
            "empCode": "IMS0099",
            "error": "Record not found in database"
        }
    ]
}
```

#### Error Responses

**400 Bad Request** - Empty file or invalid format
```json
{
    "status": false,
    "message": "The uploaded file is empty or contains no data rows"
}
```

**400 Bad Request** - Invalid headers
```json
{
    "status": false,
    "message": "Invalid file format. Headers do not match expected format.",
    "expected_headers": [...],
    "received_headers": [...]
}
```

**422 Validation Error**
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "file": ["The file field is required."]
    }
}
```

**500 Internal Server Error**
```json
{
    "status": false,
    "message": "An error occurred while importing attendance summary",
    "error": "Error details..."
}
```

### PowerShell Example
```powershell
Add-Type -AssemblyName System.Net.Http

$client = New-Object System.Net.Http.HttpClient
$content = New-Object System.Net.Http.MultipartFormDataContent

$fileStream = [System.IO.File]::OpenRead('attendance_export.xlsx')
$fileContent = New-Object System.Net.Http.StreamContent($fileStream)
$fileContent.Headers.ContentType = [System.Net.Http.Headers.MediaTypeHeaderValue]::Parse('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
$content.Add($fileContent, 'file', 'attendance_export.xlsx')

$response = $client.PostAsync('http://localhost:8000/api/attendance-summary/import', $content).Result
$result = $response.Content.ReadAsStringAsync().Result
$fileStream.Close()

Write-Host $result
```

### cURL Example
```bash
curl -X POST http://localhost:8000/api/attendance-summary/import \
  -F "file=@attendance_export.xlsx"
```

---

## Workflow Example

### Complete Export-Edit-Import Workflow

1. **Export Current Data**
```powershell
$body = @{
    corpId = 'maco'
    companyName = 'IMS MACO SERVICES INDIA PVT. LTD.'
    year = '2025'
    month = 'October'
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8000/api/attendance-summary/export' `
    -Method Post `
    -Body $body `
    -ContentType 'application/json' `
    -OutFile 'attendance_october.xlsx'
```

2. **Edit the Excel File**
   - Open `attendance_october.xlsx` in Excel
   - Modify values in columns: Total Present, Leave, Paid Days, Absent, etc.
   - Save the file (keep the same format and headers)

3. **Import Updated Data**
```powershell
Add-Type -AssemblyName System.Net.Http
$client = New-Object System.Net.Http.HttpClient
$content = New-Object System.Net.Http.MultipartFormDataContent
$fileStream = [System.IO.File]::OpenRead('attendance_october.xlsx')
$fileContent = New-Object System.Net.Http.StreamContent($fileStream)
$fileContent.Headers.ContentType = [System.Net.Http.Headers.MediaTypeHeaderValue]::Parse('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
$content.Add($fileContent, 'file', 'attendance_october.xlsx')
$response = $client.PostAsync('http://localhost:8000/api/attendance-summary/import', $content).Result
$result = $response.Content.ReadAsStringAsync().Result
$fileStream.Close()
Write-Host $result
```

---

## Testing Results

### Test Environment
- **Date**: November 9, 2025
- **Test Data**: maco / IMS MACO SERVICES INDIA PVT. LTD. / October 2025
- **Records**: 24 employee attendance records

### Export Test
✅ **Status**: Passed
- Successfully exported 24 records
- File size: 8,699 bytes
- File format: .xlsx
- All columns correctly populated with employee details and attendance data

### Import Test
✅ **Status**: Passed
- Successfully uploaded and processed Excel file
- All 24 records updated successfully
- No errors encountered
- Response confirmed:
  ```json
  {
    "status": true,
    "message": "Successfully updated 24 attendance records",
    "summary": {
      "total_rows_processed": 24,
      "successfully_updated": 24,
      "errors": 0
    }
  }
  ```

---

## Features

### Export API Features
- ✅ Filters by corpId, companyName, year, and month
- ✅ Joins with employee_details and employment_details for complete information
- ✅ Formatted Excel with styled headers (blue background, white text)
- ✅ Auto-sized columns for readability
- ✅ Descriptive filename with filter parameters
- ✅ Proper error handling and validation

### Import API Features
- ✅ Processes file in memory (no server storage required)
- ✅ Validates file format and headers
- ✅ Updates only existing records (prevents data corruption)
- ✅ Bulk update operation for efficiency
- ✅ Detailed response with success/error breakdown
- ✅ Row-level error reporting
- ✅ Transaction-safe updates

---

## Important Notes

1. **File Not Saved**: The imported Excel file is processed in memory and is NOT saved on the server.

2. **ID Matching**: The import updates records based on the ID column. Do not modify the ID column in the Excel file.

3. **Data Validation**: The import validates that the record exists before updating. Non-existent IDs will be reported in the errors array.

4. **File Size Limit**: Maximum upload file size is 10MB.

5. **Supported Formats**: .xlsx and .xls files are supported.

6. **Read-Only Columns**: While you can modify Employee Name, Designation, and Department in the Excel file, these columns are NOT updated in the database (they are display-only).

7. **Primary Update Fields**: The main fields that should be updated are:
   - Total Present
   - Leave
   - Paid Days
   - Absent
   - Working Days (if needed)
   - Holidays (if needed)
   - Week Off (if needed)

---

## Implementation Summary

### Files Modified
1. **Controller**: `app/Http/Controllers/EmployeeAttendanceSummaryApiController.php`
   - Added `exportAttendanceSummary()` method
   - Added `importAttendanceSummary()` method
   - Added necessary imports for PhpSpreadsheet

2. **Routes**: `routes/api.php`
   - Added `POST /api/attendance-summary/export`
   - Added `POST /api/attendance-summary/import`

### Dependencies Used
- **maatwebsite/excel**: Already installed (^3.1)
- **PhpSpreadsheet**: For direct Excel manipulation
- **Laravel's built-in validation**
- **Laravel's file handling**

---

## Conclusion

Both APIs have been successfully implemented and tested. The export-import workflow allows administrators to:
1. Export attendance data to Excel
2. Make bulk modifications offline
3. Import the changes back to the database
4. Receive detailed feedback on the import operation

All operations are secure, validated, and provide comprehensive error reporting.
