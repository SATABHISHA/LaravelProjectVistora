[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$BASE = "https://vistora.sroy.es/public/api/timesheet"
$H = @{ Accept = "application/json" }
$pass = 0; $fail = 0; $total = 0

function Call-API {
    param($Method, $Uri, $Body, $Token, $ExpectCode)
    $headers = @{ Accept = "application/json" }
    if ($Token) { $headers["Authorization"] = "Bearer $Token" }
    try {
        $params = @{ Method = $Method; Uri = $Uri; Headers = $headers; ContentType = "application/json"; UseBasicParsing = $true }
        if ($Body) { $params["Body"] = $Body }
        $resp = Invoke-WebRequest @params
        $code = $resp.StatusCode
        $data = $resp.Content | ConvertFrom-Json
        if ($ExpectCode -and $code -ne $ExpectCode) {
            return @{ ok = $false; code = $code; data = $data; msg = "Expected $ExpectCode got $code" }
        }
        return @{ ok = $true; code = $code; data = $data }
    } catch {
        $code = $_.Exception.Response.StatusCode.value__
        $errBody = $null
        try { $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream()); $errBody = $reader.ReadToEnd() | ConvertFrom-Json } catch {}
        if ($ExpectCode -and $code -eq $ExpectCode) {
            return @{ ok = $true; code = $code; data = $errBody }
        }
        return @{ ok = $false; code = $code; data = $errBody; msg = "HTTP $code" }
    }
}

function Test-Result {
    param($Name, $Result, $Check)
    $script:total++
    if ($Result.ok -and (!$Check -or (& $Check))) {
        $script:pass++
        Write-Host "[PASS] $Name (HTTP $($Result.code))" -ForegroundColor Green
    } else {
        $script:fail++
        $errMsg = if ($Result.msg) { $Result.msg } else { "Check failed" }
        Write-Host "[FAIL] $Name - $errMsg" -ForegroundColor Red
        if ($Result.data) { Write-Host "       $(($Result.data | ConvertTo-Json -Compress -Depth 3).Substring(0, [Math]::Min(200, ($Result.data | ConvertTo-Json -Compress -Depth 3).Length)))" -ForegroundColor DarkGray }
    }
}

$ts = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
Write-Host "`n========================================" -ForegroundColor Yellow
Write-Host " TIMESHEET API LIVE SERVER TEST" -ForegroundColor Yellow
Write-Host " Base: $BASE" -ForegroundColor Yellow
Write-Host " Timestamp: $ts" -ForegroundColor Yellow
Write-Host "========================================`n" -ForegroundColor Yellow

# ============================================
# SECTION 1: AUTHENTICATION
# ============================================
Write-Host "--- SECTION 1: AUTHENTICATION ---" -ForegroundColor Cyan

# 1. Register Admin
$r = Call-API -Method POST -Uri "$BASE/auth/register" -Body "{`"name`":`"Admin $ts`",`"email`":`"admin_$ts@test.com`",`"password`":`"password123`",`"password_confirmation`":`"password123`",`"role`":`"admin`"}" -ExpectCode 201
$adminToken = $r.data.data.token; $adminId = $r.data.data.user.id
Test-Result "Register Admin" $r { $adminToken -ne $null }

# 2. Register Supervisor
$r = Call-API -Method POST -Uri "$BASE/auth/register" -Body "{`"name`":`"Supervisor $ts`",`"email`":`"sup_$ts@test.com`",`"password`":`"password123`",`"password_confirmation`":`"password123`",`"role`":`"supervisor`"}" -ExpectCode 201
$supToken = $r.data.data.token; $supId = $r.data.data.user.id
Test-Result "Register Supervisor" $r { $supToken -ne $null }

# 3. Register Subordinate 1
$r = Call-API -Method POST -Uri "$BASE/auth/register" -Body "{`"name`":`"Sub One $ts`",`"email`":`"sub1_$ts@test.com`",`"password`":`"password123`",`"password_confirmation`":`"password123`",`"role`":`"subordinate`",`"supervisor_id`":$supId}" -ExpectCode 201
$sub1Token = $r.data.data.token; $sub1Id = $r.data.data.user.id
Test-Result "Register Subordinate 1" $r { $sub1Token -ne $null }

# 4. Register Subordinate 2
$r = Call-API -Method POST -Uri "$BASE/auth/register" -Body "{`"name`":`"Sub Two $ts`",`"email`":`"sub2_$ts@test.com`",`"password`":`"password123`",`"password_confirmation`":`"password123`",`"role`":`"subordinate`",`"supervisor_id`":$supId}" -ExpectCode 201
$sub2Token = $r.data.data.token; $sub2Id = $r.data.data.user.id
Test-Result "Register Subordinate 2" $r { $sub2Token -ne $null }

# 5. Login
$r = Call-API -Method POST -Uri "$BASE/auth/login" -Body "{`"email`":`"admin_$ts@test.com`",`"password`":`"password123`"}" -ExpectCode 200
Test-Result "Login Admin" $r { $r.data.data.token -ne $null }

# 6. Get Profile
$r = Call-API -Method GET -Uri "$BASE/auth/profile" -Token $adminToken -ExpectCode 200
Test-Result "Get Admin Profile" $r { $r.data.data.role -eq "admin" }

# 7. Unauthenticated (expect 401)
$r = Call-API -Method GET -Uri "$BASE/auth/profile" -Token "invalid_token_xyz" -ExpectCode 401
Test-Result "Unauthenticated returns 401" $r

# 8. Duplicate Registration (expect 422)
$r = Call-API -Method POST -Uri "$BASE/auth/register" -Body "{`"name`":`"Dup`",`"email`":`"admin_$ts@test.com`",`"password`":`"password123`",`"password_confirmation`":`"password123`",`"role`":`"admin`"}" -ExpectCode 422
Test-Result "Duplicate email rejected (422)" $r

Write-Host "`nIDs: Admin=$adminId, Sup=$supId, Sub1=$sub1Id, Sub2=$sub2Id`n" -ForegroundColor DarkYellow

# ============================================
# SECTION 2: USERS
# ============================================
Write-Host "--- SECTION 2: USER LISTING ---" -ForegroundColor Cyan

# 9. Admin lists all users
$r = Call-API -Method GET -Uri "$BASE/users" -Token $adminToken -ExpectCode 200
Test-Result "Admin list users" $r

# 10. Supervisor lists users
$r = Call-API -Method GET -Uri "$BASE/users" -Token $supToken -ExpectCode 200
Test-Result "Supervisor list users" $r

# 11. Subordinate denied user listing (403)
$r = Call-API -Method GET -Uri "$BASE/users" -Token $sub1Token -ExpectCode 403
Test-Result "Subordinate denied user list (403)" $r

# ============================================
# SECTION 3: PROJECTS
# ============================================
Write-Host "`n--- SECTION 3: PROJECTS ---" -ForegroundColor Cyan

# 12. Admin creates project 1
$r = Call-API -Method POST -Uri "$BASE/projects" -Token $adminToken -Body "{`"name`":`"Project Alpha $ts`",`"description`":`"Admin project`",`"start_date`":`"2026-03-01`",`"end_date`":`"2026-04-30`",`"status`":`"active`"}" -ExpectCode 201
$proj1Id = $r.data.data.id
Test-Result "Admin create project 1" $r { $proj1Id -ne $null }

# 13. Supervisor creates project 2
$r = Call-API -Method POST -Uri "$BASE/projects" -Token $supToken -Body "{`"name`":`"Project Beta $ts`",`"description`":`"Supervisor project`",`"start_date`":`"2026-03-01`",`"end_date`":`"2026-05-31`",`"status`":`"active`"}" -ExpectCode 201
$proj2Id = $r.data.data.id
Test-Result "Supervisor create project 2" $r { $proj2Id -ne $null }

# 14. Subordinate cannot create project (403)
$r = Call-API -Method POST -Uri "$BASE/projects" -Token $sub1Token -Body "{`"name`":`"Fail`",`"description`":`"X`",`"start_date`":`"2026-03-01`",`"end_date`":`"2026-04-30`"}" -ExpectCode 403
Test-Result "Subordinate denied project create (403)" $r

# 15. List projects
$r = Call-API -Method GET -Uri "$BASE/projects" -Token $adminToken -ExpectCode 200
Test-Result "Admin list projects" $r

# 16. Show project
$r = Call-API -Method GET -Uri "$BASE/projects/$proj1Id" -Token $adminToken -ExpectCode 200
Test-Result "Show project detail" $r

# 17. Update project
$r = Call-API -Method PUT -Uri "$BASE/projects/$proj1Id" -Token $adminToken -Body "{`"name`":`"Project Alpha Updated $ts`"}" -ExpectCode 200
Test-Result "Update project name" $r

# 18. Extend timeline
$r = Call-API -Method POST -Uri "$BASE/projects/$proj1Id/extend-timeline" -Token $adminToken -Body "{`"extended_end_date`":`"2026-06-30`",`"reason`":`"Extra time needed`"}" -ExpectCode 200
Test-Result "Extend project timeline" $r

# 19. Assign sub1 to project 1
$r = Call-API -Method POST -Uri "$BASE/projects/$proj1Id/assign-member" -Token $adminToken -Body "{`"user_id`":$sub1Id}" -ExpectCode 200
Test-Result "Assign Sub1 to project 1" $r

# 20. Assign sub2 to project 2
$r = Call-API -Method POST -Uri "$BASE/projects/$proj2Id/assign-member" -Token $supToken -Body "{`"user_id`":$sub2Id}" -ExpectCode 200
Test-Result "Assign Sub2 to project 2" $r

# 21. Subordinate can see assigned project
$r = Call-API -Method GET -Uri "$BASE/projects" -Token $sub1Token -ExpectCode 200
Test-Result "Sub1 sees assigned projects" $r

# 22. Remove member
$r = Call-API -Method POST -Uri "$BASE/projects/$proj1Id/remove-member" -Token $adminToken -Body "{`"user_id`":$sub1Id}" -ExpectCode 200
Test-Result "Remove Sub1 from project 1" $r

# Re-assign for tasks later
Call-API -Method POST -Uri "$BASE/projects/$proj1Id/assign-member" -Token $adminToken -Body "{`"user_id`":$sub1Id}" | Out-Null
Call-API -Method POST -Uri "$BASE/projects/$proj2Id/assign-member" -Token $supToken -Body "{`"user_id`":$sub1Id}" | Out-Null

# ============================================
# SECTION 4: TASKS
# ============================================
Write-Host "`n--- SECTION 4: TASKS ---" -ForegroundColor Cyan

# 23. Admin creates task for sub1
$r = Call-API -Method POST -Uri "$BASE/tasks" -Token $adminToken -Body "{`"project_id`":$proj1Id,`"title`":`"Task Alpha $ts`",`"description`":`"Design work`",`"assigned_to`":$sub1Id,`"priority`":`"high`",`"due_date`":`"2026-03-15`"}" -ExpectCode 201
$task1Id = $r.data.data.id
Test-Result "Admin create task for Sub1" $r { $task1Id -ne $null }

# 24. Supervisor creates task for sub2
$r = Call-API -Method POST -Uri "$BASE/tasks" -Token $supToken -Body "{`"project_id`":$proj2Id,`"title`":`"Task Beta $ts`",`"description`":`"Dev work`",`"assigned_to`":$sub2Id,`"priority`":`"medium`",`"due_date`":`"2026-04-01`"}" -ExpectCode 201
$task2Id = $r.data.data.id
Test-Result "Supervisor create task for Sub2" $r { $task2Id -ne $null }

# 25. Supervisor creates standalone task (no project)
$r = Call-API -Method POST -Uri "$BASE/tasks" -Token $supToken -Body "{`"title`":`"Standalone Task $ts`",`"description`":`"No project`",`"assigned_to`":$sub1Id,`"priority`":`"low`",`"due_date`":`"2026-03-20`"}" -ExpectCode 201
$task3Id = $r.data.data.id
Test-Result "Create standalone task (no project)" $r { $task3Id -ne $null }

# 26. Subordinate cannot create task (403)
$r = Call-API -Method POST -Uri "$BASE/tasks" -Token $sub1Token -Body "{`"title`":`"Fail`",`"assigned_to`":$sub2Id,`"priority`":`"low`"}" -ExpectCode 403
Test-Result "Subordinate denied task create (403)" $r

# 27. List tasks
$r = Call-API -Method GET -Uri "$BASE/tasks" -Token $sub1Token -ExpectCode 200
Test-Result "Sub1 list own tasks" $r

# 28. Show task
$r = Call-API -Method GET -Uri "$BASE/tasks/$task1Id" -Token $sub1Token -ExpectCode 200
Test-Result "Sub1 show task detail" $r

# 29. Update task (admin)
$r = Call-API -Method PUT -Uri "$BASE/tasks/$task1Id" -Token $adminToken -Body "{`"priority`":`"urgent`"}" -ExpectCode 200
Test-Result "Admin update task priority" $r

# ============================================
# SECTION 5: TASK WORKFLOW
# ============================================
Write-Host "`n--- SECTION 5: TASK WORKFLOW ---" -ForegroundColor Cyan

# 30. Sub1 starts task (pending -> in_progress)
$r = Call-API -Method PATCH -Uri "$BASE/tasks/$task1Id/status" -Token $sub1Token -Body "{`"status`":`"in_progress`",`"remarks`":`"Starting work`"}" -ExpectCode 200
Test-Result "Sub1 start task (in_progress)" $r

# 31. Sub1 completes task (in_progress -> completed)
$r = Call-API -Method PATCH -Uri "$BASE/tasks/$task1Id/status" -Token $sub1Token -Body "{`"status`":`"completed`",`"remarks`":`"Done`"}" -ExpectCode 200
Test-Result "Sub1 complete task" $r

# 32. Admin approves task
$r = Call-API -Method POST -Uri "$BASE/tasks/$task1Id/approve" -Token $adminToken -Body "{`"remarks`":`"Well done`"}" -ExpectCode 200
Test-Result "Admin approve task" $r

# 33. Sub2 starts + completes task 2
Call-API -Method PATCH -Uri "$BASE/tasks/$task2Id/status" -Token $sub2Token -Body "{`"status`":`"in_progress`"}" | Out-Null
$r = Call-API -Method PATCH -Uri "$BASE/tasks/$task2Id/status" -Token $sub2Token -Body "{`"status`":`"completed`"}" -ExpectCode 200
Test-Result "Sub2 complete task 2" $r

# 34. Supervisor rejects task 2
$r = Call-API -Method POST -Uri "$BASE/tasks/$task2Id/reject" -Token $supToken -Body "{`"rejection_reason`":`"Needs more work`"}" -ExpectCode 200
Test-Result "Supervisor reject task 2" $r

# 35. Sub2 reworks (rejected -> in_progress)
$r = Call-API -Method PATCH -Uri "$BASE/tasks/$task2Id/status" -Token $sub2Token -Body "{`"status`":`"in_progress`",`"remarks`":`"Reworking`"}" -ExpectCode 200
Test-Result "Sub2 rework (rejected -> in_progress)" $r

# 36. Sub2 re-completes and supervisor approves
Call-API -Method PATCH -Uri "$BASE/tasks/$task2Id/status" -Token $sub2Token -Body "{`"status`":`"completed`"}" | Out-Null
$r = Call-API -Method POST -Uri "$BASE/tasks/$task2Id/approve" -Token $supToken -Body "{`"remarks`":`"Better now`"}" -ExpectCode 200
Test-Result "Supervisor approve reworked task 2" $r

# ============================================
# SECTION 6: DAILY REPORTS
# ============================================
Write-Host "`n--- SECTION 6: DAILY REPORTS ---" -ForegroundColor Cyan

# 37. Sub1 submits daily report
$r = Call-API -Method POST -Uri "$BASE/daily-reports" -Token $sub1Token -Body "{`"task_id`":$task1Id,`"report_date`":`"2026-02-28`",`"description`":`"Worked on wireframes for 6 hours`",`"hours_spent`":6}" -ExpectCode 201
$report1Id = $r.data.data.id
Test-Result "Sub1 submit daily report" $r { $report1Id -ne $null }

# 38. Sub2 submits report
$r = Call-API -Method POST -Uri "$BASE/daily-reports" -Token $sub2Token -Body "{`"task_id`":$task2Id,`"report_date`":`"2026-02-28`",`"description`":`"Backend dev for 8 hours`",`"hours_spent`":8}" -ExpectCode 201
$report2Id = $r.data.data.id
Test-Result "Sub2 submit daily report" $r { $report2Id -ne $null }

# 39. Sub1 extra report (no task)
$r = Call-API -Method POST -Uri "$BASE/daily-reports" -Token $sub1Token -Body "{`"report_date`":`"2026-02-27`",`"description`":`"Meeting and planning`",`"hours_spent`":3}" -ExpectCode 201
Test-Result "Sub1 submit report without task" $r

# 40. Duplicate report rejected
$r = Call-API -Method POST -Uri "$BASE/daily-reports" -Token $sub1Token -Body "{`"task_id`":$task1Id,`"report_date`":`"2026-02-28`",`"description`":`"Dup`",`"hours_spent`":1}" -ExpectCode 422
Test-Result "Duplicate report rejected (422)" $r

# 41. List daily reports (admin sees all)
$r = Call-API -Method GET -Uri "$BASE/daily-reports" -Token $adminToken -ExpectCode 200
Test-Result "Admin list all daily reports" $r

# 42. Show daily report
$r = Call-API -Method GET -Uri "$BASE/daily-reports/$report1Id" -Token $sub1Token -ExpectCode 200
Test-Result "Sub1 show own report" $r

# 43. Update daily report
$r = Call-API -Method PUT -Uri "$BASE/daily-reports/$report1Id" -Token $sub1Token -Body "{`"hours_spent`":7,`"description`":`"Updated: 7 hours of wireframes`"}" -ExpectCode 200
Test-Result "Sub1 update report" $r

# 44. Supervisor cannot submit report (403 or appropriate error)
$r = Call-API -Method POST -Uri "$BASE/daily-reports" -Token $supToken -Body "{`"report_date`":`"2026-02-28`",`"description`":`"X`",`"hours_spent`":1}" -ExpectCode 403
Test-Result "Supervisor denied daily report (403)" $r

# ============================================
# SECTION 7: HISTORIES
# ============================================
Write-Host "`n--- SECTION 7: HISTORIES ---" -ForegroundColor Cyan

# 45. Task histories
$r = Call-API -Method GET -Uri "$BASE/histories/tasks" -Token $adminToken -ExpectCode 200
Test-Result "Admin view task histories" $r

# 46. Task histories for specific task
$r = Call-API -Method GET -Uri "$BASE/histories/tasks?task_id=$task1Id" -Token $adminToken -ExpectCode 200
Test-Result "Task histories for task 1" $r

# 47. Project histories
$r = Call-API -Method GET -Uri "$BASE/histories/projects" -Token $adminToken -ExpectCode 200
Test-Result "Admin view project histories" $r

# 48. Project histories for specific project
$r = Call-API -Method GET -Uri "$BASE/histories/projects?project_id=$proj1Id" -Token $adminToken -ExpectCode 200
Test-Result "Project histories for project 1" $r

# 49. Subordinate task histories (own only)
$r = Call-API -Method GET -Uri "$BASE/histories/tasks" -Token $sub1Token -ExpectCode 200
Test-Result "Sub1 view own task histories" $r

# ============================================
# SECTION 8: REPORTS & KPIS
# ============================================
Write-Host "`n--- SECTION 8: REPORTS & KPIS ---" -ForegroundColor Cyan

# 50. Subordinate performance (self)
$r = Call-API -Method GET -Uri "$BASE/reports/subordinate-performance?user_id=$sub1Id&period=2026-02" -Token $sub1Token -ExpectCode 200
Test-Result "Sub1 own performance report" $r

# 51. Admin views sub performance
$r = Call-API -Method GET -Uri "$BASE/reports/subordinate-performance?user_id=$sub2Id&period=2026-02" -Token $adminToken -ExpectCode 200
Test-Result "Admin view Sub2 performance" $r

# 52. Supervisor performance
$r = Call-API -Method GET -Uri "$BASE/reports/supervisor-performance?user_id=$supId&period=2026-02" -Token $supToken -ExpectCode 200
Test-Result "Supervisor own performance report" $r

# 53. Organization performance (admin only)
$r = Call-API -Method GET -Uri "$BASE/reports/organization-performance?period=2026-02" -Token $adminToken -ExpectCode 200
Test-Result "Admin organization performance" $r

# 54. Subordinate denied org performance (403)
$r = Call-API -Method GET -Uri "$BASE/reports/organization-performance" -Token $sub1Token -ExpectCode 403
Test-Result "Subordinate denied org report (403)" $r

# 55. KPI history
$r = Call-API -Method GET -Uri "$BASE/reports/kpi-history?user_id=$sub1Id" -Token $adminToken -ExpectCode 200
Test-Result "KPI history" $r

# ============================================
# SECTION 9: RBAC ENFORCEMENT
# ============================================
Write-Host "`n--- SECTION 9: RBAC ENFORCEMENT ---" -ForegroundColor Cyan

# 56. Supervisor cannot delete project (403)
$r = Call-API -Method DELETE -Uri "$BASE/projects/$proj2Id" -Token $supToken -ExpectCode 403
Test-Result "Supervisor denied project delete (403)" $r

# 57. Supervisor cannot delete task (403)
$r = Call-API -Method DELETE -Uri "$BASE/tasks/$task2Id" -Token $supToken -ExpectCode 403
Test-Result "Supervisor denied task delete (403)" $r

# 58. Sub cannot approve task (403)
$r = Call-API -Method POST -Uri "$BASE/tasks/$task3Id/approve" -Token $sub1Token -Body "{`"remarks`":`"self approve`"}" -ExpectCode 403
Test-Result "Sub denied task approve (403)" $r

# 59. Sub cannot update other's report
$r = Call-API -Method PUT -Uri "$BASE/daily-reports/$report2Id" -Token $sub1Token -Body "{`"hours_spent`":1}" -ExpectCode 403
Test-Result "Sub denied update other's report (403)" $r

# ============================================
# SECTION 10: CLEANUP / DELETE
# ============================================
Write-Host "`n--- SECTION 10: DELETE OPERATIONS ---" -ForegroundColor Cyan

# 60. Delete daily report
$r = Call-API -Method DELETE -Uri "$BASE/daily-reports/$report1Id" -Token $sub1Token -ExpectCode 200
Test-Result "Sub1 delete own report" $r

# 61. Admin delete task
$r = Call-API -Method DELETE -Uri "$BASE/tasks/$task3Id" -Token $adminToken -ExpectCode 200
Test-Result "Admin delete task" $r

# 62. Admin delete project
$r = Call-API -Method DELETE -Uri "$BASE/projects/$proj1Id" -Token $adminToken -ExpectCode 200
Test-Result "Admin delete project" $r

# 63. Logout
$r = Call-API -Method POST -Uri "$BASE/auth/logout" -Token $adminToken -ExpectCode 200
Test-Result "Logout admin" $r

# 64. Verify token is revoked (401)
$r = Call-API -Method GET -Uri "$BASE/auth/profile" -Token $adminToken -ExpectCode 401
Test-Result "Revoked token returns 401" $r

# ============================================
# SUMMARY
# ============================================
Write-Host "`n========================================" -ForegroundColor Yellow
Write-Host " TEST RESULTS: $pass PASSED / $fail FAILED / $total TOTAL" -ForegroundColor $(if ($fail -eq 0) { "Green" } else { "Red" })
Write-Host "========================================`n" -ForegroundColor Yellow
