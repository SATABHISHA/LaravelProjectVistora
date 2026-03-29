# Paygroup Configuration V1 API Documentation

## Overview
The Paygroup Configuration V1 API provides endpoints for managing paygroup configurations using the `paygroup_configuration_v1s` table and calculating complete payroll breakdowns using `formula_builders` for component value calculations.

### Key Differences from V1 vs Original Paygroup API
| Feature | Original (`/paygroup/`) | V1 (`/paygroupv1/`) |
|---|---|---|
| Table | `paygroup_configurations` | `paygroup_configuration_v1s` |
| Formula Source | Global `formula_builders` by `componentName` | Scoped `formula_builders` by `paygroupPuid` |
| Complete Breakdown Params | `groupName, corpId, basicSalary, ctc, empCode, companyName` | `groupName, corpId, ctc` |
| Basic Salary | Passed as parameter | Derived from CTC using formula_builders |
| Statutory Calculations | Yes (VPF, NPS, ESI, Medical) | No (simpler calculation) |
| CTCAllowances column | Yes | No |

---

## API Endpoints

### 1. Create or Update Paygroup Configuration V1
**POST** `/api/paygroupconfigurationv1/add-or-update`

Creates a new paygroup configuration or updates an existing one (if `puid` is provided).

**Request Body:**
```json
{
    "corpId": "TESTCORP",
    "GroupName": "SalaryGroup1",
    "IncludedComponents": "Basic,HRA,DA,PF",
    "puid": "PGV1-XXXXXXXX"  // Optional - include for update
}
```

**Response (201 Created / 200 Updated):**
```json
{
    "status": true,
    "message": "PaygroupConfigurationV1 created successfully",
    "data": {
        "id": 3,
        "corpId": "TESTCORP",
        "puid": "PGV1-B8ESLN9N",
        "GroupName": "SalaryGroup1",
        "IncludedComponents": "Basic,HRA,DA,PF",
        "created_at": "2026-03-29T03:44:20.000000Z",
        "updated_at": "2026-03-29T03:44:20.000000Z"
    }
}
```

**Error Responses:**
- `409` — GroupName already exists for this corpId
- `422` — Validation failed (missing corpId or GroupName)
- `404` — puid not found (when updating)

---

### 2. Get All Paygroup Configurations by corpId
**GET** `/api/paygroupconfigurationv1/all/{corpId}`

Returns all paygroup configurations for a given corpId.

**Example:** `GET /api/paygroupconfigurationv1/all/TESTCORP`

**Response:**
```json
{
    "status": true,
    "data": [
        {
            "id": 3,
            "corpId": "TESTCORP",
            "puid": "PGV1-B8ESLN9N",
            "GroupName": "SalaryGroup1",
            "IncludedComponents": "Basic,HRA,DA,PF",
            "created_at": "2026-03-29T03:44:20.000000Z",
            "updated_at": "2026-03-29T03:44:20.000000Z"
        }
    ]
}
```

---

### 3. Get Group Names by corpId
**GET** `/api/paygroupv1/groupnames/{corpId}`

Returns all group names with their details for a given corpId.

**Example:** `GET /api/paygroupv1/groupnames/TESTCORP`

**Response:**
```json
{
    "status": true,
    "data": [
        {
            "puid": "PGV1-B8ESLN9N",
            "GroupName": "SalaryGroup1",
            "IncludedComponents": "Basic,HRA,DA,PF",
            "created_at": "2026-03-29T03:44:20.000000Z",
            "updated_at": "2026-03-29T03:44:20.000000Z"
        }
    ],
    "totalCount": 1
}
```

---

### 4. Delete Paygroup Configuration V1
**DELETE** `/api/paygroupconfigurationv1/delete/{puid}`

Deletes a paygroup configuration **and all related formula_builders** (cascade delete).

**Example:** `DELETE /api/paygroupconfigurationv1/delete/PGV1-B8ESLN9N`

**Response:**
```json
{
    "status": true,
    "message": "PaygroupConfigurationV1 and related formula builders deleted successfully",
    "deletedFormulaBuilders": 4
}
```

**Error Responses:**
- `404` — PaygroupConfigurationV1 not found

---

### 5. Complete Improved Payroll Breakdown (V1)
**GET** `/api/paygroupv1/complete-improved/{groupName}/{corpId}/{ctc}`

Calculates a complete payroll breakdown using `paygroup_configuration_v1s` and `formula_builders` tables.

**Parameters:**
| Parameter | Type | Description |
|---|---|---|
| `groupName` | string | The paygroup group name |
| `corpId` | string | Corporation/company ID |
| `ctc` | numeric | Monthly CTC amount |

**How it works:**
1. Finds the paygroup config by `GroupName` + `corpId` in `paygroup_configuration_v1s`
2. Gets the paygroup's `puid`
3. Loads all `formula_builders` entries where `paygroupPuid` matches
4. Calculates **Basic** salary from CTC using the Basic component's formula (defaults to 40% of CTC if no formula defined)
5. For each component in `IncludedComponents`, calculates its value using the formula_builder entry
6. Categorizes components into Gross (Addition), Deductions, and Benefits based on `pay_components.payType`

**Example:** `GET /api/paygroupv1/complete-improved/SalaryGroup1/TESTCORP/100000`

**Response:**
```json
{
    "status": true,
    "message": "Payroll breakdown calculated successfully (V1)",
    "data": {
        "groupName": "SalaryGroup1",
        "corpId": "TESTCORP",
        "paygroupPuid": "PGV1-B8ESLN9N",
        "ctc": 100000,
        "basicSalary": 50000,
        "gross": [
            {
                "componentName": "Basic",
                "payType": "Addition",
                "paymentNature": "Fixed",
                "formula": "Basic",
                "calculatedValue": 50000,
                "annualCalculatedValue": 600000
            },
            {
                "componentName": "HRA",
                "payType": "Addition",
                "paymentNature": "Fixed",
                "formula": "50% of Basic",
                "calculatedValue": 25000,
                "annualCalculatedValue": 300000
            }
        ],
        "deductions": [
            {
                "componentName": "PF",
                "payType": "Deduction",
                "paymentNature": "Fixed",
                "formula": "12% of Basic",
                "calculatedValue": 6000,
                "annualCalculatedValue": 72000
            }
        ],
        "otherBenefitsAllowances": [],
        "summary": {
            "totalGross": { "monthly": 75000, "annual": 900000 },
            "totalDeductions": { "monthly": 6000, "annual": 72000 },
            "totalBenefits": { "monthly": 0, "annual": 0 },
            "netSalary": { "monthly": 69000, "annual": 828000 },
            "totalCTC": { "monthly": 75000, "annual": 900000 }
        },
        "component_counts": {
            "gross_components": 2,
            "deduction_components": 1,
            "benefit_components": 0,
            "total_components": 3
        }
    }
}
```

**Error Responses:**
- `400` — Invalid CTC (negative or non-numeric)
- `404` — GroupName not found for this corpId / No IncludedComponents
- `500` — Internal server error

---

## Formula Builder Integration

The V1 payroll breakdown uses `formula_builders` scoped by `paygroupPuid` (not globally by `componentName`).

### Supported Formula Types:
| Formula | Description | Example |
|---|---|---|
| `percent` | Percentage of a reference component | `50% of Basic` |
| `fixed` | Fixed monetary amount | `Fixed: ₹1500.00` |
| `variable` | Manually entered variable amount | Returns 0 |

### Basic Salary Calculation Priority:
1. If a `formula_builder` entry exists for `Basic` with `componentNameRefersTo = "CTC"` and `formula = "percent"` → uses that percentage of CTC
2. If a `formula_builder` entry exists for `Basic` with `formula = "fixed"` → uses the fixed amount
3. **Default fallback:** Basic = 40% of CTC

---

## Related API: Formula Builder

### Add or Update Formula Builder
**POST** `/api/formula-builder/add-or-update`

```json
{
    "corpId": "TESTCORP",
    "puid": "FB-T-001",
    "paygroupPuid": "PGV1-B8ESLN9N",
    "componentGroupName": "SalaryGroup1",
    "componentName": "HRA",
    "componentNameRefersTo": "Basic",
    "referenceValue": "50",
    "formula": "percent"
}
```

### Get Formula Builders by corpId and Group
**GET** `/api/formula-builder/{corpId}/{componentGroupName}`

### Delete Formula Builder
**DELETE** `/api/formula-builder/delete/{puid}/{paygroupPuid}`

---

## Complete API Route Summary

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/paygroupconfigurationv1/add-or-update` | Create/update paygroup config v1 |
| GET | `/api/paygroupconfigurationv1/all/{corpId}` | Get all configs by corpId |
| DELETE | `/api/paygroupconfigurationv1/delete/{puid}` | Delete config + related formula builders |
| GET | `/api/paygroupv1/groupnames/{corpId}` | Get group names by corpId |
| GET | `/api/paygroupv1/complete-improved/{groupName}/{corpId}/{ctc}` | Complete payroll breakdown |
