# Timesheet System - API Documentation

## Overview

A role-based timesheet management system with three roles: **Admin**, **Supervisor**, and **Subordinate**. Built on Laravel 10 with Sanctum token authentication and full RBAC middleware.

**Base URL:** `/api/timesheet`

---

## Table of Contents

1. [Roles & Permissions](#roles--permissions)
2. [Database Tables](#database-tables)
3. [Authentication APIs](#1-authentication)
4. [User Management APIs](#2-user-management)
5. [Project APIs](#3-projects)
6. [Task APIs](#4-tasks)
7. [Daily Report APIs](#5-daily-reports)
8. [History APIs](#6-histories)
9. [Report & KPI APIs](#7-reports--kpis)
10. [Error Handling](#error-handling)

---

## Roles & Permissions

| Feature | Admin | Supervisor | Subordinate |
|---|:---:|:---:|:---:|
| Register/Login | ✅ | ✅ | ✅ |
| View all users | ✅ | Own + subs | ❌ |
| Create projects | ✅ | ✅ | ❌ |
| Assign subordinates to projects | ✅ | Own subs only | ❌ |
| Extend project timelines | ✅ | Own projects | ❌ |
| Delete projects | ✅ | ❌ | ❌ |
| Create/assign tasks | ✅ | Own subs only | ❌ |
| Update task status | ❌ | ❌ | Own tasks only |
| Approve/reject tasks | ✅ | Own subs only | ❌ |
| Delete tasks | ✅ | ❌ | ❌ |
| Submit daily reports | ❌ | ❌ | ✅ |
| View daily reports | All | Own + subs | Own only |
| View task histories | All | Own + subs | Own only |
| View project histories | All | Own projects | Assigned projects |
| Subordinate performance | All | Own subs | Own only |
| Supervisor performance | All | Own only | ❌ |
| Organization performance | ✅ | ❌ | ❌ |
| KPI history | All | Own + subs | Own only |

---

## Database Tables

### ts_users
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| name | varchar(255) | User name |
| email | varchar(255) | Unique email |
| password | varchar(255) | Hashed password |
| role | enum | `admin`, `supervisor`, `subordinate` |
| supervisor_id | bigint (nullable) | FK to ts_users (for subordinates) |
| is_active | boolean | Account active status |
| remember_token | varchar(100) | Token for remember me |
| created_at / updated_at | timestamp | Timestamps |

### ts_projects
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| name | varchar(255) | Project name |
| description | text (nullable) | Project description |
| created_by | bigint | FK to ts_users |
| start_date | date | Project start date |
| end_date | date | Original end date |
| extended_end_date | date (nullable) | Extended deadline |
| status | enum | `active`, `completed`, `on_hold`, `cancelled` |
| created_at / updated_at | timestamp | Timestamps |

### ts_project_assignments
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| project_id | bigint | FK to ts_projects |
| user_id | bigint | FK to ts_users (subordinate) |
| assigned_by | bigint | FK to ts_users (assigner) |
| created_at / updated_at | timestamp | Timestamps |

### ts_tasks
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| project_id | bigint (nullable) | FK to ts_projects (tasks can exist without a project) |
| title | varchar(255) | Task title |
| description | text (nullable) | Task description |
| assigned_to | bigint | FK to ts_users (subordinate) |
| assigned_by | bigint | FK to ts_users (assigner) |
| status | enum | `pending`, `in_progress`, `completed`, `approved`, `rejected` |
| priority | enum | `low`, `medium`, `high`, `urgent` |
| due_date | date (nullable) | Task due date |
| completed_at | timestamp (nullable) | When marked completed |
| approved_by | bigint (nullable) | FK to ts_users |
| approved_at | timestamp (nullable) | When approved |
| rejection_reason | text (nullable) | Reason for rejection |
| created_at / updated_at | timestamp | Timestamps |

### ts_daily_reports
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| user_id | bigint | FK to ts_users |
| task_id | bigint (nullable) | FK to ts_tasks |
| report_date | date | Date of the report |
| description | text | Work description |
| hours_spent | decimal(5,2) | Hours worked |
| created_at / updated_at | timestamp | Timestamps |

### ts_task_histories
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| task_id | bigint | FK to ts_tasks |
| user_id | bigint | FK to ts_users (who performed the action) |
| action | varchar(255) | Action type (created, status_changed, approved, rejected, reassigned) |
| old_value | varchar(255) (nullable) | Previous value |
| new_value | varchar(255) (nullable) | New value |
| remarks | text (nullable) | Additional notes |
| created_at | timestamp | When the action occurred |

### ts_project_histories
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| project_id | bigint | FK to ts_projects |
| user_id | bigint | FK to ts_users |
| action | varchar(255) | Action type (created, updated_*, timeline_extended, member_added, member_removed) |
| old_value | varchar(255) (nullable) | Previous value |
| new_value | varchar(255) (nullable) | New value |
| remarks | text (nullable) | Additional notes |
| created_at | timestamp | When the action occurred |

### ts_kpis
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| user_id | bigint | FK to ts_users |
| period | varchar(7) | Period in YYYY-MM format |
| metric_name | varchar(255) | KPI metric name |
| metric_value | decimal(8,2) | Metric value (percentage) |
| calculated_at | timestamp (nullable) | When calculated |
| created_at / updated_at | timestamp | Timestamps |

---

## 1. Authentication

### POST `/auth/register`
Register a new timesheet user.

**Access:** Public

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "subordinate",
    "supervisor_id": 2
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| name | string | Yes | Max 255 chars |
| email | string | Yes | Must be unique |
| password | string | Yes | Min 6 chars |
| password_confirmation | string | Yes | Must match password |
| role | string | Yes | `admin`, `supervisor`, or `subordinate` |
| supervisor_id | integer | Conditional | Required when role is `subordinate` |

**Response (201):**
```json
{
    "success": true,
    "message": "User registered successfully.",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "subordinate",
            "supervisor_id": 2
        },
        "token": "1|abc123..."
    }
}
```

---

### POST `/auth/login`
Login an existing user.

**Access:** Public

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "subordinate",
            "supervisor_id": 2
        },
        "token": "2|def456..."
    }
}
```

---

### POST `/auth/logout`
Logout (revoke current token).

**Access:** Authenticated

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully."
}
```

---

### GET `/auth/profile`
Get current user profile with relationships.

**Access:** Authenticated

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 3,
        "name": "Subordinate One",
        "email": "sub1@example.com",
        "role": "subordinate",
        "supervisor_id": 2,
        "supervisor": {
            "id": 2,
            "name": "Supervisor One",
            "email": "sup1@example.com",
            "role": "supervisor"
        }
    }
}
```

---

## 2. User Management

### GET `/users`
List users with role-based visibility.

**Access:** Admin, Supervisor

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| role | string | Filter by role (`admin`, `supervisor`, `subordinate`) |
| per_page | integer | Items per page (default: 15) |

**Visibility Rules:**
- **Admin:** sees all users
- **Supervisor:** sees self + own subordinates

**Response (200):** Paginated list of users.

---

## 3. Projects

### GET `/projects`
List projects with role-based visibility.

**Access:** Authenticated

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| status | string | Filter by status (`active`, `completed`, `on_hold`, `cancelled`) |
| per_page | integer | Items per page (default: 15) |

**Visibility Rules:**
- **Admin:** all projects
- **Supervisor:** projects they created + projects their subordinates are assigned to
- **Subordinate:** only projects they are assigned to

**Response (200):** Paginated list of projects with creator and members.

---

### POST `/projects`
Create a new project.

**Access:** Admin, Supervisor

**Request Body:**
```json
{
    "name": "Project Alpha",
    "description": "First project description",
    "start_date": "2026-03-01",
    "end_date": "2026-04-30",
    "status": "active"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| name | string | Yes | Max 255 chars |
| description | string | No | |
| start_date | date | Yes | YYYY-MM-DD |
| end_date | date | Yes | Must be >= start_date |
| status | string | No | Default: `active` |

**Response (201):** Created project object.

---

### GET `/projects/{id}`
Show a single project with tasks, members, and history.

**Access:** Authenticated (with visibility check)

**Response (200):** Full project object with relationships.

---

### PUT `/projects/{id}`
Update a project.

**Access:** Admin or project creator (Supervisor)

**Request Body:** (any fields to update)
```json
{
    "name": "Project Alpha - Updated",
    "description": "Updated description",
    "status": "completed"
}
```

**Response (200):** Updated project object.

---

### DELETE `/projects/{id}`
Delete a project.

**Access:** Admin only

**Response (200):**
```json
{
    "success": true,
    "message": "Project deleted successfully."
}
```

---

### POST `/projects/{id}/extend-timeline`
Extend a project's deadline.

**Access:** Admin or project creator (Supervisor)

**Request Body:**
```json
{
    "extended_end_date": "2026-06-30",
    "reason": "Client requested additional features"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| extended_end_date | date | Yes | Must be after current end date |
| reason | string | Yes | Max 500 chars |

**Response (200):** Updated project object.

---

### POST `/projects/{id}/assign-member`
Assign a subordinate to a project.

**Access:** Admin or project creator (Supervisor)

**Request Body:**
```json
{
    "user_id": 3
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| user_id | integer | Yes | Must be a subordinate |

**Constraints:**
- Supervisors can only assign their own subordinates
- Duplicate assignments are prevented

**Response (200):** Updated project with members.

---

### POST `/projects/{id}/remove-member`
Remove a subordinate from a project.

**Access:** Admin or project creator (Supervisor)

**Request Body:**
```json
{
    "user_id": 3
}
```

**Response (200):** Updated project with members.

---

## 4. Tasks

### GET `/tasks`
List tasks with role-based visibility and filters.

**Access:** Authenticated

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| status | string | Filter by status |
| priority | string | Filter by priority |
| project_id | integer | Filter by project |
| assigned_to | integer | Filter by assignee |
| overdue | boolean | Show only overdue tasks |
| per_page | integer | Items per page (default: 15) |

**Visibility Rules:**
- **Admin:** all tasks
- **Supervisor:** tasks of own subordinates + tasks they assigned
- **Subordinate:** only their own tasks

**Response (200):** Paginated list of tasks.

---

### POST `/tasks`
Create and assign a task (with or without project).

**Access:** Admin, Supervisor

**Request Body:**
```json
{
    "project_id": 1,
    "title": "Design wireframes",
    "description": "Create initial wireframes for the project",
    "assigned_to": 3,
    "priority": "high",
    "due_date": "2026-03-15"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| project_id | integer | No | Nullable - tasks can exist without a project |
| title | string | Yes | Max 255 chars |
| description | string | No | |
| assigned_to | integer | Yes | Must be a subordinate |
| priority | string | No | `low`, `medium` (default), `high`, `urgent` |
| due_date | date | No | Must be today or later |

**Constraints:**
- Supervisors can only assign to their own subordinates
- Only subordinates can be assignees

**Response (201):** Created task object.

---

### GET `/tasks/{id}`
Show a single task with all relationships and history.

**Access:** Authenticated (with visibility check)

**Response (200):** Full task object with project, assignee, assigner, approver, daily reports, and histories.

---

### PUT `/tasks/{id}`
Update task details (title, description, priority, due_date, reassign).

**Access:** Admin or task creator (Supervisor)

**Request Body:** (any fields to update)
```json
{
    "title": "Design wireframes - v2",
    "priority": "urgent",
    "assigned_to": 4
}
```

**Response (200):** Updated task object.

---

### DELETE `/tasks/{id}`
Delete a task.

**Access:** Admin only

**Response (200):**
```json
{
    "success": true,
    "message": "Task deleted successfully."
}
```

---

### PATCH `/tasks/{id}/status`
Update task status (subordinate marks progress).

**Access:** Subordinate (assigned to this task only)

**Request Body:**
```json
{
    "status": "in_progress",
    "remarks": "Starting work on this task"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| status | string | Yes | `in_progress` or `completed` |
| remarks | string | No | Optional notes |

**Allowed Status Transitions:**
| From | To |
|---|---|
| pending | in_progress |
| in_progress | completed |
| rejected | in_progress |

**Response (200):** Updated task object.

---

### POST `/tasks/{id}/approve`
Approve a completed task.

**Access:** Admin, Supervisor (own subordinates only)

**Request Body:**
```json
{
    "remarks": "Great work!"
}
```

**Constraints:**
- Only tasks with status `completed` can be approved
- Supervisors can only approve their own subordinates' tasks

**Response (200):** Updated task with approver details.

---

### POST `/tasks/{id}/reject`
Reject a completed task (sends it back for rework).

**Access:** Admin, Supervisor (own subordinates only)

**Request Body:**
```json
{
    "rejection_reason": "Need more test coverage"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| rejection_reason | string | Yes | Max 500 chars |

**Constraints:**
- Only tasks with status `completed` can be rejected
- After rejection, subordinate can change status: rejected → in_progress → completed

**Response (200):** Updated task object.

---

## 5. Daily Reports

### GET `/daily-reports`
List daily reports with role-based visibility.

**Access:** Authenticated

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| user_id | integer | Filter by user |
| task_id | integer | Filter by task |
| report_date | date | Filter by exact date |
| date_from | date | Filter from date |
| date_to | date | Filter to date |
| per_page | integer | Items per page (default: 15) |

**Visibility Rules:**
- **Admin:** all reports
- **Supervisor:** own + subordinates' reports
- **Subordinate:** own reports only

**Response (200):** Paginated list of daily reports.

---

### POST `/daily-reports`
Submit a daily report.

**Access:** Subordinate only

**Request Body:**
```json
{
    "task_id": 1,
    "report_date": "2026-02-28",
    "description": "Worked on wireframe designs for 6 hours",
    "hours_spent": 6
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| task_id | integer | No | Must be assigned to this user |
| report_date | date | Yes | Must be today or earlier |
| description | string | Yes | Work description |
| hours_spent | number | Yes | Min: 0.25, Max: 24 |

**Constraints:**
- Duplicate reports (same user + task + date) are prevented
- task_id (if provided) must be assigned to the reporting user

**Response (201):** Created report object.

---

### GET `/daily-reports/{id}`
Show a single daily report.

**Access:** Authenticated (with visibility check)

**Response (200):** Report object with user and task details.

---

### PUT `/daily-reports/{id}`
Update a daily report.

**Access:** Subordinate (own reports only)

**Request Body:**
```json
{
    "description": "Updated description",
    "hours_spent": 7
}
```

**Response (200):** Updated report object.

---

### DELETE `/daily-reports/{id}`
Delete a daily report.

**Access:** Admin or Subordinate (own reports)

**Response (200):**
```json
{
    "success": true,
    "message": "Daily report deleted successfully."
}
```

---

## 6. Histories

### GET `/histories/tasks`
Get task change history with audit trail.

**Access:** Authenticated

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| task_id | integer | Filter by task |
| action | string | Filter by action type |
| user_id | integer | Filter by actor |
| date_from | date | Filter from date |
| date_to | date | Filter to date |
| per_page | integer | Items per page (default: 15) |

**Action Types:** `created`, `status_changed`, `approved`, `rejected`, `reassigned`, `updated_*`

**Visibility Rules:**
- **Admin:** all histories
- **Supervisor:** histories of own subordinates' tasks + tasks they assigned
- **Subordinate:** only their own task histories

**Response (200):** Paginated list of task history entries.

---

### GET `/histories/projects`
Get project change history with audit trail.

**Access:** Authenticated

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| project_id | integer | Filter by project |
| action | string | Filter by action type |
| date_from | date | Filter from date |
| date_to | date | Filter to date |
| per_page | integer | Items per page (default: 15) |

**Action Types:** `created`, `updated_name`, `updated_description`, `updated_status`, `timeline_extended`, `member_added`, `member_removed`

**Visibility Rules:**
- **Admin:** all histories
- **Supervisor:** histories of own projects + projects with their subordinates
- **Subordinate:** histories of projects they're assigned to

**Response (200):** Paginated list of project history entries.

---

## 7. Reports & KPIs

### GET `/reports/subordinate-performance`
Get performance report for a subordinate.

**Access:** Authenticated

**Query Parameters:**
| Param | Type | Required | Description |
|---|---|---|---|
| user_id | integer | No | Target user (defaults to self) |
| period | string | No | YYYY-MM format (defaults to current month) |

**Visibility Rules:**
- **Admin:** any subordinate
- **Supervisor:** own subordinates
- **Subordinate:** self only

**Response (200):**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 3,
            "name": "Subordinate One",
            "email": "sub1@example.com",
            "role": "subordinate"
        },
        "period": "2026-02",
        "kpis": {
            "task_completion_rate": 50.00,
            "approval_rate": 100.00,
            "reporting_consistency": 5.00,
            "on_time_completion_rate": 100.00
        },
        "summary": {
            "total_tasks": 2,
            "completed_tasks": 1,
            "approved_tasks": 1,
            "rejected_tasks": 0,
            "overdue_tasks": 0,
            "total_hours_logged": 9.00,
            "avg_hours_per_day": 9.00,
            "working_days": 20,
            "days_reported": 1
        }
    }
}
```

**KPI Definitions:**
| KPI | Description |
|---|---|
| task_completion_rate | % of assigned tasks that are completed or approved |
| approval_rate | % of completed tasks that were approved |
| reporting_consistency | % of working days with at least one daily report |
| on_time_completion_rate | % of tasks completed before or on due date |

---

### GET `/reports/supervisor-performance`
Get performance report for a supervisor (with team breakdown).

**Access:** Admin, Supervisor

**Query Parameters:**
| Param | Type | Required | Description |
|---|---|---|---|
| user_id | integer | No | Target supervisor (defaults to self) |
| period | string | No | YYYY-MM format (defaults to current month) |

**Visibility Rules:**
- **Admin:** any supervisor
- **Supervisor:** self only

**Response (200):**
```json
{
    "success": true,
    "data": {
        "supervisor": {
            "id": 2,
            "name": "Supervisor One",
            "email": "sup1@example.com",
            "role": "supervisor"
        },
        "period": "2026-02",
        "kpis": {
            "project_delivery_rate": 0.00,
            "on_time_delivery_rate": 0.00,
            "team_productivity": 66.67
        },
        "summary": {
            "total_projects": 1,
            "completed_projects": 0,
            "total_subordinates": 2,
            "team_total_tasks": 3,
            "team_completed_tasks": 2,
            "team_approved_tasks": 2,
            "team_total_hours": 17.00,
            "avg_approval_time_days": 0.00
        },
        "subordinate_breakdown": [
            {
                "user": { "id": 3, "name": "Sub One", "email": "..." },
                "total_tasks": 2,
                "completed_tasks": 1,
                "completion_rate": 50.00,
                "hours_logged": 9.00
            },
            {
                "user": { "id": 4, "name": "Sub Two", "email": "..." },
                "total_tasks": 1,
                "completed_tasks": 1,
                "completion_rate": 100.00,
                "hours_logged": 8.00
            }
        ]
    }
}
```

**Supervisor KPIs:**
| KPI | Description |
|---|---|
| project_delivery_rate | % of projects completed in the period |
| on_time_delivery_rate | % of completed projects delivered on/before deadline |
| team_productivity | % of team tasks completed/approved |

---

### GET `/reports/organization-performance`
Admin-only: Organization-wide efficiency report with supervisor comparisons.

**Access:** Admin only

**Query Parameters:**
| Param | Type | Required | Description |
|---|---|---|---|
| period | string | No | YYYY-MM format (defaults to current month) |

**Response (200):**
```json
{
    "success": true,
    "data": {
        "period": "2026-02",
        "kpis": {
            "organization_efficiency": 66.67,
            "task_completion_rate": 66.67,
            "project_completion_rate": 0.00
        },
        "summary": {
            "total_tasks": 3,
            "completed_tasks": 2,
            "approved_tasks": 2,
            "overdue_tasks": 0,
            "total_projects": 2,
            "completed_projects": 0,
            "total_hours_logged": 17.00,
            "active_subordinates": 2,
            "active_supervisors": 1
        },
        "supervisor_comparisons": [
            {
                "supervisor": { "id": 2, "name": "Supervisor One", "email": "..." },
                "subordinate_count": 2,
                "total_tasks": 3,
                "completed_tasks": 2,
                "team_productivity": 66.67,
                "total_projects": 1,
                "completed_projects": 0,
                "team_hours_logged": 17.00
            }
        ]
    }
}
```

**Organization KPIs:**
| KPI | Description |
|---|---|
| organization_efficiency | Overall task completion rate across all teams |
| task_completion_rate | % of all tasks completed/approved org-wide |
| project_completion_rate | % of all projects completed in the period |

---

### GET `/reports/kpi-history`
Get historical KPI records for a user.

**Access:** Authenticated

**Query Parameters:**
| Param | Type | Required | Description |
|---|---|---|---|
| user_id | integer | No | Target user (defaults to self) |
| metric_name | string | No | Filter by specific metric |
| per_page | integer | No | Items per page (default: 30) |

**Visibility Rules:**
- **Admin:** any user
- **Supervisor:** self + own subordinates
- **Subordinate:** self only

**Response (200):** Paginated list of KPI records.

---

## Error Handling

### Standard Error Responses

**401 Unauthenticated:**
```json
{
    "status": false,
    "message": "Unauthenticated."
}
```

**403 Forbidden (RBAC):**
```json
{
    "success": false,
    "message": "Access denied. Required role(s): admin, supervisor"
}
```

**403 Forbidden (Business Logic):**
```json
{
    "success": false,
    "message": "You can only assign tasks to your own subordinates."
}
```

**404 Not Found:**
```json
{
    "status": false,
    "message": "No query results for model [App\\Models\\TsTask] 999"
}
```

**422 Validation Error:**
```json
{
    "status": false,
    "message": "The name field is required.",
    "errors": {
        "name": ["The name field is required."]
    }
}
```

---

## Task Status Workflow

```
pending → in_progress → completed → approved ✓
                                  → rejected → in_progress → completed → approved ✓
```

---

## Files Created

### Migrations (8 tables)
- `database/migrations/2026_02_28_000001_create_ts_users_table.php`
- `database/migrations/2026_02_28_000002_create_ts_projects_table.php`
- `database/migrations/2026_02_28_000003_create_ts_project_assignments_table.php`
- `database/migrations/2026_02_28_000004_create_ts_tasks_table.php`
- `database/migrations/2026_02_28_000005_create_ts_daily_reports_table.php`
- `database/migrations/2026_02_28_000006_create_ts_task_histories_table.php`
- `database/migrations/2026_02_28_000007_create_ts_project_histories_table.php`
- `database/migrations/2026_02_28_000008_create_ts_kpis_table.php`

### Models (8 models)
- `app/Models/TsUser.php`
- `app/Models/TsProject.php`
- `app/Models/TsProjectAssignment.php`
- `app/Models/TsTask.php`
- `app/Models/TsDailyReport.php`
- `app/Models/TsTaskHistory.php`
- `app/Models/TsProjectHistory.php`
- `app/Models/TsKpi.php`

### Controllers (6 controllers)
- `app/Http/Controllers/TimesheetAuthController.php`
- `app/Http/Controllers/TimesheetProjectController.php`
- `app/Http/Controllers/TimesheetTaskController.php`
- `app/Http/Controllers/TimesheetDailyReportController.php`
- `app/Http/Controllers/TimesheetHistoryController.php`
- `app/Http/Controllers/TimesheetReportController.php`

### Middleware
- `app/Http/Middleware/TimesheetRole.php` — RBAC middleware registered as `ts.role`

### Routes
- All routes defined in `routes/api.php` under prefix `/api/timesheet`

### Test Script
- `test_timesheet_apis.ps1` — Comprehensive PowerShell test covering all 32 endpoints

---

## Quick Start

```bash
# Run migrations
php artisan migrate

# Start server
php artisan serve --port=8001

# Register admin
curl -X POST http://127.0.0.1:8001/api/timesheet/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Admin","email":"admin@test.com","password":"password123","password_confirmation":"password123","role":"admin"}'

# Use the returned token for authenticated requests
curl -H "Authorization: Bearer {token}" http://127.0.0.1:8001/api/timesheet/auth/profile
```
