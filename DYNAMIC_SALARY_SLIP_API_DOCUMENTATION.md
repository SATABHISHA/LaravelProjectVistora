# Dynamic Salary Slip Download API Documentation

> **Version:** 1.0  
> **Last Updated:** February 27, 2026

## Overview

These APIs allow downloading salary slip PDFs for **any company** by passing the company name dynamically. Unlike the existing custom PDF endpoints (which are tied to IMS MACO defaults), these endpoints have **no hardcoded company fallbacks** — the company name is always **required** and all company details are sourced from the database.

### Existing (IMS MACO) vs Dynamic APIs

| Feature | Existing Custom PDF APIs | New Dynamic PDF APIs |
|---|---|---|
| Company Name | Optional / defaults to MACO | **Required** — fully dynamic |
| Template | `salary-slip-custom-pdf` (MACO defaults) | `salary-slip-dynamic-pdf` (no defaults) |
| Single Endpoint | `/api/salary-slip/download-custom-pdf/...` | `/api/salary-slip/download-dynamic-pdf/...` |
| Bulk Endpoint | `/api/salary-slips/download-all-custom-pdf` | `/api/salary-slips/download-all-dynamic-pdf` |

---

## API 1: Download Single Employee Dynamic Salary Slip PDF

Download a salary slip PDF for a single employee with a dynamically specified company name.

### Endpoint

```
GET /api/salary-slip/download-dynamic-pdf/{corpId}/{empCode}/{year}/{month}/{companyName}
```

### URL Parameters

| Parameter | Type | Required | Description |
|---|---|---|---|
| `corpId` | string | Yes | Corporation ID (e.g., `maco`, `vistora`) |
| `empCode` | string | Yes | Employee code (e.g., `EMP001`) |
| `year` | string | Yes | Payroll year (e.g., `2025`) |
| `month` | string | Yes | Month — numeric `1`–`12` or name `January`–`December` |
| `companyName` | string | Yes | Company name exactly as stored in the payroll database |

### Example Requests

**Using month name:**
```
GET /api/salary-slip/download-dynamic-pdf/maco/EMP001/2025/January/IMS MACO SERVICES INDIA PVT. LTD.
```

**Using month number:**
```
GET /api/salary-slip/download-dynamic-pdf/vistora/V101/2025/6/VISTORA TECHNOLOGIES PVT LTD
```

**cURL example:**
```bash
curl -o Payslip_EMP001_January_2025.pdf \
  "https://your-domain.com/api/salary-slip/download-dynamic-pdf/maco/EMP001/2025/January/IMS%20MACO%20SERVICES%20INDIA%20PVT.%20LTD."
```

### Success Response

- **Status:** `200 OK`
- **Content-Type:** `application/pdf`
- **Body:** Binary PDF file streamed as download
- **Filename format:** `Payslip_{empCode}_{MonthName}_{Year}.pdf`

### Error Responses

| Status | Message |
|---|---|
| `404` | Payroll record not found for the specified employee, period, and company. |
| `500` | Error generating salary slip: `{error details}` |

---

## API 2: Download All Employees Dynamic Salary Slips (Bulk ZIP)

Download salary slip PDFs for **all employees** of a specific company for a given month/year, bundled in a ZIP file. The company name is dynamic and required.

### Endpoint

```
POST /api/salary-slips/download-all-dynamic-pdf
```

```
GET /api/salary-slips/download-all-dynamic-pdf
```

> Both `POST` and `GET` are supported. The `GET` method is provided for FlutterFlow compatibility.

### Request Body / Query Parameters

| Parameter | Type | Required | Description |
|---|---|---|---|
| `corpId` | string | Yes | Corporation ID (max 10 chars) |
| `companyName` | string | Yes | Company name exactly as stored in the payroll database (max 100 chars) |
| `year` | string | Yes | Payroll year (max 4 chars, e.g., `2025`) |
| `month` | string | Yes | Month — numeric `1`–`12` or name `January`–`December` (max 50 chars) |
| `status` | string | No | Filter by payroll status (e.g., `processed`, `released`) |

### Example Requests

**POST with JSON body:**
```bash
curl -X POST "https://your-domain.com/api/salary-slips/download-all-dynamic-pdf" \
  -H "Content-Type: application/json" \
  -d '{
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "year": "2025",
    "month": "January"
  }' \
  -o Payslips_IMS_MACO_January_2025.zip
```

**POST with a different company:**
```bash
curl -X POST "https://your-domain.com/api/salary-slips/download-all-dynamic-pdf" \
  -H "Content-Type: application/json" \
  -d '{
    "corpId": "vistora",
    "companyName": "VISTORA TECHNOLOGIES PVT LTD",
    "year": "2025",
    "month": "6",
    "status": "released"
  }' \
  -o Payslips_VISTORA_June_2025.zip
```

**GET with query parameters (FlutterFlow):**
```
GET /api/salary-slips/download-all-dynamic-pdf?corpId=vistora&companyName=VISTORA%20TECHNOLOGIES%20PVT%20LTD&year=2025&month=January
```

### Success Response

- **Status:** `200 OK`
- **Content-Type:** `application/zip`
- **Body:** ZIP file containing individual PDF salary slips for every employee
- **ZIP filename format:** `Payslips_{SafeCompanyName}_{MonthName}_{Year}.zip`
- **Individual PDF filename format:** `Payslip_{empCode}_{MonthName}_{Year}.pdf`

> Special characters in the company name are replaced with underscores (`_`) in the ZIP filename.

### Error Responses

| Status | Message |
|---|---|
| `404` | No payroll records found for the specified criteria. |
| `422` | Validation error — missing or invalid required fields. |
| `500` | Error generating bulk dynamic salary slips: `{error details}` |

### Validation Rules

```json
{
  "corpId": "required|string|max:10",
  "companyName": "required|string|max:100",
  "year": "required|string|max:4",
  "month": "required|string|max:50",
  "status": "nullable|string"
}
```

---

## Key Differences from Existing Custom PDF APIs

1. **Company name is mandatory** — it is a required URL path parameter (single) or required body parameter (bulk). There is no optional/default behavior.

2. **No hardcoded fallbacks** — the PDF template (`salary-slip-dynamic-pdf`) does not fall back to MACO Corporation defaults for company name, address, etc. If company details are not in the database, fields show as empty or "N/A".

3. **Works for any company** — simply pass the correct `corpId` and `companyName` and it will generate payslips for that company.

4. **Existing APIs are untouched** — the original custom PDF endpoints (`/download-custom-pdf` and `/download-all-custom-pdf`) continue to work exactly as before for IMS MACO.

---

## Route Summary

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/salary-slip/download-dynamic-pdf/{corpId}/{empCode}/{year}/{month}/{companyName}` | Single employee payslip PDF |
| `POST` | `/api/salary-slips/download-all-dynamic-pdf` | Bulk payslips ZIP (all employees) |
| `GET` | `/api/salary-slips/download-all-dynamic-pdf` | Bulk payslips ZIP — GET variant for FlutterFlow |

---

## Usage in FlutterFlow / Frontend

### Single Employee Payslip

Construct the URL by interpolating variables:

```
${baseUrl}/api/salary-slip/download-dynamic-pdf/${corpId}/${empCode}/${year}/${month}/${companyName}
```

> **Important:** URL-encode the `companyName` if it contains spaces or special characters (e.g., `IMS%20MACO%20SERVICES%20INDIA%20PVT.%20LTD.`).

### Bulk Payslips

Use a POST API call with JSON body:

```json
{
  "corpId": "{{corpId}}",
  "companyName": "{{selectedCompanyName}}",
  "year": "{{selectedYear}}",
  "month": "{{selectedMonth}}"
}
```

Or use the GET variant with query parameters for simpler integration.
