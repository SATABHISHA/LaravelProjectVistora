# Employee Leave Balance API Documentation

## Overview

The Employee Leave Balance API provides functionality to manage employee leave allotments, track leave balances, and handle carry forward logic based on leave type configurations.

**Base URL:** `http://localhost:8000/api`

---

## Table of Contents

1. [Allot Leaves to Employees](#1-allot-leaves-to-employees)
2. [Process Monthly Credits](#2-process-monthly-credits)
3. [Get All Employees Leave List](#3-get-all-employees-leave-list)
4. [Get Individual Employee Leave Balance](#4-get-individual-employee-leave-balance)
5. [Update Leave Used](#5-update-leave-used)
6. [Revert Leave Used](#6-revert-leave-used)
7. [Get Leave Summary](#7-get-leave-summary)

---

## 1. Allot Leaves to Employees

Allot leaves to all employees based on configured leave types. Only admin users can perform this action.

### Endpoint

```
POST /api/employee-leave-balance/allot
```

### Headers

| Header | Value | Required |
|--------|-------|----------|
| Content-Type | application/json | Yes |
| Accept | application/json | Yes |

### Request Body Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corp_id | string | Yes | Corporate ID |
| emp_code | string | Yes | Employee code of the admin user performing the action |
| year | integer | Yes | Year for which leaves should be allotted (e.g., 2025) |

### Request Example

```json
{
    "corp_id": "test",
    "emp_code": "EMP001",
    "year": 2025
}
```

### cURL Example

```bash
curl -X POST "http://localhost:8000/api/employee-leave-balance/allot" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corp_id": "test",
    "emp_code": "EMP001",
    "year": 2025
  }'
```

### Success Response (201 Created)

```json
{
    "status": true,
    "message": "Leave allotment completed successfully.",
    "data": {
        "total_leave_records_created": 8,
        "total_records_skipped": 0,
        "carry_forward_applied": 0,
        "employees_allotted": 4,
        "employees_skipped": 0,
        "year": 2025
    }
}
```

### Re-allotment Response (Already Allotted)

```json
{
    "status": true,
    "message": "Leave allotment completed successfully.",
    "data": {
        "total_leave_records_created": 0,
        "total_records_skipped": 8,
        "carry_forward_applied": 0,
        "employees_allotted": 0,
        "employees_skipped": 4,
        "year": 2025
    }
}
```

### Error Response - Non-Admin User (403 Forbidden)

```json
{
    "status": false,
    "message": "Access denied. Only admin users can allot leaves."
}
```

### Error Response - No Employees Found (404 Not Found)

```json
{
    "status": false,
    "message": "No employees found for this corp_id."
}
```

### Error Response - No Leave Configurations (404 Not Found)

```json
{
    "status": false,
    "message": "No leave type configurations found for this corp_id. Please configure leave types first."
}
```

### Business Logic

- Only users with `admin_yn = 1` in `userlogin` table can allot leaves
- Duplicate leaves for the same employee, leave type, and year are automatically skipped
- Carry forward is applied based on `lapseLeaveYn` setting in `leave_type_full_configurations`:
  - If `lapseLeaveYn = 'NO'`: Carry forward is allowed based on `maxCarryForwardLeavesType`
  - If `lapseLeaveYn = 'YES'`: Leaves lapse, no carry forward
- Monthly credited leaves are calculated based on the current month when allotting

---

## 2. Process Monthly Credits

Process monthly leave credits for employees with monthly credit type leaves. Admin only.

### Endpoint

```
POST /api/employee-leave-balance/process-monthly
```

### Headers

| Header | Value | Required |
|--------|-------|----------|
| Content-Type | application/json | Yes |
| Accept | application/json | Yes |

### Request Body Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corp_id | string | Yes | Corporate ID |
| emp_code | string | Yes | Employee code of the admin user |
| year | integer | Yes | Year for processing |
| month | integer | Yes | Month to process (1-12) |

### Request Example

```json
{
    "corp_id": "test",
    "emp_code": "EMP001",
    "year": 2025,
    "month": 6
}
```

### cURL Example

```bash
curl -X POST "http://localhost:8000/api/employee-leave-balance/process-monthly" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corp_id": "test",
    "emp_code": "EMP001",
    "year": 2025,
    "month": 6
  }'
```

### Success Response (200 OK)

```json
{
    "status": true,
    "message": "Monthly credits processed successfully.",
    "data": {
        "records_updated": 4,
        "records_skipped": 0,
        "year": 2025,
        "month": 6
    }
}
```

### Error Response - Non-Admin User (403 Forbidden)

```json
{
    "status": false,
    "message": "Access denied. Only admin users can process monthly credits."
}
```

---

## 3. Get All Employees Leave List

Retrieve leave balances for all employees in a corporation.

### Endpoint

```
GET /api/employee-leave-balance/list/{corpId}
```

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corpId | string | Yes | Corporate ID |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| year | integer | No | Current Year | Year for which to retrieve leave balances |

### cURL Example

```bash
curl -X GET "http://localhost:8000/api/employee-leave-balance/list/test?year=2025" \
  -H "Accept: application/json"
```

### Success Response (200 OK)

```json
{
    "status": true,
    "message": "Employee leave list retrieved successfully.",
    "total_employees": 4,
    "year": 2025,
    "data": [
        {
            "emp_code": "EMP001",
            "emp_full_name": "Rajesh Kr Patel",
            "year": 2025,
            "leave_types": [
                {
                    "leave_code": "CL",
                    "leave_name": "Causal Leave",
                    "total_allotted": 24,
                    "used": 0,
                    "balance": 24,
                    "carry_forward": 0,
                    "credit_type": "monthly",
                    "is_lapsed": false,
                    "created_at": "2025-12-31T10:44:58.000000Z",
                    "updated_at": "2025-12-31T10:44:58.000000Z"
                },
                {
                    "leave_code": "SL",
                    "leave_name": "Sick Leave",
                    "total_allotted": 20,
                    "used": 0,
                    "balance": 20,
                    "carry_forward": 0,
                    "credit_type": "yearly",
                    "is_lapsed": false,
                    "created_at": "2025-12-31T10:44:58.000000Z",
                    "updated_at": "2025-12-31T10:44:58.000000Z"
                }
            ]
        },
        {
            "emp_code": "EMP002",
            "emp_full_name": "Indranil Shah",
            "year": 2025,
            "leave_types": [
                {
                    "leave_code": "CL",
                    "leave_name": "Causal Leave",
                    "total_allotted": 24,
                    "used": 0,
                    "balance": 24,
                    "carry_forward": 0,
                    "credit_type": "monthly",
                    "is_lapsed": false,
                    "created_at": "2025-12-31T10:44:58.000000Z",
                    "updated_at": "2025-12-31T10:44:58.000000Z"
                },
                {
                    "leave_code": "SL",
                    "leave_name": "Sick Leave",
                    "total_allotted": 20,
                    "used": 0,
                    "balance": 20,
                    "carry_forward": 0,
                    "credit_type": "yearly",
                    "is_lapsed": false,
                    "created_at": "2025-12-31T10:44:58.000000Z",
                    "updated_at": "2025-12-31T10:44:58.000000Z"
                }
            ]
        }
    ]
}
```

### Error Response - No Data Found

```json
{
    "status": false,
    "message": "No leave balances found for this corp_id and year.",
    "data": []
}
```

---

## 4. Get Individual Employee Leave Balance

Retrieve leave balances for a specific employee.

### Endpoint

```
GET /api/employee-leave-balance/{corpId}/{empCode}
```

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corpId | string | Yes | Corporate ID |
| empCode | string | Yes | Employee Code |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| year | integer | No | Current Year | Year for which to retrieve leave balance |

### cURL Example

```bash
curl -X GET "http://localhost:8000/api/employee-leave-balance/test/EMP001?year=2025" \
  -H "Accept: application/json"
```

### Success Response (200 OK)

```json
{
    "status": true,
    "message": "Employee leave balance retrieved successfully.",
    "data": {
        "corp_id": "test",
        "emp_code": "EMP001",
        "emp_full_name": "Rajesh Kr Patel",
        "year": 2025,
        "total_leave_types": 2,
        "leave_balances": [
            {
                "leave_code": "CL",
                "leave_name": "Causal Leave",
                "total_allotted": 24,
                "used": 0,
                "balance": 24,
                "carry_forward": 0,
                "credit_type": "monthly",
                "is_lapsed": false,
                "last_credited_at": "2025-12-31T10:44:58.000000Z",
                "created_at": "2025-12-31T10:44:58.000000Z",
                "updated_at": "2025-12-31T10:44:58.000000Z"
            },
            {
                "leave_code": "SL",
                "leave_name": "Sick Leave",
                "total_allotted": 20,
                "used": 0,
                "balance": 20,
                "carry_forward": 0,
                "credit_type": "yearly",
                "is_lapsed": false,
                "last_credited_at": "2025-12-31T10:44:58.000000Z",
                "created_at": "2025-12-31T10:44:58.000000Z",
                "updated_at": "2025-12-31T10:44:58.000000Z"
            }
        ]
    }
}
```

### Error Response - Employee Not Found (404 Not Found)

```json
{
    "status": false,
    "message": "Employee not found with the given corp_id and emp_code.",
    "data": []
}
```

### Error Response - No Leaves Allotted

```json
{
    "status": false,
    "message": "No leaves have been allotted to this employee for the year 2025. Please contact admin to allot leaves.",
    "data": []
}
```

---

## 5. Update Leave Used

Deduct leave balance when an employee uses leave (typically called after leave request approval).

### Endpoint

```
POST /api/employee-leave-balance/update-used
```

### Headers

| Header | Value | Required |
|--------|-------|----------|
| Content-Type | application/json | Yes |
| Accept | application/json | Yes |

### Request Body Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corp_id | string | Yes | Corporate ID |
| emp_code | string | Yes | Employee Code |
| leave_type_puid | string | Yes | Leave type PUID from leave_type_basic_configurations |
| days_used | numeric | Yes | Number of days to deduct (minimum 0.5 for half-day) |
| year | integer | Yes | Leave year |

### Request Example

```json
{
    "corp_id": "test",
    "emp_code": "EMP001",
    "leave_type_puid": "9f1fd7d9-3015-4787-814e-15992e681ba3",
    "days_used": 2,
    "year": 2025
}
```

### cURL Example

```bash
curl -X POST "http://localhost:8000/api/employee-leave-balance/update-used" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corp_id": "test",
    "emp_code": "EMP001",
    "leave_type_puid": "9f1fd7d9-3015-4787-814e-15992e681ba3",
    "days_used": 2,
    "year": 2025
  }'
```

### Success Response (200 OK)

```json
{
    "status": true,
    "message": "Leave balance updated successfully.",
    "data": {
        "emp_code": "EMP001",
        "leave_code": "CL",
        "days_deducted": 2,
        "new_balance": 22,
        "total_used": 2
    }
}
```

### Error Response - Leave Balance Not Found (404 Not Found)

```json
{
    "status": false,
    "message": "Leave balance not found for this employee and leave type."
}
```

### Error Response - Insufficient Balance (400 Bad Request)

```json
{
    "status": false,
    "message": "Insufficient leave balance. Available: 5 days."
}
```

---

## 6. Revert Leave Used

Revert leave deduction when a leave request is cancelled.

### Endpoint

```
POST /api/employee-leave-balance/revert-used
```

### Headers

| Header | Value | Required |
|--------|-------|----------|
| Content-Type | application/json | Yes |
| Accept | application/json | Yes |

### Request Body Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corp_id | string | Yes | Corporate ID |
| emp_code | string | Yes | Employee Code |
| leave_type_puid | string | Yes | Leave type PUID from leave_type_basic_configurations |
| days_to_revert | numeric | Yes | Number of days to add back (minimum 0.5) |
| year | integer | Yes | Leave year |

### Request Example

```json
{
    "corp_id": "test",
    "emp_code": "EMP001",
    "leave_type_puid": "9f1fd7d9-3015-4787-814e-15992e681ba3",
    "days_to_revert": 1,
    "year": 2025
}
```

### cURL Example

```bash
curl -X POST "http://localhost:8000/api/employee-leave-balance/revert-used" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corp_id": "test",
    "emp_code": "EMP001",
    "leave_type_puid": "9f1fd7d9-3015-4787-814e-15992e681ba3",
    "days_to_revert": 1,
    "year": 2025
  }'
```

### Success Response (200 OK)

```json
{
    "status": true,
    "message": "Leave reverted successfully.",
    "data": {
        "emp_code": "EMP001",
        "leave_code": "CL",
        "days_reverted": 1,
        "new_balance": 23,
        "total_used": 1
    }
}
```

### Error Response - Leave Balance Not Found (404 Not Found)

```json
{
    "status": false,
    "message": "Leave balance not found for this employee and leave type."
}
```

---

## 7. Get Leave Summary

Get aggregated leave summary for admin dashboard.

### Endpoint

```
GET /api/employee-leave-balance/summary/{corpId}
```

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| corpId | string | Yes | Corporate ID |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| year | integer | No | Current Year | Year for summary |

### cURL Example

```bash
curl -X GET "http://localhost:8000/api/employee-leave-balance/summary/test?year=2025" \
  -H "Accept: application/json"
```

### Success Response (200 OK)

```json
{
    "status": true,
    "message": "Leave summary retrieved successfully.",
    "year": "2025",
    "data": [
        {
            "leave_code": "CL",
            "leave_name": "Causal Leave",
            "total_employees": 4,
            "total_allotted": "96.00",
            "total_used": "0.00",
            "total_balance": "96.00",
            "total_carry_forward": "0.00"
        },
        {
            "leave_code": "SL",
            "leave_name": "Sick Leave",
            "total_employees": 4,
            "total_allotted": "80.00",
            "total_used": "0.00",
            "total_balance": "80.00",
            "total_carry_forward": "0.00"
        }
    ]
}
```

### Error Response - No Data Found

```json
{
    "status": false,
    "message": "No leave data found for this corp_id and year.",
    "data": []
}
```

---

## Database Schema

### Table: `employee_leave_balances`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| corp_id | varchar(255) | Corporate ID |
| emp_code | varchar(255) | Employee code |
| emp_full_name | varchar(255) | Employee full name |
| leave_type_puid | varchar(255) | Reference to leave_type_basic_configurations |
| leave_code | varchar(255) | Leave code (e.g., CL, SL) |
| leave_name | varchar(255) | Leave name |
| total_allotted | decimal(8,2) | Total leaves allotted |
| used | decimal(8,2) | Leaves used |
| balance | decimal(8,2) | Remaining balance |
| carry_forward | decimal(8,2) | Carry forward from previous year |
| year | int | Leave year |
| month | int | Last credited month (for monthly leaves) |
| credit_type | varchar(255) | Credit type: 'yearly' or 'monthly' |
| is_lapsed | boolean | Whether leave has lapsed |
| last_credited_at | timestamp | Last credit timestamp |
| created_at | timestamp | Record creation time |
| updated_at | timestamp | Record update time |

### Unique Constraint

`unique_leave_balance` on (`corp_id`, `emp_code`, `leave_type_puid`, `year`)

---

## Related Tables

- `leave_type_basic_configurations` - Contains basic leave type settings
- `leave_type_full_configurations` - Contains full leave configurations including carry forward rules
- `userlogin` - Used for admin validation (`admin_yn` column)
- `employee_details` - Employee information

---

## Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 200 | Success |
| 201 | Created (new records) |
| 400 | Bad Request (e.g., insufficient balance) |
| 403 | Forbidden (non-admin trying admin action) |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## Notes

1. **Admin Validation**: The system checks `admin_yn = 1` in the `userlogin` table to validate admin access.

2. **Carry Forward Logic**:
   - Based on `lapseLeaveYn` in `leave_type_full_configurations`
   - If `lapseLeaveYn = 'NO'`, carry forward is applied based on `maxCarryForwardLeavesType`:
     - `'All'`: Carry forward entire balance
     - `'Days'`: Carry forward up to `maxCarryForwardLeavesBalance` days
     - `'Zero'`: No carry forward

3. **Monthly Credits**: For leave types with `leaveTypeTobeCredited = 'Monthly'`, the system calculates monthly credits as `LimitDays / 12`.

4. **Duplicate Prevention**: The unique constraint prevents duplicate leave allotments for the same employee, leave type, and year.

---

## Automated Cron Job for Monthly Leave Credits

The system includes an automated cron job that processes monthly leave credits automatically on the 1st of every month.

### Artisan Command

```bash
php artisan leave:process-monthly-credits
```

### Command Options

| Option | Description |
|--------|-------------|
| `--corp_id=` | Process only for a specific corporate ID |
| `--year=` | Process for a specific year (default: current year) |
| `--month=` | Process for a specific month (default: current month) |
| `--force` | Force reprocess even if already processed for the month |

### Usage Examples

```bash
# Process all corporations for current month
php artisan leave:process-monthly-credits

# Process specific corporation
php artisan leave:process-monthly-credits --corp_id=test

# Process for specific year and month
php artisan leave:process-monthly-credits --year=2025 --month=6

# Force reprocess (useful for corrections)
php artisan leave:process-monthly-credits --corp_id=test --force
```

### Server Crontab Setup

To enable automatic monthly processing, add the following to your server's crontab:

```bash
# Edit crontab
crontab -e

# Add Laravel scheduler (runs every minute, Laravel handles the scheduling)
* * * * * cd /path/to/your/laravel/project && php artisan schedule:run >> /dev/null 2>&1
```

Or run the command directly on the 1st of every month:

```bash
# Direct cron entry for leave credits (runs at 00:05 on 1st of each month)
5 0 1 * * cd /path/to/your/laravel/project && php artisan leave:process-monthly-credits >> /var/log/leave-credits.log 2>&1
```

### What the Cron Job Does

1. **Monthly Credit Addition**: 
   - Automatically adds monthly leave credits for leave types configured with \`leaveTypeTobeCredited = 'Monthly'\`
   - Calculates credit as \`LimitDays / 12\` and adds it to the balance

2. **New Employee Allotment**:
   - Automatically creates leave balance records for new employees who don't have any
   - Prorates monthly leaves based on the current month

3. **Carry Forward Processing**:
   - At year start (January), calculates and applies carry forward from previous year
   - Respects \`lapseLeaveYn\` and \`maxCarryForwardLeavesType\` configurations

4. **Skip Already Processed**:
   - Automatically skips employees already processed for the current month
   - Use \`--force\` flag to reprocess if needed

### Cron Job vs API Interaction

| Scenario | Recommendation |
|----------|----------------|
| Regular monthly processing | Use cron job (automatic) |
| New company setup | Use API (manual initial allotment) |
| Mid-month corrections | Use API with specific month |
| Bulk reprocessing | Use cron with \`--force\` flag |

### Log Files

The cron job writes logs to:
- \`storage/logs/leave-credits-cron.log\` - Cron execution output
- \`storage/logs/laravel-{date}.log\` - Detailed processing logs

### Example Log Output

```
======================================
Processing Monthly Leave Credits
Year: 2025, Month: 2
======================================

Processing Corp ID: test
----------------------------------------
  ✓ Processed: 4, Skipped: 0, New Allotments: 2

Processing Corp ID: demo
----------------------------------------
  ✓ Processed: 8, Skipped: 0, New Allotments: 0

======================================
Summary:
  Total Processed: 12
  Total Skipped: 0
  Total New Allotments: 2
  Total Errors: 0
======================================
```

---

## Additional Notes (Cron)

5. **Cron Job**: Set up the Laravel scheduler or direct crontab entry for automatic monthly leave credit processing on the 1st of each month.

