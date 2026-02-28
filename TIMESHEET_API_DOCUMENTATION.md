# Timesheet System - API Documentation

## Overview

A role-based timesheet management system integrated with Vistora's existing `userlogin` authentication. Three roles: **Admin**, **Supervisor**, and **Subordinate** — determined by `admin_yn` and `supervisor_yn` flags in the `userlogin` table.

**Live Base URL:** `https://vistora.sroy.es/public/api/timesheet`

**Authentication:** All protected endpoints require `?user_id=X` query parameter where `X` is the `user_login_id` from the `userlogin` table.

---

## Table of Contents

1. [Roles & Permissions](#roles--permissions)
2. [Authentication](#1-authentication)
3. [User Management](#2-user-management)
4. [Team Members](#3-team-members)
5. [Projects](#4-projects)
6. [Tasks](#5-tasks)
7. [Daily Reports](#6-daily-reports)
8. [Histories](#7-histories)
9. [Reports & KPIs](#8-reports--kpis)
10. [Error Handling](#error-handling)

---

## Roles & Permissions

Role is derived from `userlogin` table flags:
- **Admin**: `admin_yn = 1`
- **Supervisor**: `supervisor_yn = 1` AND `admin_yn != 1`
- **Subordinate**: neither admin nor supervisor

| Feature | Admin | Supervisor | Subordinate |
|---|:---:|:---:|:---:|
| Login | ✅ | ✅ | ✅ |
| View all users | ✅ | ✅ | ❌ |
| Manage team members | ✅ (any supervisor) | ✅ (own team) | ❌ |
| Create projects | ✅ | ✅ | ❌ |
| Assign members to projects | ✅ | Own team only | ❌ |
| Extend project timelines | ✅ | Own projects | ❌ |
| Delete projects | ✅ | ❌ | ❌ |
| Create/assign tasks | ✅ | Own team only | ❌ |
| Update task status | ❌ | ❌ | Own tasks only |
| Approve/reject tasks | ✅ | Own team only | ❌ |
| Delete tasks | ✅ | ❌ | ❌ |
| Submit daily reports | ❌ | ❌ | ✅ |
| View daily reports | All | Own + team | Own only |
| View histories | All | Own + team | Own only |
| Subordinate performance | All | Own team | Own only |
| Supervisor performance | All | Own only | ❌ |
| Organization performance | ✅ | ❌ | ❌ |
| KPI history | All | Own + team | Own only |

---

## 1. Authentication

### POST `/auth/login`

Login using Vistora credentials. **No `user_id` query parameter needed.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/auth/login
Content-Type: application/json

{
    "corp_id": "test",
    "email_id": "test@gmail.com",
    "password": "123456"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "user": {
            "user_login_id": 2,
            "username": "test",
            "email_id": "test@gmail.com",
            "empcode": "EMP001",
            "corp_id": "test",
            "company_name": "Nippo",
            "role": "admin",
            "is_active": true
        }
    }
}
```

**Error (401):**
```json
{
    "success": false,
    "message": "Invalid credentials."
}
```

---

### GET `/auth/profile?user_id=X`

Get logged-in user's profile. For admin/supervisor, includes team members.

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/auth/profile?user_id=2
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "user_login_id": 2,
        "username": "test",
        "email_id": "test@gmail.com",
        "empcode": "EMP001",
        "corp_id": "test",
        "company_name": "Nippo",
        "role": "admin",
        "is_active": true,
        "team_members": [
            {
                "user_login_id": 44,
                "username": "Indranil Shah",
                "email_id": "indSh@xyz.com",
                "role": "subordinate"
            },
            {
                "user_login_id": 45,
                "username": "Pulkit Ray",
                "email_id": "pr@gmail.com",
                "role": "subordinate"
            }
        ]
    }
}
```

---

## 2. User Management

### GET `/users?user_id=X`

List all users in the same `corp_id`. **Admin & Supervisor only.**

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `role` | string | No | Filter: `admin`, `supervisor`, `subordinate` |
| `per_page` | integer | No | Results per page (default: 15) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/users?user_id=2
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "user_login_id": 44,
                "username": "Indranil Shah",
                "email_id": "indSh@xyz.com",
                "empcode": "EMP002",
                "corp_id": "test",
                "company_name": "IMS MACO SERVICES INDIA PVT. LTD.",
                "active_yn": 1,
                "admin_yn": 0,
                "supervisor_yn": 0,
                "created_at": null,
                "role": "subordinate",
                "is_active": true
            },
            {
                "user_login_id": 2,
                "username": "test",
                "email_id": "test@gmail.com",
                "empcode": "EMP001",
                "corp_id": "test",
                "company_name": "Nippo",
                "active_yn": 1,
                "admin_yn": 1,
                "supervisor_yn": 1,
                "created_at": "2025-06-02T15:39:31.000000Z",
                "role": "admin",
                "is_active": true
            }
        ],
        "total": 5,
        "per_page": 15,
        "current_page": 1,
        "last_page": 1
    }
}
```

---

## 3. Team Members

Team members define supervisor-subordinate relationships via the `ts_team_members` table. **Admin & Supervisor only.**

### POST `/team-members?user_id=X`

Add a team member. Supervisor adds to own team; Admin can specify `supervisor_id`.

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/team-members?user_id=2
Content-Type: application/json

{
    "member_id": 44,
    "supervisor_id": 2
}
```

> `supervisor_id` is optional. If omitted, defaults to the authenticated user. Only admins can set it to another user.

**Response (201):**
```json
{
    "success": true,
    "message": "Team member added successfully.",
    "data": {
        "supervisor_id": 2,
        "member_id": 44,
        "corp_id": "test",
        "updated_at": "2026-02-28T12:17:59.000000Z",
        "created_at": "2026-02-28T12:17:59.000000Z",
        "id": 4,
        "supervisor": {
            "user_login_id": 2,
            "username": "test",
            "email_id": "test@gmail.com",
            "role": "admin",
            "is_active": true
        },
        "member": {
            "user_login_id": 44,
            "username": "Indranil Shah",
            "email_id": "indSh@xyz.com",
            "role": "subordinate",
            "is_active": true
        }
    }
}
```

---

### GET `/team-members?user_id=X`

List team members. Supervisor sees own team; Admin can filter by `supervisor_id`.

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `supervisor_id` | integer | No | Admin-only: filter by supervisor |
| `per_page` | integer | No | Results per page (default: 15) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/team-members?user_id=2
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 4,
                "supervisor_id": 2,
                "member_id": 44,
                "corp_id": "test",
                "created_at": "2026-02-28T12:17:59.000000Z",
                "updated_at": "2026-02-28T12:17:59.000000Z",
                "member": {
                    "user_login_id": 44,
                    "username": "Indranil Shah",
                    "email_id": "indSh@xyz.com",
                    "role": "subordinate",
                    "is_active": true
                },
                "supervisor": {
                    "user_login_id": 2,
                    "username": "test",
                    "email_id": "test@gmail.com",
                    "role": "admin",
                    "is_active": true
                }
            }
        ],
        "total": 3,
        "per_page": 15
    }
}
```

---

### DELETE `/team-members?user_id=X`

Remove a team member.

**Request:**
```json
DELETE https://vistora.sroy.es/public/api/timesheet/team-members?user_id=2
Content-Type: application/json

{
    "member_id": 44,
    "supervisor_id": 2
}
```

> `supervisor_id` is optional (admin-only). Defaults to authenticated user.

**Response (200):**
```json
{
    "success": true,
    "message": "Team member removed successfully."
}
```

---

## 4. Projects

### POST `/projects?user_id=X`

Create a new project. **Admin & Supervisor only.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/projects?user_id=2
Content-Type: application/json

{
    "name": "Website Redesign",
    "description": "Complete redesign of company website with modern UI/UX",
    "start_date": "2026-03-01",
    "end_date": "2026-05-31",
    "status": "active"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Project created successfully.",
    "data": {
        "name": "Website Redesign",
        "description": "Complete redesign of company website with modern UI/UX",
        "created_by": 2,
        "start_date": "2026-03-01T00:00:00.000000Z",
        "end_date": "2026-05-31T00:00:00.000000Z",
        "status": "active",
        "updated_at": "2026-02-28T12:18:55.000000Z",
        "created_at": "2026-02-28T12:18:55.000000Z",
        "id": 2,
        "creator": {
            "user_login_id": 2,
            "username": "test",
            "email_id": "test@gmail.com",
            "role": "admin",
            "is_active": true
        },
        "members": []
    }
}
```

---

### GET `/projects?user_id=X`

List projects visible to the user.

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `status` | string | No | Filter: `active`, `completed`, `on_hold`, `cancelled` |
| `per_page` | integer | No | Results per page (default: 15) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/projects?user_id=2
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 2,
                "name": "Website Redesign",
                "description": "Complete redesign of company website with modern UI/UX",
                "created_by": 2,
                "start_date": "2026-03-01T00:00:00.000000Z",
                "end_date": "2026-05-31T00:00:00.000000Z",
                "extended_end_date": "2026-06-30T00:00:00.000000Z",
                "status": "active",
                "created_at": "2026-02-28T12:18:55.000000Z",
                "updated_at": "2026-02-28T12:19:28.000000Z",
                "creator": { "user_login_id": 2, "username": "test" },
                "members": [
                    { "user_login_id": 44, "username": "Indranil Shah" },
                    { "user_login_id": 45, "username": "Pulkit Ray" }
                ]
            }
        ],
        "total": 3,
        "per_page": 15
    }
}
```

---

### GET `/projects/{id}?user_id=X`

Get a single project by ID.

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/projects/2?user_id=2
```

---

### PUT `/projects/{id}?user_id=X`

Update a project. **Admin & Supervisor only.**

**Request:**
```json
PUT https://vistora.sroy.es/public/api/timesheet/projects/2?user_id=2
Content-Type: application/json

{
    "name": "Website Redesign v2",
    "status": "active"
}
```

---

### DELETE `/projects/{id}?user_id=X`

Delete a project. **Admin only.**

```
DELETE https://vistora.sroy.es/public/api/timesheet/projects/2?user_id=2
```

---

### POST `/projects/{id}/extend-timeline?user_id=X`

Extend project deadline. **Admin & Supervisor only.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/projects/2/extend-timeline?user_id=2
Content-Type: application/json

{
    "extended_end_date": "2026-06-30",
    "reason": "Client requested additional features"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Project timeline extended successfully.",
    "data": {
        "id": 2,
        "name": "Website Redesign",
        "extended_end_date": "2026-06-30T00:00:00.000000Z",
        "status": "active"
    }
}
```

---

### POST `/projects/{id}/assign-member?user_id=X`

Assign a user to a project. **Admin & Supervisor only.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/projects/2/assign-member?user_id=2
Content-Type: application/json

{
    "member_user_id": 44
}
```

> Note: The body field is `member_user_id` (not `user_id`) to avoid conflict with the query parameter.

**Response (200):**
```json
{
    "success": true,
    "message": "Member assigned to project successfully.",
    "data": {
        "id": 2,
        "name": "Website Redesign",
        "members": [
            {
                "user_login_id": 44,
                "username": "Indranil Shah",
                "role": "subordinate"
            }
        ]
    }
}
```

---

### POST `/projects/{id}/remove-member?user_id=X`

Remove a user from a project. **Admin & Supervisor only.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/projects/2/remove-member?user_id=2
Content-Type: application/json

{
    "member_user_id": 44
}
```

---

## 5. Tasks

### POST `/tasks?user_id=X`

Create and assign a task. **Admin & Supervisor only.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/tasks?user_id=2
Content-Type: application/json

{
    "project_id": 2,
    "assigned_to": 44,
    "title": "Design Homepage Mockup",
    "description": "Create wireframes and mockups for new homepage",
    "due_date": "2026-03-15",
    "priority": "high"
}
```

**Fields:**
| Field | Type | Required | Description |
|---|---|---|---|
| `project_id` | integer | Yes | Project to assign task to |
| `assigned_to` | integer | Yes | `user_login_id` of assignee |
| `title` | string | Yes | Task title |
| `description` | string | No | Task description |
| `due_date` | date | No | Due date (YYYY-MM-DD) |
| `priority` | string | Yes | `low`, `medium`, `high` |

**Response (201):**
```json
{
    "success": true,
    "message": "Task created successfully.",
    "data": {
        "project_id": 2,
        "title": "Design Homepage Mockup",
        "description": "Create wireframes and mockups for new homepage",
        "assigned_to": 44,
        "assigned_by": 2,
        "priority": "high",
        "due_date": "2026-03-15T00:00:00.000000Z",
        "status": "pending",
        "updated_at": "2026-02-28T12:19:59.000000Z",
        "created_at": "2026-02-28T12:19:59.000000Z",
        "id": 3,
        "project": { "id": 2, "name": "Website Redesign" },
        "assignee": {
            "user_login_id": 44,
            "username": "Indranil Shah",
            "role": "subordinate"
        },
        "assigner": {
            "user_login_id": 2,
            "username": "test",
            "role": "admin"
        }
    }
}
```

---

### GET `/tasks?user_id=X`

List tasks visible to the user.

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `project_id` | integer | No | Filter by project |
| `status` | string | No | Filter: `pending`, `in_progress`, `completed`, `approved`, `rejected` |
| `priority` | string | No | Filter: `low`, `medium`, `high` |
| `per_page` | integer | No | Results per page (default: 15) |

---

### GET `/tasks/{id}?user_id=X`

Get a single task by ID.

---

### PUT `/tasks/{id}?user_id=X`

Update task details. **Admin & Supervisor only.**

---

### DELETE `/tasks/{id}?user_id=X`

Delete a task. **Admin only.**

---

### PATCH `/tasks/{id}/status?user_id=X`

Update task status. **Subordinate only** (own assigned tasks).

**Allowed transitions:**
- `pending` → `in_progress`
- `in_progress` → `completed`
- `rejected` → `in_progress`

**Request:**
```json
PATCH https://vistora.sroy.es/public/api/timesheet/tasks/3/status?user_id=44
Content-Type: application/json

{
    "status": "in_progress"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Task status updated successfully.",
    "data": {
        "id": 3,
        "title": "Design Homepage Mockup",
        "status": "in_progress",
        "completed_at": null,
        "approved_by": null,
        "approved_at": null
    }
}
```

---

### POST `/tasks/{id}/approve?user_id=X`

Approve a completed task. **Admin & Supervisor only.**

**Request:**
```
POST https://vistora.sroy.es/public/api/timesheet/tasks/3/approve?user_id=2
```

**Response (200):**
```json
{
    "success": true,
    "message": "Task approved successfully.",
    "data": {
        "id": 3,
        "status": "approved",
        "approved_by": 2,
        "approved_at": "2026-02-28T12:20:44.000000Z"
    }
}
```

---

### POST `/tasks/{id}/reject?user_id=X`

Reject a completed task. **Admin & Supervisor only.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/tasks/4/reject?user_id=2
Content-Type: application/json

{
    "rejection_reason": "CSS does not match the mockup design specifications"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Task rejected.",
    "data": {
        "id": 4,
        "status": "rejected",
        "rejection_reason": "CSS does not match the mockup design specifications"
    }
}
```

---

## 6. Daily Reports

### POST `/daily-reports?user_id=X`

Submit a daily work report. **Subordinate only.**

**Request:**
```json
POST https://vistora.sroy.es/public/api/timesheet/daily-reports?user_id=44
Content-Type: application/json

{
    "task_id": 3,
    "report_date": "2026-02-28",
    "description": "Completed homepage mockup design with 3 layout variations",
    "hours_spent": 7.5
}
```

**Fields:**
| Field | Type | Required | Description |
|---|---|---|---|
| `task_id` | integer | No | Associated task ID |
| `report_date` | date | Yes | Date of work (YYYY-MM-DD, <= today) |
| `description` | string | Yes | Work description |
| `hours_spent` | decimal | Yes | Hours worked (0.25 to 24) |

**Response (201):**
```json
{
    "success": true,
    "message": "Daily report submitted successfully.",
    "data": {
        "user_id": 44,
        "task_id": 3,
        "report_date": "2026-02-28T00:00:00.000000Z",
        "description": "Completed homepage mockup design with 3 layout variations",
        "hours_spent": "7.50",
        "updated_at": "2026-02-28T12:21:21.000000Z",
        "created_at": "2026-02-28T12:21:21.000000Z",
        "id": 2,
        "user": {
            "user_login_id": 44,
            "username": "Indranil Shah",
            "role": "subordinate"
        },
        "task": {
            "id": 3,
            "title": "Design Homepage Mockup",
            "project_id": 2
        }
    }
}
```

---

### GET `/daily-reports?user_id=X`

List daily reports visible to the user.

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `report_user_id` | integer | No | Filter by report author's `user_login_id` |
| `task_id` | integer | No | Filter by task |
| `date_from` | date | No | Start date filter |
| `date_to` | date | No | End date filter |
| `per_page` | integer | No | Results per page (default: 15) |

---

### GET `/daily-reports/{id}?user_id=X`

Get a single daily report.

---

### PUT `/daily-reports/{id}?user_id=X`

Update a daily report. **Subordinate only** (own reports).

---

### DELETE `/daily-reports/{id}?user_id=X`

Delete a daily report.

---

## 7. Histories

### GET `/histories/tasks?user_id=X`

Get task action history (created, status_changed, approved, rejected).

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `task_id` | integer | No | Filter by task |
| `action_user_id` | integer | No | Filter by user who performed the action |
| `action` | string | No | Filter: `created`, `status_changed`, `approved`, `rejected` |
| `per_page` | integer | No | Results per page (default: 15) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/histories/tasks?user_id=2
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 16,
                "task_id": 4,
                "user_id": 2,
                "action": "rejected",
                "old_value": "completed",
                "new_value": "rejected",
                "remarks": "CSS does not match the mockup design specifications",
                "created_at": "2026-02-28T12:21:05.000000Z",
                "task": { "id": 4, "title": "Implement Responsive CSS", "project_id": 2, "assigned_to": 45 },
                "user": { "user_login_id": 2, "username": "test", "role": "admin" }
            },
            {
                "id": 13,
                "task_id": 3,
                "user_id": 2,
                "action": "approved",
                "old_value": "completed",
                "new_value": "approved",
                "remarks": "Task approved.",
                "created_at": "2026-02-28T12:20:44.000000Z",
                "task": { "id": 3, "title": "Design Homepage Mockup", "project_id": 2, "assigned_to": 44 },
                "user": { "user_login_id": 2, "username": "test", "role": "admin" }
            }
        ],
        "total": 16,
        "per_page": 15
    }
}
```

---

### GET `/histories/projects?user_id=X`

Get project action history (created, updated, member_added, member_removed, timeline_extended).

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `project_id` | integer | No | Filter by project |
| `action_user_id` | integer | No | Filter by user who performed the action |
| `action` | string | No | Filter by action type |
| `per_page` | integer | No | Results per page (default: 15) |

---

## 8. Reports & KPIs

### GET `/reports/subordinate-performance?user_id=X`

Get performance metrics for a subordinate.
- **Subordinate:** own metrics only
- **Supervisor:** own team members
- **Admin:** anyone

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `target_user_id` | integer | No | Target user (defaults to self) |
| `period` | string | No | YYYY-MM format (defaults to current month) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/reports/subordinate-performance?user_id=2&target_user_id=44&period=2026-02
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "user": {
            "user_login_id": 44,
            "username": "Indranil Shah",
            "email_id": "indSh@xyz.com",
            "role": "subordinate"
        },
        "period": "2026-02",
        "kpis": {
            "task_completion_rate": 100,
            "approval_rate": 100,
            "reporting_consistency": 5,
            "on_time_completion_rate": 100
        },
        "summary": {
            "total_tasks": 2,
            "completed_tasks": 2,
            "approved_tasks": 2,
            "rejected_tasks": 0,
            "overdue_tasks": 0,
            "total_hours_logged": 14,
            "avg_hours_per_day": 14,
            "working_days": 20,
            "days_reported": 1
        }
    }
}
```

---

### GET `/reports/supervisor-performance?user_id=X`

Get performance metrics for a supervisor including team breakdown. **Admin & Supervisor only.**

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `target_user_id` | integer | No | Target supervisor (defaults to self) |
| `period` | string | No | YYYY-MM format (defaults to current month) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/reports/supervisor-performance?user_id=2&period=2026-02
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "supervisor": {
            "user_login_id": 2,
            "username": "test",
            "email_id": "test@gmail.com",
            "role": "admin"
        },
        "period": "2026-02",
        "kpis": {
            "project_delivery_rate": 0,
            "on_time_delivery_rate": 0,
            "team_productivity": 50
        },
        "summary": {
            "total_projects": 3,
            "completed_projects": 0,
            "total_subordinates": 3,
            "team_total_tasks": 4,
            "team_completed_tasks": 2,
            "team_approved_tasks": 2,
            "team_total_hours": 19,
            "avg_approval_time_days": 0
        },
        "subordinate_breakdown": [
            {
                "user": { "user_login_id": 44, "username": "Indranil Shah", "email_id": "indSh@xyz.com" },
                "total_tasks": 2,
                "completed_tasks": 2,
                "completion_rate": 100,
                "hours_logged": 14
            },
            {
                "user": { "user_login_id": 45, "username": "Pulkit Ray", "email_id": "pr@gmail.com" },
                "total_tasks": 2,
                "completed_tasks": 0,
                "completion_rate": 0,
                "hours_logged": 5
            }
        ]
    }
}
```

---

### GET `/reports/organization-performance?user_id=X`

Organization-wide performance report. **Admin only.**

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `period` | string | No | YYYY-MM format (defaults to current month) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/reports/organization-performance?user_id=2&period=2026-02
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "period": "2026-02",
        "kpis": {
            "organization_efficiency": 50,
            "task_completion_rate": 50,
            "project_completion_rate": 0
        },
        "summary": {
            "total_tasks": 4,
            "completed_tasks": 2,
            "approved_tasks": 2,
            "overdue_tasks": 0,
            "total_projects": 3,
            "completed_projects": 0,
            "total_hours_logged": 19,
            "active_subordinates": 3,
            "active_supervisors": 2
        },
        "supervisor_comparisons": [
            {
                "supervisor": { "user_login_id": 2, "username": "test", "email_id": "test@gmail.com" },
                "subordinate_count": 3,
                "total_tasks": 4,
                "completed_tasks": 2,
                "team_productivity": 50,
                "total_projects": 3,
                "completed_projects": 0,
                "team_hours_logged": 19
            },
            {
                "supervisor": { "user_login_id": 19, "username": "sroy", "email_id": "sr@gmail.com" },
                "subordinate_count": 2,
                "total_tasks": 4,
                "completed_tasks": 2,
                "team_productivity": 50,
                "total_projects": 0,
                "completed_projects": 0,
                "team_hours_logged": 19
            }
        ]
    }
}
```

---

### GET `/reports/kpi-history?user_id=X`

Get stored KPI history for a user.

**Query Parameters:**
| Parameter | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | Your `user_login_id` |
| `target_user_id` | integer | No | Target user (defaults to self) |
| `metric_name` | string | No | Filter by metric name |
| `per_page` | integer | No | Results per page (default: 30) |

**Request:**
```
GET https://vistora.sroy.es/public/api/timesheet/reports/kpi-history?user_id=2&target_user_id=44
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 3,
                "user_id": 44,
                "period": "2026-02",
                "metric_name": "on_time_completion_rate",
                "metric_value": "100.00",
                "calculated_at": "2026-02-28T12:22:09.000000Z",
                "created_at": "2026-02-28T06:36:37.000000Z",
                "updated_at": "2026-02-28T12:22:09.000000Z"
            },
            {
                "id": 2,
                "user_id": 44,
                "period": "2026-02",
                "metric_name": "reporting_consistency",
                "metric_value": "5.00",
                "calculated_at": "2026-02-28T12:22:09.000000Z"
            },
            {
                "id": 1,
                "user_id": 44,
                "period": "2026-02",
                "metric_name": "task_completion_rate",
                "metric_value": "100.00",
                "calculated_at": "2026-02-28T12:22:09.000000Z"
            }
        ],
        "total": 3,
        "per_page": 30
    }
}
```

---

## Error Handling

All errors follow a consistent format:

### Validation Error (422)
```json
{
    "status": false,
    "message": "The description field is required.",
    "errors": {
        "description": ["The description field is required."]
    }
}
```

### Authentication Error (401)
```json
{
    "status": false,
    "message": "user_id query parameter is required (pass your user_login_id)"
}
```

### Inactive User (403)
```json
{
    "status": false,
    "message": "Your account is inactive."
}
```

### Authorization Error (403)
```json
{
    "success": false,
    "message": "Access denied. Required role: admin, supervisor"
}
```

### Not Found (404)
```json
{
    "success": false,
    "message": "Resource not found."
}
```

---

## API Routes Summary

| Method | Endpoint | Auth | Roles |
|---|---|---|---|
| POST | `/auth/login` | No | All |
| GET | `/auth/profile` | Yes | All |
| GET | `/users` | Yes | Admin, Supervisor |
| POST | `/team-members` | Yes | Admin, Supervisor |
| GET | `/team-members` | Yes | Admin, Supervisor |
| DELETE | `/team-members` | Yes | Admin, Supervisor |
| GET | `/projects` | Yes | All |
| POST | `/projects` | Yes | Admin, Supervisor |
| GET | `/projects/{id}` | Yes | All |
| PUT | `/projects/{id}` | Yes | Admin, Supervisor |
| DELETE | `/projects/{id}` | Yes | Admin |
| POST | `/projects/{id}/extend-timeline` | Yes | Admin, Supervisor |
| POST | `/projects/{id}/assign-member` | Yes | Admin, Supervisor |
| POST | `/projects/{id}/remove-member` | Yes | Admin, Supervisor |
| GET | `/tasks` | Yes | All |
| POST | `/tasks` | Yes | Admin, Supervisor |
| GET | `/tasks/{id}` | Yes | All |
| PUT | `/tasks/{id}` | Yes | Admin, Supervisor |
| DELETE | `/tasks/{id}` | Yes | Admin |
| PATCH | `/tasks/{id}/status` | Yes | Subordinate |
| POST | `/tasks/{id}/approve` | Yes | Admin, Supervisor |
| POST | `/tasks/{id}/reject` | Yes | Admin, Supervisor |
| GET | `/daily-reports` | Yes | All |
| POST | `/daily-reports` | Yes | Subordinate |
| GET | `/daily-reports/{id}` | Yes | All |
| PUT | `/daily-reports/{id}` | Yes | Subordinate |
| DELETE | `/daily-reports/{id}` | Yes | All |
| GET | `/histories/tasks` | Yes | All |
| GET | `/histories/projects` | Yes | All |
| GET | `/reports/subordinate-performance` | Yes | All |
| GET | `/reports/supervisor-performance` | Yes | Admin, Supervisor |
| GET | `/reports/organization-performance` | Yes | Admin |
| GET | `/reports/kpi-history` | Yes | All |

---

## Test Credentials

```
Corp ID:  test
Email:    test@gmail.com
Password: 123456
Role:     Admin (user_login_id: 2)
```

## Test Data on Live Server

| user_login_id | Username | Email | Role |
|---|---|---|---|
| 2 | test | test@gmail.com | Admin |
| 19 | sroy | sr@gmail.com | Admin |
| 43 | Rajesh Kr Patel | rjk@gmail.com | Subordinate |
| 44 | Indranil Shah | indSh@xyz.com | Subordinate |
| 45 | Pulkit Ray | pr@gmail.com | Subordinate |

**Team Setup:** User 2 (test) supervises users 43, 44, 45

**Projects Created:**
- Project 2: "Website Redesign" (members: 44, 45) — extended to 2026-06-30
- Project 3: "Mobile App Development" (member: 43)

**Tasks Created:**
- Task 3: "Design Homepage Mockup" → assigned to 44, status: approved
- Task 4: "Implement Responsive CSS" → assigned to 45, status: rejected
