# test_timesheet_apis.ps1 - Comprehensive test for all Timesheet APIs
$base = "http://127.0.0.1:8001/api/timesheet"
$headers = @{ "Accept" = "application/json"; "Content-Type" = "application/json" }

function Test-Api {
    param(
        [string]$Method,
        [string]$Url,
        [string]$Body = $null,
        [hashtable]$Headers = @{ "Accept" = "application/json"; "Content-Type" = "application/json" },
        [string]$Label
    )
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host "TEST: $Label" -ForegroundColor Yellow
    Write-Host "$Method $Url" -ForegroundColor Gray
    try {
        $params = @{
            Method = $Method
            Uri = $Url
            Headers = $Headers
            ContentType = "application/json"
        }
        if ($Body) { $params.Body = $Body }
        $response = Invoke-RestMethod @params
        Write-Host "STATUS: SUCCESS" -ForegroundColor Green
        $response | ConvertTo-Json -Depth 5 | Write-Host
        return $response
    } catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        $errorBody = $_.ErrorDetails.Message
        if ($statusCode -ge 400 -and $statusCode -lt 500) {
            Write-Host "STATUS: $statusCode (Expected Client Error)" -ForegroundColor Yellow
        } else {
            Write-Host "STATUS: $statusCode FAILED" -ForegroundColor Red
        }
        Write-Host $errorBody
        return $null
    }
}

Write-Host "============================================" -ForegroundColor Magenta
Write-Host "   TIMESHEET API - COMPREHENSIVE TEST SUITE" -ForegroundColor Magenta
Write-Host "============================================" -ForegroundColor Magenta

# ==========================================================
# 1. AUTHENTICATION - Register Admin
# ==========================================================
$body = @{
    name = "Admin User"
    email = "admin@timesheet.test"
    password = "password123"
    password_confirmation = "password123"
    role = "admin"
} | ConvertTo-Json

$adminResult = Test-Api -Method "POST" -Url "$base/auth/register" -Body $body -Label "Register Admin"
$adminToken = $adminResult.data.token
$adminId = $adminResult.data.user.id
$adminHeaders = @{ "Accept" = "application/json"; "Content-Type" = "application/json"; "Authorization" = "Bearer $adminToken" }

# ==========================================================
# 2. AUTHENTICATION - Register Supervisor
# ==========================================================
$body = @{
    name = "Supervisor One"
    email = "supervisor1@timesheet.test"
    password = "password123"
    password_confirmation = "password123"
    role = "supervisor"
} | ConvertTo-Json

$supResult = Test-Api -Method "POST" -Url "$base/auth/register" -Body $body -Label "Register Supervisor"
$supToken = $supResult.data.token
$supId = $supResult.data.user.id
$supHeaders = @{ "Accept" = "application/json"; "Content-Type" = "application/json"; "Authorization" = "Bearer $supToken" }

# ==========================================================
# 3. AUTHENTICATION - Register Subordinates
# ==========================================================
$body = @{
    name = "Subordinate One"
    email = "sub1@timesheet.test"
    password = "password123"
    password_confirmation = "password123"
    role = "subordinate"
    supervisor_id = $supId
} | ConvertTo-Json

$sub1Result = Test-Api -Method "POST" -Url "$base/auth/register" -Body $body -Label "Register Subordinate 1"
$sub1Token = $sub1Result.data.token
$sub1Id = $sub1Result.data.user.id
$sub1Headers = @{ "Accept" = "application/json"; "Content-Type" = "application/json"; "Authorization" = "Bearer $sub1Token" }

$body = @{
    name = "Subordinate Two"
    email = "sub2@timesheet.test"
    password = "password123"
    password_confirmation = "password123"
    role = "subordinate"
    supervisor_id = $supId
} | ConvertTo-Json

$sub2Result = Test-Api -Method "POST" -Url "$base/auth/register" -Body $body -Label "Register Subordinate 2"
$sub2Token = $sub2Result.data.token
$sub2Id = $sub2Result.data.user.id
$sub2Headers = @{ "Accept" = "application/json"; "Content-Type" = "application/json"; "Authorization" = "Bearer $sub2Token" }

# ==========================================================
# 4. AUTHENTICATION - Register subordinate without supervisor (should fail)
# ==========================================================
$body = @{
    name = "Bad Sub"
    email = "badsub@timesheet.test"
    password = "password123"
    password_confirmation = "password123"
    role = "subordinate"
} | ConvertTo-Json

Test-Api -Method "POST" -Url "$base/auth/register" -Body $body -Label "Register Subordinate without supervisor (should fail 422)"

# ==========================================================
# 5. AUTHENTICATION - Login
# ==========================================================
$body = @{
    email = "admin@timesheet.test"
    password = "password123"
} | ConvertTo-Json

$loginResult = Test-Api -Method "POST" -Url "$base/auth/login" -Body $body -Label "Login Admin"

# ==========================================================
# 6. AUTHENTICATION - Login with wrong password (should fail)
# ==========================================================
$body = @{
    email = "admin@timesheet.test"
    password = "wrongpassword"
} | ConvertTo-Json

Test-Api -Method "POST" -Url "$base/auth/login" -Body $body -Label "Login with wrong password (should fail 401)"

# ==========================================================
# 7. AUTHENTICATION - Profile
# ==========================================================
Test-Api -Method "GET" -Url "$base/auth/profile" -Headers $adminHeaders -Label "Admin Profile"
Test-Api -Method "GET" -Url "$base/auth/profile" -Headers $supHeaders -Label "Supervisor Profile"
Test-Api -Method "GET" -Url "$base/auth/profile" -Headers $sub1Headers -Label "Subordinate 1 Profile"

# ==========================================================
# 8. USERS - List (Admin sees all, Supervisor sees own)
# ==========================================================
Test-Api -Method "GET" -Url "$base/users" -Headers $adminHeaders -Label "Admin List All Users"
Test-Api -Method "GET" -Url "$base/users" -Headers $supHeaders -Label "Supervisor List Users (own + subs)"

# Subordinate should be denied
Test-Api -Method "GET" -Url "$base/users" -Headers $sub1Headers -Label "Subordinate List Users (should fail 403)"

# ==========================================================
# 9. PROJECTS - Create (Supervisor)
# ==========================================================
$body = @{
    name = "Project Alpha"
    description = "First test project"
    start_date = "2026-03-01"
    end_date = "2026-04-30"
} | ConvertTo-Json

$projResult = Test-Api -Method "POST" -Url "$base/projects" -Body $body -Headers $supHeaders -Label "Supervisor Create Project"
$projectId = $projResult.data.id

# ==========================================================
# 10. PROJECTS - Create (Admin)
# ==========================================================
$body = @{
    name = "Project Beta"
    description = "Second project by admin"
    start_date = "2026-03-15"
    end_date = "2026-06-30"
} | ConvertTo-Json

$projBetaResult = Test-Api -Method "POST" -Url "$base/projects" -Body $body -Headers $adminHeaders -Label "Admin Create Project"
$projectBetaId = $projBetaResult.data.id

# ==========================================================
# 11. PROJECTS - Subordinate Create (should fail 403)
# ==========================================================
$body = @{
    name = "Unauthorized Project"
    description = "Should fail"
    start_date = "2026-03-01"
    end_date = "2026-04-30"
} | ConvertTo-Json

Test-Api -Method "POST" -Url "$base/projects" -Body $body -Headers $sub1Headers -Label "Subordinate Create Project (should fail 403)"

# ==========================================================
# 12. PROJECTS - Assign members
# ==========================================================
$body = @{ user_id = $sub1Id } | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/projects/$projectId/assign-member" -Body $body -Headers $supHeaders -Label "Assign Sub1 to Project Alpha"

$body = @{ user_id = $sub2Id } | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/projects/$projectId/assign-member" -Body $body -Headers $supHeaders -Label "Assign Sub2 to Project Alpha"

# Duplicate assignment (should fail)
$body = @{ user_id = $sub1Id } | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/projects/$projectId/assign-member" -Body $body -Headers $supHeaders -Label "Duplicate assign (should fail 422)"

# ==========================================================
# 13. PROJECTS - List with role visibility
# ==========================================================
Test-Api -Method "GET" -Url "$base/projects" -Headers $adminHeaders -Label "Admin List Projects (sees all)"
Test-Api -Method "GET" -Url "$base/projects" -Headers $supHeaders -Label "Supervisor List Projects"
Test-Api -Method "GET" -Url "$base/projects" -Headers $sub1Headers -Label "Subordinate 1 List Projects (own only)"

# ==========================================================
# 14. PROJECTS - Show single
# ==========================================================
Test-Api -Method "GET" -Url "$base/projects/$projectId" -Headers $supHeaders -Label "Show Project Alpha"

# ==========================================================
# 15. PROJECTS - Update
# ==========================================================
$body = @{
    name = "Project Alpha - Updated"
    description = "Updated description"
} | ConvertTo-Json

Test-Api -Method "PUT" -Url "$base/projects/$projectId" -Body $body -Headers $supHeaders -Label "Update Project Alpha"

# ==========================================================
# 16. PROJECTS - Extend Timeline
# ==========================================================
$body = @{
    extended_end_date = "2026-06-30"
    reason = "Client requested additional features"
} | ConvertTo-Json

Test-Api -Method "POST" -Url "$base/projects/$projectId/extend-timeline" -Body $body -Headers $supHeaders -Label "Extend Project Alpha Timeline"

# ==========================================================
# 17. PROJECTS - Remove member
# ==========================================================
$body = @{ user_id = $sub2Id } | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/projects/$projectId/remove-member" -Body $body -Headers $supHeaders -Label "Remove Sub2 from Project Alpha"

# Re-assign for later tests
$body = @{ user_id = $sub2Id } | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/projects/$projectId/assign-member" -Body $body -Headers $supHeaders -Label "Re-assign Sub2 to Project Alpha"

# ==========================================================
# 18. TASKS - Create with project
# ==========================================================
$body = @{
    project_id = $projectId
    title = "Design wireframes"
    description = "Create initial wireframes for the project"
    assigned_to = $sub1Id
    priority = "high"
    due_date = "2026-03-15"
} | ConvertTo-Json

$task1Result = Test-Api -Method "POST" -Url "$base/tasks" -Body $body -Headers $supHeaders -Label "Create Task 1 (with project)"
$task1Id = $task1Result.data.id

# ==========================================================
# 19. TASKS - Create without project
# ==========================================================
$body = @{
    title = "Update documentation"
    description = "General documentation task"
    assigned_to = $sub1Id
    priority = "medium"
    due_date = "2026-03-20"
} | ConvertTo-Json

$task2Result = Test-Api -Method "POST" -Url "$base/tasks" -Body $body -Headers $supHeaders -Label "Create Task 2 (without project)"
$task2Id = $task2Result.data.id

# ==========================================================
# 20. TASKS - Create for Sub2
# ==========================================================
$body = @{
    project_id = $projectId
    title = "Backend API implementation"
    description = "Implement REST APIs"
    assigned_to = $sub2Id
    priority = "urgent"
    due_date = "2026-03-25"
} | ConvertTo-Json

$task3Result = Test-Api -Method "POST" -Url "$base/tasks" -Body $body -Headers $supHeaders -Label "Create Task 3 (for Sub2)"
$task3Id = $task3Result.data.id

# Subordinate tries to create task (should fail 403)
$body = @{
    title = "Unauthorized task"
    assigned_to = $sub1Id
    priority = "low"
} | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/tasks" -Body $body -Headers $sub1Headers -Label "Subordinate Create Task (should fail 403)"

# ==========================================================
# 21. TASKS - List with role visibility
# ==========================================================
Test-Api -Method "GET" -Url "$base/tasks" -Headers $adminHeaders -Label "Admin List All Tasks"
Test-Api -Method "GET" -Url "$base/tasks" -Headers $supHeaders -Label "Supervisor List Tasks"
Test-Api -Method "GET" -Url "$base/tasks" -Headers $sub1Headers -Label "Sub1 List Tasks (own only)"
Test-Api -Method "GET" -Url "$base/tasks" -Headers $sub2Headers -Label "Sub2 List Tasks (own only)"

# Filter by status
Test-Api -Method "GET" -Url "$base/tasks?status=pending" -Headers $supHeaders -Label "Supervisor List Pending Tasks"

# ==========================================================
# 22. TASKS - Show single
# ==========================================================
Test-Api -Method "GET" -Url "$base/tasks/$task1Id" -Headers $sub1Headers -Label "Sub1 Show Task 1"

# ==========================================================
# 23. TASKS - Update task details (Supervisor)
# ==========================================================
$body = @{
    title = "Design wireframes - v2"
    priority = "urgent"
} | ConvertTo-Json

Test-Api -Method "PUT" -Url "$base/tasks/$task1Id" -Body $body -Headers $supHeaders -Label "Update Task 1"

# ==========================================================
# 24. TASKS - Update Status (Subordinate workflow)
# ==========================================================
# Sub1 starts Task 1: pending -> in_progress
$body = @{ status = "in_progress"; remarks = "Starting wireframe design" } | ConvertTo-Json
Test-Api -Method "PATCH" -Url "$base/tasks/$task1Id/status" -Body $body -Headers $sub1Headers -Label "Sub1: Task 1 pending -> in_progress"

# Sub1 completes Task 1: in_progress -> completed
$body = @{ status = "completed"; remarks = "Wireframes completed" } | ConvertTo-Json
Test-Api -Method "PATCH" -Url "$base/tasks/$task1Id/status" -Body $body -Headers $sub1Headers -Label "Sub1: Task 1 in_progress -> completed"

# Sub2 starts and completes Task 3
$body = @{ status = "in_progress" } | ConvertTo-Json
Test-Api -Method "PATCH" -Url "$base/tasks/$task3Id/status" -Body $body -Headers $sub2Headers -Label "Sub2: Task 3 pending -> in_progress"

$body = @{ status = "completed"; remarks = "API implementation done" } | ConvertTo-Json
Test-Api -Method "PATCH" -Url "$base/tasks/$task3Id/status" -Body $body -Headers $sub2Headers -Label "Sub2: Task 3 in_progress -> completed"

# Invalid transition (should fail)
$body = @{ status = "completed" } | ConvertTo-Json
Test-Api -Method "PATCH" -Url "$base/tasks/$task2Id/status" -Body $body -Headers $sub1Headers -Label "Invalid: pending -> completed (should fail 422)"

# ==========================================================
# 25. TASKS - Approve
# ==========================================================
Test-Api -Method "POST" -Url "$base/tasks/$task1Id/approve" -Body '{"remarks":"Great work!"}' -Headers $supHeaders -Label "Supervisor Approve Task 1"

# ==========================================================
# 26. TASKS - Reject
# ==========================================================
$body = @{ rejection_reason = "Need more test coverage" } | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/tasks/$task3Id/reject" -Body $body -Headers $supHeaders -Label "Supervisor Reject Task 3"

# Sub2 re-works Task 3: rejected -> in_progress -> completed
$body = @{ status = "in_progress"; remarks = "Re-working with more tests" } | ConvertTo-Json
Test-Api -Method "PATCH" -Url "$base/tasks/$task3Id/status" -Body $body -Headers $sub2Headers -Label "Sub2: Task 3 rejected -> in_progress"

$body = @{ status = "completed"; remarks = "Added test coverage" } | ConvertTo-Json
Test-Api -Method "PATCH" -Url "$base/tasks/$task3Id/status" -Body $body -Headers $sub2Headers -Label "Sub2: Task 3 in_progress -> completed again"

Test-Api -Method "POST" -Url "$base/tasks/$task3Id/approve" -Body '{"remarks":"Looks good now"}' -Headers $supHeaders -Label "Supervisor Approve Task 3 (2nd attempt)"

# ==========================================================
# 27. DAILY REPORTS - Submit
# ==========================================================
$body = @{
    task_id = $task1Id
    report_date = "2026-02-28"
    description = "Worked on wireframe designs for 6 hours"
    hours_spent = 6
} | ConvertTo-Json

$report1Result = Test-Api -Method "POST" -Url "$base/daily-reports" -Body $body -Headers $sub1Headers -Label "Sub1 Submit Daily Report 1"
$report1Id = $report1Result.data.id

$body = @{
    task_id = $task2Id
    report_date = "2026-02-28"
    description = "Updated documentation for 2 hours"
    hours_spent = 2
} | ConvertTo-Json

Test-Api -Method "POST" -Url "$base/daily-reports" -Body $body -Headers $sub1Headers -Label "Sub1 Submit Daily Report 2"

$body = @{
    task_id = $task3Id
    report_date = "2026-02-28"
    description = "Implemented REST APIs for 8 hours"
    hours_spent = 8
} | ConvertTo-Json

Test-Api -Method "POST" -Url "$base/daily-reports" -Body $body -Headers $sub2Headers -Label "Sub2 Submit Daily Report"

# Duplicate report (should fail)
$body = @{
    task_id = $task1Id
    report_date = "2026-02-28"
    description = "Duplicate"
    hours_spent = 1
} | ConvertTo-Json

Test-Api -Method "POST" -Url "$base/daily-reports" -Body $body -Headers $sub1Headers -Label "Duplicate Daily Report (should fail 422)"

# Supervisor tries to submit (should fail 403)
$body = @{
    report_date = "2026-02-28"
    description = "Supervisor report"
    hours_spent = 4
} | ConvertTo-Json
Test-Api -Method "POST" -Url "$base/daily-reports" -Body $body -Headers $supHeaders -Label "Supervisor Submit Report (should fail 403)"

# ==========================================================
# 28. DAILY REPORTS - List with filters
# ==========================================================
Test-Api -Method "GET" -Url "$base/daily-reports" -Headers $adminHeaders -Label "Admin List All Reports"
Test-Api -Method "GET" -Url "$base/daily-reports" -Headers $supHeaders -Label "Supervisor List Reports"
Test-Api -Method "GET" -Url "$base/daily-reports" -Headers $sub1Headers -Label "Sub1 List Own Reports"
Test-Api -Method "GET" -Url "$base/daily-reports?report_date=2026-02-28" -Headers $adminHeaders -Label "Filter Reports by date"

# ==========================================================
# 29. DAILY REPORTS - Update
# ==========================================================
$body = @{
    description = "Updated: Worked on wireframe designs and revisions"
    hours_spent = 7
} | ConvertTo-Json

Test-Api -Method "PUT" -Url "$base/daily-reports/$report1Id" -Body $body -Headers $sub1Headers -Label "Sub1 Update Daily Report"

# ==========================================================
# 30. HISTORIES - Task
# ==========================================================
Test-Api -Method "GET" -Url "$base/histories/tasks" -Headers $adminHeaders -Label "Admin Task Histories"
Test-Api -Method "GET" -Url "$base/histories/tasks?task_id=$task1Id" -Headers $supHeaders -Label "Supervisor Task 1 History"
Test-Api -Method "GET" -Url "$base/histories/tasks" -Headers $sub1Headers -Label "Sub1 Task Histories (own only)"

# ==========================================================
# 31. HISTORIES - Project
# ==========================================================
Test-Api -Method "GET" -Url "$base/histories/projects" -Headers $adminHeaders -Label "Admin Project Histories"
Test-Api -Method "GET" -Url "$base/histories/projects?project_id=$projectId" -Headers $supHeaders -Label "Supervisor Project Alpha History"

# ==========================================================
# 32. REPORTS - Subordinate Performance
# ==========================================================
Test-Api -Method "GET" -Url "$base/reports/subordinate-performance?user_id=$sub1Id&period=2026-02" -Headers $adminHeaders -Label "Admin: Sub1 Performance Report"
Test-Api -Method "GET" -Url "$base/reports/subordinate-performance?user_id=$sub1Id&period=2026-02" -Headers $supHeaders -Label "Supervisor: Sub1 Performance Report"
Test-Api -Method "GET" -Url "$base/reports/subordinate-performance?period=2026-02" -Headers $sub1Headers -Label "Sub1: Own Performance Report"

# Sub1 tries to view Sub2's report (should fail 403)
Test-Api -Method "GET" -Url "$base/reports/subordinate-performance?user_id=$sub2Id&period=2026-02" -Headers $sub1Headers -Label "Sub1 views Sub2 report (should fail 403)"

# ==========================================================
# 33. REPORTS - Supervisor Performance
# ==========================================================
Test-Api -Method "GET" -Url "$base/reports/supervisor-performance?user_id=$supId&period=2026-02" -Headers $adminHeaders -Label "Admin: Supervisor 1 Performance"
Test-Api -Method "GET" -Url "$base/reports/supervisor-performance?period=2026-02" -Headers $supHeaders -Label "Supervisor: Own Performance"

# Sub tries to access supervisor report (should fail 403)
Test-Api -Method "GET" -Url "$base/reports/supervisor-performance?period=2026-02" -Headers $sub1Headers -Label "Subordinate access supervisor report (should fail 403)"

# ==========================================================
# 34. REPORTS - Organization Performance (Admin only)
# ==========================================================
Test-Api -Method "GET" -Url "$base/reports/organization-performance?period=2026-02" -Headers $adminHeaders -Label "Admin: Organization Performance"

# Supervisor tries (should fail 403)
Test-Api -Method "GET" -Url "$base/reports/organization-performance?period=2026-02" -Headers $supHeaders -Label "Supervisor org report (should fail 403)"

# ==========================================================
# 35. REPORTS - KPI History
# ==========================================================
Test-Api -Method "GET" -Url "$base/reports/kpi-history?user_id=$sub1Id" -Headers $adminHeaders -Label "Admin: Sub1 KPI History"
Test-Api -Method "GET" -Url "$base/reports/kpi-history" -Headers $sub1Headers -Label "Sub1: Own KPI History"

# ==========================================================
# 36. AUTH - Logout
# ==========================================================
Test-Api -Method "POST" -Url "$base/auth/logout" -Headers $sub2Headers -Label "Sub2 Logout"

# Try to access after logout (should fail 401)
Test-Api -Method "GET" -Url "$base/auth/profile" -Headers $sub2Headers -Label "Access after logout (should fail 401)"

# ==========================================================
# 37. PROJECTS - Delete (Admin only)
# ==========================================================
Test-Api -Method "DELETE" -Url "$base/projects/$projectBetaId" -Headers $adminHeaders -Label "Admin Delete Project Beta"

# Supervisor tries to delete (should fail 403) 
Test-Api -Method "DELETE" -Url "$base/projects/$projectId" -Headers $supHeaders -Label "Supervisor Delete Project (should fail 403)"

# ==========================================================
# 38. TASKS - Delete (Admin only)
# ==========================================================
Test-Api -Method "DELETE" -Url "$base/tasks/$task2Id" -Headers $adminHeaders -Label "Admin Delete Task 2"

# Supervisor tries to delete (should fail 403)
Test-Api -Method "DELETE" -Url "$base/tasks/$task1Id" -Headers $supHeaders -Label "Supervisor Delete Task (should fail 403)"

Write-Host "`n============================================" -ForegroundColor Magenta
Write-Host "   ALL TESTS COMPLETED!" -ForegroundColor Magenta
Write-Host "============================================" -ForegroundColor Magenta
