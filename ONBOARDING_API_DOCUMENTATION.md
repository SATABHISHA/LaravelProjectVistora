# Onboarding Process API Documentation

> Full Recruitment-to-Onboarding API — Job Postings, Candidates, Applications, Interview Stages & Remarks, Selection/Rejection, Offer Letter Templates (with logo, digital signature, salary structure), and Offer Letter Generation (PDF).

**Base URL:** `http://your-domain/api`

---

## Table of Contents

1. [Database Tables](#1-database-tables)
2. [Recruitment Job Postings](#2-recruitment-job-postings)
3. [Recruitment Candidates](#3-recruitment-candidates)
4. [Recruitment Stages](#4-recruitment-stages)
5. [Recruitment Applications](#5-recruitment-applications)
6. [Interview Stage Results & Remarks](#6-interview-stage-results--remarks)
7. [Selection / Rejection](#7-selection--rejection)
8. [Offer Letter Templates](#8-offer-letter-templates)
9. [Offer Letters](#9-offer-letters)
10. [Complete Workflow Example](#10-complete-workflow-example)

---

## 1. Database Tables

| Table | Purpose |
|-------|---------|
| `recruitment_job_postings` | Job openings created by the company |
| `recruitment_candidates` | Candidate profiles with resume upload |
| `recruitment_applications` | Links a candidate to a job posting — tracks status through the pipeline |
| `recruitment_stages` | Configurable interview stages (Screening, Technical Round, HR Round, etc.) |
| `recruitment_stage_results` | Per-stage interview results/remarks for each application |
| `offer_letter_templates` | Admin-configurable templates with company logo, digital signature, salary structure, content |
| `offer_letters` | Generated offer letters referencing a template, candidate, and application |

---

## 2. Recruitment Job Postings

### 2.1 Create Job Posting

```
POST /api/recruitment/job-postings
```

**Request Body (JSON):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `corp_id` | string | Yes | Corporate/company ID |
| `job_title` | string | Yes | Title of the position |
| `department` | string | No | Department name |
| `sub_department` | string | No | Sub-department |
| `designation` | string | No | Designation offered |
| `location` | string | No | Work location |
| `employment_type` | string | No | Full-time, Part-time, Contract |
| `no_of_openings` | integer | No | Number of positions (default: 1) |
| `job_description` | text | No | Detailed description |
| `requirements` | text | No | Skill/qualification requirements |
| `min_salary` | decimal | No | Minimum salary |
| `max_salary` | decimal | No | Maximum salary |
| `currency` | string | No | Salary currency (INR, USD, etc.) |
| `application_deadline` | date | No | Last date to apply |
| `status` | string | No | Open, Closed, On Hold (default: Open) |
| `created_by` | string | No | Creator identifier |

**Example Request:**

```json
{
    "corp_id": "CORP01",
    "job_title": "Software Engineer",
    "department": "Engineering",
    "designation": "Senior Developer",
    "employment_type": "Full-time",
    "no_of_openings": 3,
    "job_description": "Develop and maintain web applications",
    "min_salary": 800000,
    "max_salary": 1500000,
    "currency": "INR",
    "status": "Open",
    "created_by": "admin"
}
```

**Response (201):**

```json
{
    "status": true,
    "message": "Job posting created.",
    "data": {
        "id": 1,
        "corp_id": "CORP01",
        "job_title": "Software Engineer",
        "department": "Engineering",
        "designation": "Senior Developer",
        "employment_type": "Full-time",
        "no_of_openings": 3,
        "status": "Open",
        "created_at": "2026-02-25T01:41:39.000000Z",
        "updated_at": "2026-02-25T01:41:39.000000Z"
    }
}
```

### 2.2 List All Job Postings

```
GET /api/recruitment/job-postings/{corp_id}
```

**Response (200):** `{ "status": true, "data": [ ... ] }`

### 2.3 Get Single Job Posting

```
GET /api/recruitment/job-postings/{corp_id}/{id}
```

**Response (200):** `{ "status": true, "data": { ... } }`

### 2.4 Update Job Posting

```
PUT /api/recruitment/job-postings/{corp_id}/{id}
```

Send any fields from the create request that need updating.

### 2.5 Delete Job Posting

```
DELETE /api/recruitment/job-postings/{corp_id}/{id}
```

**Response:** `{ "status": true, "message": "Job posting deleted." }`

### 2.6 Change Job Posting Status

```
PATCH /api/recruitment/job-postings/{corp_id}/{id}/status
```

**Request Body:**

```json
{
    "status": "Closed"
}
```

Valid values: `Open`, `Closed`, `On Hold`

---

## 3. Recruitment Candidates

### 3.1 Create Candidate

```
POST /api/recruitment/candidates
```

**Content-Type:** `multipart/form-data` (if uploading resume) or `application/json`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `corp_id` | string | Yes | Corporate ID |
| `first_name` | string | Yes | First name |
| `last_name` | string | No | Last name |
| `email` | string (email) | No | Email address |
| `phone` | string | No | Phone number |
| `dob` | date | No | Date of birth |
| `gender` | string | No | Male, Female, Other |
| `current_location` | string | No | Current city |
| `highest_qualification` | string | No | Highest degree |
| `total_experience_years` | integer | No | Years of experience |
| `current_ctc` | decimal | No | Current annual CTC |
| `expected_ctc` | decimal | No | Expected CTC |
| `notice_period` | string | No | e.g., "30 days" |
| `resume` | file | No | PDF/DOC/DOCX (max 5MB) |
| `linkedin_url` | string | No | LinkedIn profile URL |
| `source` | string | No | Referral, LinkedIn, Job Portal, Walk-in |
| `referred_by` | string | No | Referrer name/code |
| `skills` | text | No | Comma-separated skills |

**Example Request:**

```json
{
    "corp_id": "CORP01",
    "first_name": "Rahul",
    "last_name": "Sharma",
    "email": "rahul.sharma@email.com",
    "phone": "9876543210",
    "gender": "Male",
    "highest_qualification": "B.Tech Computer Science",
    "total_experience_years": 5,
    "current_ctc": 700000,
    "expected_ctc": 1200000,
    "notice_period": "30 days",
    "source": "LinkedIn"
}
```

### 3.2 List All Candidates

```
GET /api/recruitment/candidates/{corp_id}
```

### 3.3 Get Single Candidate

```
GET /api/recruitment/candidates/{corp_id}/{id}
```

### 3.4 Update Candidate

```
POST /api/recruitment/candidates/{corp_id}/{id}
```

> **Note:** Uses POST (not PUT) to support `multipart/form-data` for resume upload.

### 3.5 Delete Candidate

```
DELETE /api/recruitment/candidates/{corp_id}/{id}
```

### 3.6 Download Resume

```
GET /api/recruitment/candidates/{corp_id}/{id}/resume
```

Returns the resume file as a download.

---

## 4. Recruitment Stages

Stages define the steps in your hiring pipeline (e.g., Screening → Technical Round 1 → HR Round → Final).

### 4.1 Create Stage

```
POST /api/recruitment/stages
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `corp_id` | string | Yes | Corporate ID |
| `stage_name` | string | Yes | e.g., "Technical Round 1" |
| `stage_order` | integer | No | Ordering (1, 2, 3…) |
| `stage_type` | string | No | Telephonic, Video, Face-to-Face, Assessment |
| `description` | text | No | Stage instructions |
| `is_active` | integer | No | 1 = active, 0 = inactive |

**Example Request:**

```json
{
    "corp_id": "CORP01",
    "stage_name": "Technical Round 1",
    "stage_order": 2,
    "stage_type": "Video",
    "description": "Coding and problem-solving interview"
}
```

### 4.2 List All Stages

```
GET /api/recruitment/stages/{corp_id}
```

Returns stages ordered by `stage_order`.

### 4.3 Get Single Stage

```
GET /api/recruitment/stages/{corp_id}/{id}
```

### 4.4 Update Stage

```
PUT /api/recruitment/stages/{corp_id}/{id}
```

### 4.5 Delete Stage

```
DELETE /api/recruitment/stages/{corp_id}/{id}
```

---

## 5. Recruitment Applications

An application links a candidate to a job posting and tracks their journey through the pipeline.

### 5.1 Create Application

```
POST /api/recruitment/applications
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `corp_id` | string | Yes | Corporate ID |
| `job_posting_id` | integer | Yes | FK to `recruitment_job_postings` |
| `candidate_id` | integer | Yes | FK to `recruitment_candidates` |
| `applied_date` | date | No | Defaults to today |
| `current_stage` | string | No | Name of current stage |
| `status` | string | No | Default: Applied |

**Application Status Values:** `Applied`, `In Progress`, `Selected`, `Rejected`, `On Hold`, `Offer Sent`, `Joined`

**Example Request:**

```json
{
    "corp_id": "CORP01",
    "job_posting_id": 1,
    "candidate_id": 1,
    "applied_date": "2026-02-25"
}
```

> **Duplicate check:** A candidate cannot apply to the same job twice — returns 409.

### 5.2 List Applications

```
GET /api/recruitment/applications/{corp_id}
```

**Optional Query Parameters:**

| Parameter | Description |
|-----------|-------------|
| `job_posting_id` | Filter by specific job posting |
| `status` | Filter by status (e.g., `Selected`) |

Returns applications with `candidate` and `jobPosting` relationships loaded.

### 5.3 Get Single Application (with Stage Results)

```
GET /api/recruitment/applications/{corp_id}/{id}
```

Returns the application with all `stageResults`, `candidate`, and `jobPosting` included.

### 5.4 Update Application

```
PUT /api/recruitment/applications/{corp_id}/{id}
```

### 5.5 Delete Application

```
DELETE /api/recruitment/applications/{corp_id}/{id}
```

---

## 6. Interview Stage Results & Remarks

Track each interview stage's outcome, rating, and remarks for an application.

### 6.1 Add Stage Result

```
POST /api/recruitment/applications/{corp_id}/{application_id}/stage-results
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `stage_id` | integer | Yes | FK to `recruitment_stages` |
| `stage_name` | string | No | Friendly name of the stage |
| `scheduled_at` | datetime | No | When the interview is scheduled |
| `conducted_at` | datetime | No | When it was actually conducted |
| `interviewer_emp_code` | string | No | Interviewer's employee code |
| `interviewer_name` | string | No | Interviewer's name |
| `remarks` | text | No | Detailed feedback/remarks |
| `rating` | integer | No | Rating 1–10 |
| `outcome` | string | No | Pass, Fail, On Hold, No Show |

**Example Request:**

```json
{
    "stage_id": 1,
    "stage_name": "Technical Round 1",
    "scheduled_at": "2026-02-26 10:00:00",
    "conducted_at": "2026-02-26 10:30:00",
    "interviewer_name": "Priya Singh",
    "interviewer_emp_code": "EMP001",
    "remarks": "Excellent coding skills, strong in Data Structures & Algorithms. Good communication.",
    "rating": 9,
    "outcome": "Pass"
}
```

**Response (201):**

```json
{
    "status": true,
    "message": "Stage result added.",
    "data": {
        "id": 1,
        "corp_id": "CORP01",
        "application_id": 1,
        "stage_id": 1,
        "stage_name": "Technical Round 1",
        "interviewer_name": "Priya Singh",
        "remarks": "Excellent coding skills...",
        "rating": 9,
        "outcome": "Pass"
    }
}
```

### 6.2 List Stage Results for an Application

```
GET /api/recruitment/applications/{corp_id}/{application_id}/stage-results
```

Returns all stage results ordered chronologically.

### 6.3 Update Stage Result

```
PUT /api/recruitment/applications/{corp_id}/{application_id}/stage-results/{result_id}
```

### 6.4 Delete Stage Result

```
DELETE /api/recruitment/applications/{corp_id}/{application_id}/stage-results/{result_id}
```

---

## 7. Selection / Rejection

### 7.1 Decide on a Candidate

```
PATCH /api/recruitment/applications/{corp_id}/{id}/decide
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `final_decision` | string | Yes | `Selected` or `Rejected` |
| `decided_by` | string | Yes | Name/code of the decision-maker |
| `overall_remarks` | text | No | Final remarks |

**Select Example:**

```json
{
    "final_decision": "Selected",
    "decided_by": "HR_MANAGER",
    "overall_remarks": "Strong technical and communication skills. Recommended for hire."
}
```

**Reject Example:**

```json
{
    "final_decision": "Rejected",
    "decided_by": "TECH_LEAD",
    "overall_remarks": "Performance below expectations in technical rounds."
}
```

**Response:**

```json
{
    "status": true,
    "message": "Candidate Selected successfully.",
    "data": {
        "id": 1,
        "status": "Selected",
        "final_decision": "Selected",
        "decided_by": "HR_MANAGER",
        "decision_date": "2026-02-25T01:42:30.000000Z"
    }
}
```

---

## 8. Offer Letter Templates

Admin-configurable templates with company logo, digital signature, salary structure definition, and content sections.

### 8.1 Create Template

```
POST /api/offer-letter-templates
```

**Content-Type:** `multipart/form-data`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `corp_id` | string | Yes | Corporate ID |
| `template_name` | string | Yes | Name of the template |
| `header_content` | text | No | Opening paragraph (intro) |
| `body_content` | text | No | Main body text |
| `footer_content` | text | No | Terms & conditions / closing |
| `company_logo` | file | No | PNG/JPG/JPEG/SVG (max 2MB) |
| `digital_signature` | file | No | PNG/JPG/JPEG (max 2MB) |
| `signatory_name` | string | No | Signing authority name |
| `signatory_designation` | string | No | Signing authority title |
| `salary_currency` | string | No | e.g., "INR" |
| `salary_components` | JSON string | No | Salary structure definition |
| `salary_notes` | text | No | Notes about the salary |

#### Salary Components Format

```json
[
    { "component": "Basic Salary", "calc_type": "percentage", "value": 40 },
    { "component": "HRA", "calc_type": "percentage", "value": 20 },
    { "component": "Special Allowance", "calc_type": "percentage", "value": 30 },
    { "component": "PF (Employer)", "calc_type": "percentage", "value": 5 },
    { "component": "Medical Allowance", "calc_type": "fixed", "value": 1250 }
]
```

- **`calc_type: "percentage"`** — the `value` is a percentage of the annual CTC
- **`calc_type: "fixed"`** — the `value` is a fixed monthly amount

**Example Request Body:**

```json
{
    "corp_id": "CORP01",
    "template_name": "Standard Offer Template",
    "header_content": "We are pleased to offer you the position of {{designation}} at our company.",
    "body_content": "Your employment will be governed by the terms and conditions stated herein. This offer is contingent upon successful completion of background verification.",
    "footer_content": "Please sign and return this letter within 7 days. We look forward to welcoming you to our team.",
    "signatory_name": "Rajesh Kumar",
    "signatory_designation": "Chief HR Officer",
    "salary_currency": "INR",
    "salary_components": "[{\"component\":\"Basic Salary\",\"calc_type\":\"percentage\",\"value\":40},{\"component\":\"HRA\",\"calc_type\":\"percentage\",\"value\":20},{\"component\":\"Special Allowance\",\"calc_type\":\"percentage\",\"value\":30}]",
    "salary_notes": "Salary review will take place after probation period."
}
```

### 8.2 List Templates

```
GET /api/offer-letter-templates/{corp_id}
```

### 8.3 Get Single Template

```
GET /api/offer-letter-templates/{corp_id}/{id}
```

Returns the template with `company_logo_url` and `digital_signature_url` public URLs appended.

### 8.4 Update Template

```
POST /api/offer-letter-templates/{corp_id}/{id}
```

> Uses POST for `multipart/form-data` support (logo + signature files).

### 8.5 Delete Template

```
DELETE /api/offer-letter-templates/{corp_id}/{id}
```

Deletes the template and removes stored logo/signature files.

### 8.6 Upload Company Logo

```
POST /api/offer-letter-templates/{corp_id}/{id}/upload-logo
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `company_logo` | file | Yes | PNG/JPG/JPEG/SVG (max 2MB) |

**Response:**

```json
{
    "status": true,
    "message": "Company logo uploaded.",
    "url": "http://your-domain/storage/offer_letter_templates/CORP01/logos/abc.png"
}
```

### 8.7 Upload Digital Signature

```
POST /api/offer-letter-templates/{corp_id}/{id}/upload-signature
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `digital_signature` | file | Yes | PNG/JPG/JPEG (max 2MB) |

**Response:**

```json
{
    "status": true,
    "message": "Digital signature uploaded.",
    "url": "http://your-domain/storage/offer_letter_templates/CORP01/signatures/xyz.png"
}
```

---

## 9. Offer Letters

### 9.1 Generate Offer Letter

Generates an offer letter for a **Selected** candidate using a chosen template.

```
POST /api/offer-letters/generate
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `corp_id` | string | Yes | Corporate ID |
| `application_id` | integer | Yes | FK to `recruitment_applications` (must be Selected) |
| `template_id` | integer | Yes | FK to `offer_letter_templates` |
| `date_of_joining` | date | Yes | Proposed joining date |
| `ctc_annual` | decimal | Yes | Annual CTC offered |
| `designation` | string | No | Override (defaults from job posting) |
| `department` | string | No | Override (defaults from job posting) |
| `location` | string | No | Override (defaults from job posting) |
| `candidate_name` | string | No | Override (defaults from candidate record) |
| `generated_by` | string | No | Who generated it |

**Example Request:**

```json
{
    "corp_id": "CORP01",
    "application_id": 1,
    "template_id": 1,
    "date_of_joining": "2026-03-15",
    "ctc_annual": 1200000,
    "generated_by": "HR_MANAGER"
}
```

**Response (201):**

```json
{
    "status": true,
    "message": "Offer letter generated successfully.",
    "data": {
        "id": 1,
        "offer_reference_no": "OL-CORP01-20260225-3000",
        "candidate_name": "Rahul Sharma",
        "designation": "Senior Developer",
        "department": "Engineering",
        "location": "Bangalore",
        "date_of_joining": "2026-03-15",
        "ctc_annual": 1200000,
        "salary_breakdown": [
            { "component": "Basic Salary", "calc_type": "percentage", "calc_display": "40% of CTC", "monthly": 40000.00, "annual": 480000.00 },
            { "component": "HRA", "calc_type": "percentage", "calc_display": "20% of CTC", "monthly": 20000.00, "annual": 240000.00 },
            { "component": "Special Allowance", "calc_type": "percentage", "calc_display": "30% of CTC", "monthly": 30000.00, "annual": 360000.00 }
        ],
        "status": "Draft"
    }
}
```

> **Auto-calculated salary breakdown:** The system computes monthly and annual amounts from the template's salary components + CTC.

### 9.2 List All Offer Letters

```
GET /api/offer-letters/{corp_id}
```

Returns offer letters with `candidate` and `application.jobPosting` loaded.

### 9.3 Get Single Offer Letter

```
GET /api/offer-letters/{corp_id}/{id}
```

### 9.4 Preview Offer Letter (HTML)

```
GET /api/offer-letters/{corp_id}/{id}/preview
```

Returns the rendered HTML for browser preview.

```json
{
    "status": true,
    "html": "<!DOCTYPE html><html>..."
}
```

### 9.5 Download Offer Letter as PDF

```
GET /api/offer-letters/{corp_id}/{id}/download-pdf
```

Returns a downloadable PDF file. The PDF includes:
- **Company Logo** (from template)
- **Candidate & position details**
- **Salary structure table** with monthly and annual breakdown
- **Template body content** (terms, conditions)
- **Digital Signature** (from template) alongside candidate signature block
- **Offer reference number** and date

The PDF is also saved to storage (`storage/app/public/offer_letters/{corp_id}/`) and the letter status is updated to `Sent`.

### 9.6 Update Offer Letter Status

```
PATCH /api/offer-letters/{corp_id}/{id}/status
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `status` | string | Yes | `Draft`, `Sent`, `Accepted`, `Declined`, `Revoked` |

When status is set to `Accepted`, the linked application status is automatically updated to `Joined`.
When status is set to `Declined`, the linked application status is automatically updated to `Rejected`.

**Example:**

```json
{
    "status": "Accepted"
}
```

### 9.7 Delete Offer Letter

```
DELETE /api/offer-letters/{corp_id}/{id}
```

Deletes the offer letter record and its stored PDF.

---

## 10. Complete Workflow Example

Here is the full end-to-end recruitment-to-onboarding flow:

### Step 1: Setup Stages (one-time)

```
POST /api/recruitment/stages
{"corp_id":"CORP01", "stage_name":"Screening", "stage_order":1, "stage_type":"Telephonic"}

POST /api/recruitment/stages
{"corp_id":"CORP01", "stage_name":"Technical Round", "stage_order":2, "stage_type":"Video"}

POST /api/recruitment/stages
{"corp_id":"CORP01", "stage_name":"HR Round", "stage_order":3, "stage_type":"Face-to-Face"}
```

### Step 2: Create Job Posting

```
POST /api/recruitment/job-postings
{"corp_id":"CORP01", "job_title":"Software Engineer", "department":"Engineering", "designation":"Senior Developer"}
```

### Step 3: Add Candidate

```
POST /api/recruitment/candidates
(multipart/form-data with resume file)
```

### Step 4: Create Application

```
POST /api/recruitment/applications
{"corp_id":"CORP01", "job_posting_id":1, "candidate_id":1}
```

### Step 5: Conduct Interviews & Add Remarks

```
POST /api/recruitment/applications/CORP01/1/stage-results
{"stage_id":1, "stage_name":"Screening", "interviewer_name":"HR Team", "remarks":"Good communication, shortlist for next round", "rating":7, "outcome":"Pass"}

POST /api/recruitment/applications/CORP01/1/stage-results
{"stage_id":2, "stage_name":"Technical Round", "interviewer_name":"Priya Singh", "remarks":"Strong coding skills", "rating":9, "outcome":"Pass"}

POST /api/recruitment/applications/CORP01/1/stage-results
{"stage_id":3, "stage_name":"HR Round", "interviewer_name":"Rajesh Kumar", "remarks":"Good cultural fit, salary expectations aligned", "rating":8, "outcome":"Pass"}
```

### Step 6: Select Candidate

```
PATCH /api/recruitment/applications/CORP01/1/decide
{"final_decision":"Selected", "decided_by":"HR_MANAGER", "overall_remarks":"Recommended for hire"}
```

### Step 7: Setup Offer Letter Template (one-time)

```
POST /api/offer-letter-templates
(multipart/form-data with company_logo, digital_signature files, salary_components JSON)
```

Upload logo & signature separately if needed:
```
POST /api/offer-letter-templates/CORP01/1/upload-logo
POST /api/offer-letter-templates/CORP01/1/upload-signature
```

### Step 8: Generate Offer Letter

```
POST /api/offer-letters/generate
{"corp_id":"CORP01", "application_id":1, "template_id":1, "date_of_joining":"2026-03-15", "ctc_annual":1200000}
```

### Step 9: Preview & Download PDF

```
GET /api/offer-letters/CORP01/1/preview       → HTML preview
GET /api/offer-letters/CORP01/1/download-pdf   → PDF download (with logo + signature + salary table)
```

### Step 10: Update Status After Candidate Response

```
PATCH /api/offer-letters/CORP01/1/status
{"status":"Accepted"}
```

---

## Offer Letter PDF Includes

| Section | Description |
|---------|-------------|
| **Company Logo** | Uploaded via template settings, rendered in the header |
| **Offer Reference & Date** | Auto-generated reference number + current date |
| **Header Content** | Configurable intro paragraph from template |
| **Candidate Details** | Name, designation, department, location, DOJ |
| **Body Content** | Configurable main content from template |
| **Salary Structure Table** | Component-wise breakdown — monthly & annual — auto-calculated from CTC |
| **Footer Content** | T&C / closing from template |
| **Digital Signature** | Uploaded signature image + signatory name & designation |
| **Candidate Signature Block** | Space for candidate to sign |

---

## Error Responses

| Code | Meaning |
|------|---------|
| `200` | Success |
| `201` | Created |
| `404` | Resource not found |
| `409` | Duplicate / conflict |
| `422` | Validation error |
| `500` | Server error |

**Validation Error Example:**

```json
{
    "status": false,
    "errors": {
        "corp_id": ["The corp id field is required."],
        "job_title": ["The job title field is required."]
    }
}
```

---

## Files Created

### Migrations (7 tables)
- `database/migrations/2026_02_25_000001_create_recruitment_job_postings_table.php`
- `database/migrations/2026_02_25_000002_create_recruitment_candidates_table.php`
- `database/migrations/2026_02_25_000003_create_recruitment_applications_table.php`
- `database/migrations/2026_02_25_000004_create_recruitment_stages_table.php`
- `database/migrations/2026_02_25_000005_create_recruitment_stage_results_table.php`
- `database/migrations/2026_02_25_000006_create_offer_letter_templates_table.php`
- `database/migrations/2026_02_25_000007_create_offer_letters_table.php`

### Models (7)
- `app/Models/RecruitmentJobPosting.php`
- `app/Models/RecruitmentCandidate.php`
- `app/Models/RecruitmentApplication.php`
- `app/Models/RecruitmentStage.php`
- `app/Models/RecruitmentStageResult.php`
- `app/Models/OfferLetterTemplate.php`
- `app/Models/OfferLetter.php`

### Controllers (6)
- `app/Http/Controllers/RecruitmentJobPostingApiController.php`
- `app/Http/Controllers/RecruitmentCandidateApiController.php`
- `app/Http/Controllers/RecruitmentApplicationApiController.php`
- `app/Http/Controllers/RecruitmentStageApiController.php`
- `app/Http/Controllers/OfferLetterTemplateApiController.php`
- `app/Http/Controllers/OfferLetterApiController.php`

### Views
- `resources/views/offer_letter/template.blade.php` — Offer letter PDF/HTML template

### Routes
All routes added to `routes/api.php` under the Onboarding section (40+ endpoints).
