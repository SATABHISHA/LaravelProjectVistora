[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$BASE = "https://vistora.sroy.es/public/api/timesheet"
$pass = 0; $fail = 0; $total = 0

# IDs from registration
$AI = 12  # Admin
$SI = 13  # Supervisor
$S1 = 14  # Sub1
$S2 = 15  # Sub2

function T {
    param($Name, $Method, $Uri, $Body, $Expect)
    $script:total++
    $p = @{ Method=$Method; Uri=$Uri; Headers=@{Accept="application/json"}; ContentType="application/json"; UseBasicParsing=$true }
    if ($Body) { $p["Body"] = $Body }
    try {
        $r = Invoke-WebRequest @p
        $code = $r.StatusCode
        $c = $r.Content
    } catch {
        $code = $_.Exception.Response.StatusCode.value__
        try{$s=New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream());$c=$s.ReadToEnd()}catch{$c=""}
    }
    if ($Expect -and $code -ne $Expect) {
        $script:fail++
        Write-Host "[FAIL] $Name - Expected $Expect got $code" -ForegroundColor Red
        Write-Host "       $($c.Substring(0,[Math]::Min(200,$c.Length)))" -ForegroundColor DarkGray
    } else {
        $script:pass++
        Write-Host "[PASS] $Name (HTTP $code)" -ForegroundColor Green
    }
    try { return $c | ConvertFrom-Json } catch { return $null }
}

Write-Host "`n=== TIMESHEET API LIVE TEST (No Token Auth) ===" -ForegroundColor Yellow
Write-Host "Base: $BASE`n" -ForegroundColor Yellow

# --- AUTH ---
Write-Host "--- AUTHENTICATION ---" -ForegroundColor Cyan
T "Login Admin" POST "$BASE/auth/login" '{"email":"liveadmin2@test.com","password":"password123"}' 200
T "Login Bad Pwd (401)" POST "$BASE/auth/login" '{"email":"liveadmin2@test.com","password":"wrongpwd"}' 401
T "Profile (admin)" GET "$BASE/auth/profile?user_id=$AI" $null 200
T "Profile no user_id (401)" GET "$BASE/auth/profile" $null 401
T "Profile invalid user (401)" GET "$BASE/auth/profile?user_id=99999" $null 401

# --- USERS ---
Write-Host "`n--- USER LISTING ---" -ForegroundColor Cyan
T "Admin list users" GET "$BASE/users?user_id=$AI" $null 200
T "Supervisor list users" GET "$BASE/users?user_id=$SI" $null 200
T "Sub denied users (403)" GET "$BASE/users?user_id=$S1" $null 403

# --- PROJECTS ---
Write-Host "`n--- PROJECTS ---" -ForegroundColor Cyan
$r = T "Admin create project 1" POST "$BASE/projects?user_id=$AI" '{"name":"Live Project Alpha","description":"Test project","start_date":"2026-03-01","end_date":"2026-04-30","status":"active"}' 201
$P1 = $r.data.id; Write-Host "  Project1 ID: $P1" -ForegroundColor DarkYellow

$r = T "Sup create project 2" POST "$BASE/projects?user_id=$SI" '{"name":"Live Project Beta","description":"Sup project","start_date":"2026-03-01","end_date":"2026-05-31","status":"active"}' 201
$P2 = $r.data.id; Write-Host "  Project2 ID: $P2" -ForegroundColor DarkYellow

T "Sub denied create (403)" POST "$BASE/projects?user_id=$S1" '{"name":"Fail","start_date":"2026-03-01","end_date":"2026-04-30"}' 403
T "List projects (admin)" GET "$BASE/projects?user_id=$AI" $null 200
T "Show project" GET "$BASE/projects/$P1`?user_id=$AI" $null 200
T "Update project" PUT "$BASE/projects/$P1`?user_id=$AI" '{"name":"Alpha Updated"}' 200
T "Extend timeline" POST "$BASE/projects/$P1/extend-timeline?user_id=$AI" '{"extended_end_date":"2026-06-30","reason":"Need more time"}' 200
T "Assign Sub1 to P1" POST "$BASE/projects/$P1/assign-member?user_id=$AI" "{`"user_id`":$S1}" 200
T "Assign Sub2 to P2" POST "$BASE/projects/$P2/assign-member?user_id=$SI" "{`"user_id`":$S2}" 200
T "Sub1 list projects" GET "$BASE/projects?user_id=$S1" $null 200
T "Remove Sub1 from P1" POST "$BASE/projects/$P1/remove-member?user_id=$AI" "{`"user_id`":$S1}" 200

# Re-assign for tasks
T "Re-assign Sub1 to P1" POST "$BASE/projects/$P1/assign-member?user_id=$AI" "{`"user_id`":$S1}" 200

# --- TASKS ---
Write-Host "`n--- TASKS ---" -ForegroundColor Cyan
$r = T "Admin create task1" POST "$BASE/tasks?user_id=$AI" "{`"project_id`":$P1,`"title`":`"Task Alpha`",`"description`":`"Design work`",`"assigned_to`":$S1,`"priority`":`"high`",`"due_date`":`"2026-03-15`"}" 201
$T1 = $r.data.id; Write-Host "  Task1 ID: $T1" -ForegroundColor DarkYellow

$r = T "Sup create task2" POST "$BASE/tasks?user_id=$SI" "{`"project_id`":$P2,`"title`":`"Task Beta`",`"assigned_to`":$S2,`"priority`":`"medium`",`"due_date`":`"2026-04-01`"}" 201
$T2 = $r.data.id; Write-Host "  Task2 ID: $T2" -ForegroundColor DarkYellow

$r = T "Sup create standalone task" POST "$BASE/tasks?user_id=$SI" "{`"title`":`"Standalone Task`",`"assigned_to`":$S1,`"priority`":`"low`",`"due_date`":`"2026-03-20`"}" 201
$T3 = $r.data.id; Write-Host "  Task3 ID: $T3" -ForegroundColor DarkYellow

T "Sub denied create (403)" POST "$BASE/tasks?user_id=$S1" '{"title":"Fail","assigned_to":15}' 403
T "Sub1 list tasks" GET "$BASE/tasks?user_id=$S1" $null 200
T "Show task" GET "$BASE/tasks/$T1`?user_id=$S1" $null 200
T "Update task priority" PUT "$BASE/tasks/$T1`?user_id=$AI" '{"priority":"urgent"}' 200

# --- TASK WORKFLOW ---
Write-Host "`n--- TASK WORKFLOW ---" -ForegroundColor Cyan
T "Sub1 start task (in_progress)" PATCH "$BASE/tasks/$T1/status?user_id=$S1" '{"status":"in_progress","remarks":"Starting"}' 200
T "Sub1 complete task" PATCH "$BASE/tasks/$T1/status?user_id=$S1" '{"status":"completed","remarks":"Done"}' 200
T "Admin approve task1" POST "$BASE/tasks/$T1/approve?user_id=$AI" '{"remarks":"Well done"}' 200

T "Sub2 start task2" PATCH "$BASE/tasks/$T2/status?user_id=$S2" '{"status":"in_progress"}' 200
T "Sub2 complete task2" PATCH "$BASE/tasks/$T2/status?user_id=$S2" '{"status":"completed"}' 200
T "Sup reject task2" POST "$BASE/tasks/$T2/reject?user_id=$SI" '{"rejection_reason":"Needs more work"}' 200
T "Sub2 rework (rejected->in_progress)" PATCH "$BASE/tasks/$T2/status?user_id=$S2" '{"status":"in_progress","remarks":"Reworking"}' 200
T "Sub2 re-complete" PATCH "$BASE/tasks/$T2/status?user_id=$S2" '{"status":"completed"}' 200
T "Sup approve reworked task2" POST "$BASE/tasks/$T2/approve?user_id=$SI" '{"remarks":"Better"}' 200

# --- DAILY REPORTS ---
Write-Host "`n--- DAILY REPORTS ---" -ForegroundColor Cyan
$r = T "Sub1 submit report" POST "$BASE/daily-reports?user_id=$S1" "{`"task_id`":$T1,`"report_date`":`"2026-02-28`",`"description`":`"Wireframes 6h`",`"hours_spent`":6}" 201
$R1 = $r.data.id; Write-Host "  Report1 ID: $R1" -ForegroundColor DarkYellow

$r = T "Sub2 submit report" POST "$BASE/daily-reports?user_id=$S2" "{`"task_id`":$T2,`"report_date`":`"2026-02-28`",`"description`":`"Dev 8h`",`"hours_spent`":8}" 201
$R2 = $r.data.id; Write-Host "  Report2 ID: $R2" -ForegroundColor DarkYellow

T "Sub1 report no task" POST "$BASE/daily-reports?user_id=$S1" '{"report_date":"2026-02-27","description":"Meeting","hours_spent":3}' 201
T "Duplicate rejected (422)" POST "$BASE/daily-reports?user_id=$S1" "{`"task_id`":$T1,`"report_date`":`"2026-02-28`",`"description`":`"Dup`",`"hours_spent`":1}" 422
T "Admin list reports" GET "$BASE/daily-reports?user_id=$AI" $null 200
T "Show report" GET "$BASE/daily-reports/$R1`?user_id=$S1" $null 200
T "Update report" PUT "$BASE/daily-reports/$R1`?user_id=$S1" '{"hours_spent":7}' 200
T "Sup denied submit (403)" POST "$BASE/daily-reports?user_id=$SI" '{"report_date":"2026-02-28","description":"X","hours_spent":1}' 403

# --- HISTORIES ---
Write-Host "`n--- HISTORIES ---" -ForegroundColor Cyan
T "Task histories (admin)" GET "$BASE/histories/tasks?user_id=$AI" $null 200
T "Task histories for T1" GET "$BASE/histories/tasks?user_id=$AI&task_id=$T1" $null 200
T "Project histories" GET "$BASE/histories/projects?user_id=$AI" $null 200
T "Project histories for P1" GET "$BASE/histories/projects?user_id=$AI&project_id=$P1" $null 200
T "Sub1 own task histories" GET "$BASE/histories/tasks?user_id=$S1" $null 200

# --- REPORTS & KPIs ---
Write-Host "`n--- REPORTS & KPIs ---" -ForegroundColor Cyan
T "Sub1 own performance" GET "$BASE/reports/subordinate-performance?user_id=$S1&period=2026-02" $null 200
T "Admin view Sub2 perf" GET "$BASE/reports/subordinate-performance?user_id=$AI&period=2026-02" $null 200
T "Supervisor perf" GET "$BASE/reports/supervisor-performance?user_id=$SI&period=2026-02" $null 200
T "Org performance (admin)" GET "$BASE/reports/organization-performance?user_id=$AI&period=2026-02" $null 200
T "Sub denied org (403)" GET "$BASE/reports/organization-performance?user_id=$S1" $null 403
T "KPI history" GET "$BASE/reports/kpi-history?user_id=$AI" $null 200

# --- RBAC ---
Write-Host "`n--- RBAC ENFORCEMENT ---" -ForegroundColor Cyan
T "Sup denied project delete (403)" DELETE "$BASE/projects/$P2`?user_id=$SI" $null 403
T "Sup denied task delete (403)" DELETE "$BASE/tasks/$T2`?user_id=$SI" $null 403
T "Sub denied approve (403)" POST "$BASE/tasks/$T3/approve?user_id=$S1" '{"remarks":"self"}' 403
T "Sub denied update other report (403)" PUT "$BASE/daily-reports/$R2`?user_id=$S1" '{"hours_spent":1}' 403

# --- DELETE ---
Write-Host "`n--- DELETE OPERATIONS ---" -ForegroundColor Cyan
T "Sub1 delete own report" DELETE "$BASE/daily-reports/$R1`?user_id=$S1" $null 200
T "Admin delete task" DELETE "$BASE/tasks/$T3`?user_id=$AI" $null 200
T "Admin delete project" DELETE "$BASE/projects/$P1`?user_id=$AI" $null 200

# SUMMARY
Write-Host "`n========================================" -ForegroundColor Yellow
Write-Host " RESULTS: $pass PASSED / $fail FAILED / $total TOTAL" -ForegroundColor $(if($fail-eq 0){"Green"}else{"Red"})
Write-Host "========================================`n" -ForegroundColor Yellow
