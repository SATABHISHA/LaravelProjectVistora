# Payroll Rollback/Delete API Reference

## Overview
Three new APIs to rollback/delete salary processes:
1. **Rollback Initiated Salary** - Delete initiated salary for selected employees
2. **Rollback Released Salary** - Delete released salary for selected employees  
3. **Delete Month Salary Process** - Delete entire month's salary process (all employees, all statuses)

---

## 1. Rollback Initiated Salary Process
**DELETE** `/api/employee-payroll/rollback-initiated`

Deletes payroll records with status 'Initiated' for selected employees.

### Request Body
```json
{
  "corpId": "maco",
  "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
  "year": "2025",
  "month": "November",
  "empCodes": ["IMS0002", "IMS0006", "IMS0011"]
}
```

### Parameters
- `corpId` (required, string, max:10) - Corporate ID
- `companyName` (required, string, max:100) - Company name
- `year` (required, string, max:4) - Year (e.g., "2025")
- `month` (required, string, max:50) - Month name or number (e.g., "November" or "11")
- `empCodes` (required, array, min:1) - Array of employee codes to rollback

### Success Response (200)
```json
{
  "status": true,
  "message": "Successfully rolled back initiated salary for 3 employees",
  "filter": "corpId: maco, companyName: IMS MACO SERVICES INDIA PVT. LTD., year: 2025, month: November",
  "summary": {
    "requested_employees": 3,
    "successfully_deleted": 3,
    "not_found": 0
  },
  "deleted_employees": ["IMS0002", "IMS0006", "IMS0011"],
  "not_found_employees": []
}
```

### Not Found Response (404)
```json
{
  "status": false,
  "message": "No initiated salary records found for the specified employees",
  "filter": "corpId: maco, companyName: IMS MACO SERVICES INDIA PVT. LTD., year: 2025, month: November",
  "requested_employees": ["IMS0002"],
  "found_employees": []
}
```

### cURL Example
```bash
curl -X DELETE "http://127.0.0.1:8000/api/employee-payroll/rollback-initiated" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "year": "2025",
    "month": "November",
    "empCodes": ["IMS0002", "IMS0006", "IMS0011"]
  }'
```

### PowerShell Example
```powershell
$body = @{
    corpId = "maco"
    companyName = "IMS MACO SERVICES INDIA PVT. LTD."
    year = "2025"
    month = "November"
    empCodes = @("IMS0002", "IMS0006", "IMS0011")
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/employee-payroll/rollback-initiated" `
  -Method Delete `
  -ContentType "application/json" `
  -Body $body
```

---

## 2. Rollback Released Salary Process
**DELETE** `/api/employee-payroll/rollback-released`

Deletes payroll records with status 'Released' for selected employees.

### Request Body
```json
{
  "corpId": "maco",
  "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
  "year": "2025",
  "month": "October",
  "empCodes": ["IMS0002", "IMS0006"]
}
```

### Parameters
- `corpId` (required, string, max:10) - Corporate ID
- `companyName` (required, string, max:100) - Company name
- `year` (required, string, max:4) - Year (e.g., "2025")
- `month` (required, string, max:50) - Month name or number (e.g., "October" or "10")
- `empCodes` (required, array, min:1) - Array of employee codes to rollback

### Success Response (200)
```json
{
  "status": true,
  "message": "Successfully rolled back released salary for 2 employees",
  "filter": "corpId: maco, companyName: IMS MACO SERVICES INDIA PVT. LTD., year: 2025, month: October",
  "summary": {
    "requested_employees": 2,
    "successfully_deleted": 2,
    "not_found": 0
  },
  "deleted_employees": ["IMS0002", "IMS0006"],
  "not_found_employees": []
}
```

### Not Found Response (404)
```json
{
  "status": false,
  "message": "No released salary records found for the specified employees",
  "filter": "corpId: maco, companyName: IMS MACO SERVICES INDIA PVT. LTD., year: 2025, month: October",
  "requested_employees": ["IMS0002", "IMS0006"],
  "found_employees": []
}
```

### cURL Example
```bash
curl -X DELETE "http://127.0.0.1:8000/api/employee-payroll/rollback-released" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "year": "2025",
    "month": "October",
    "empCodes": ["IMS0002", "IMS0006"]
  }'
```

### PowerShell Example
```powershell
$body = @{
    corpId = "maco"
    companyName = "IMS MACO SERVICES INDIA PVT. LTD."
    year = "2025"
    month = "October"
    empCodes = @("IMS0002", "IMS0006")
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/employee-payroll/rollback-released" `
  -Method Delete `
  -ContentType "application/json" `
  -Body $body
```

---

## 3. Delete Entire Month Salary Process
**DELETE** `/api/employee-payroll/delete-month`

Deletes ALL payroll records for a specific month (all employees, all statuses: Initiated, Released, etc.)

⚠️ **WARNING**: This is a destructive operation that deletes all salary data for the entire month!

### Request Body
```json
{
  "corpId": "maco",
  "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
  "year": "2025",
  "month": "November"
}
```

### Parameters
- `corpId` (required, string, max:10) - Corporate ID
- `companyName` (required, string, max:100) - Company name
- `year` (required, string, max:4) - Year (e.g., "2025")
- `month` (required, string, max:50) - Month name or number (e.g., "November" or "11")

### Success Response (200)
```json
{
  "status": true,
  "message": "Successfully deleted entire salary process for November 2025",
  "filter": "corpId: maco, companyName: IMS MACO SERVICES INDIA PVT. LTD., year: 2025, month: November",
  "summary": {
    "total_records_deleted": 25,
    "status_breakdown": {
      "Initiated": {
        "count": 15,
        "employees": ["IMS0002", "IMS0006", "IMS0011", "..."]
      },
      "Released": {
        "count": 10,
        "employees": ["IMS0014", "IMS0015", "..."]
      }
    }
  }
}
```

### Not Found Response (404)
```json
{
  "status": false,
  "message": "No salary records found for the specified month",
  "filter": "corpId: maco, companyName: IMS MACO SERVICES INDIA PVT. LTD., year: 2025, month: November"
}
```

### cURL Example
```bash
curl -X DELETE "http://127.0.0.1:8000/api/employee-payroll/delete-month" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "year": "2025",
    "month": "November"
  }'
```

### PowerShell Example
```powershell
$body = @{
    corpId = "maco"
    companyName = "IMS MACO SERVICES INDIA PVT. LTD."
    year = "2025"
    month = "November"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/employee-payroll/delete-month" `
  -Method Delete `
  -ContentType "application/json" `
  -Body $body
```

---

## Common Features

### Month Normalization
All APIs accept month as either:
- **Month Name**: "January", "February", ..., "December"
- **Month Number**: "1", "2", ..., "12" (automatically converted to month name)

### Error Responses

**Validation Error (422)**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "corpId": ["The corp id field is required."],
    "empCodes": ["The emp codes field is required."]
  }
}
```

**Server Error (500)**
```json
{
  "status": false,
  "message": "Error rolling back initiated salary: [error details]"
}
```

---

## Use Cases

### Scenario 1: Rollback Initiated Salary for Specific Employees
Client initiated salary for wrong employees and wants to undo:
1. Call `DELETE /employee-payroll/rollback-initiated` with employee codes
2. Re-initiate for correct employees using `POST /employee-payroll/initiate-selected`

### Scenario 2: Rollback Released Salary
Salary was released prematurely or with errors:
1. Call `DELETE /employee-payroll/rollback-released` with affected employee codes
2. Fix the data
3. Re-release salary

### Scenario 3: Complete Month Reset
Entire month's payroll needs to be redone:
1. Call `DELETE /employee-payroll/delete-month` to clear all records
2. Re-process using `POST /employee-payroll/bulk-process` or `POST /employee-payroll/initiate-selected`

---

## Important Notes

1. **Status-Specific Deletion**: 
   - `rollback-initiated` only deletes records with status "Initiated"
   - `rollback-released` only deletes records with status "Released"
   - `delete-month` deletes ALL records regardless of status

2. **Atomic Operations**: Each API performs a single database transaction

3. **Audit Trail**: Consider implementing audit logging for these destructive operations

4. **Authorization**: Ensure proper role-based access control (RBAC) for these sensitive operations

5. **Confirmation**: Frontend should implement confirmation dialogs before calling these APIs

---

## Testing Checklist

- [ ] Test rollback with valid initiated salary records
- [ ] Test rollback with non-existent employee codes
- [ ] Test rollback with mixed valid/invalid employee codes
- [ ] Test rollback for wrong month/year (should return 404)
- [ ] Test delete-month with multiple statuses
- [ ] Test delete-month for empty month (should return 404)
- [ ] Verify month number conversion (1-12 → month names)
- [ ] Test validation errors (missing fields)
- [ ] Verify only matching status records are deleted

---

## Database Table
Table: `employee_payroll_salary_process`

Relevant Columns:
- `corpId` - Corporate ID
- `companyName` - Company name
- `empCode` - Employee code
- `year` - Year
- `month` - Month name
- `status` - Status (Initiated, Released, etc.)
- All other payroll fields (grossList, otherAllowances, etc.)

---

## Production Deployment

After deploying to production, ensure:

1. Clear route cache:
```bash
php artisan route:clear
php artisan route:cache
```

2. Test all three endpoints with production data

3. Monitor logs for any errors:
```bash
tail -f storage/logs/laravel.log
```
