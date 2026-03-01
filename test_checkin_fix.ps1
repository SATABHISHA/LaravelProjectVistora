# Test script to verify the checkIn/checkOut fix
# Scenario: Two users with same corp_id but different emails
# should NOT see each other's check-in/check-out times

$baseUrl = "http://127.0.0.1:8099/api"
$testCorpId = "test"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  CheckIn/CheckOut Bug Fix Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# -------------------------------------------------------
# Clean up: Delete any existing attendance for today for these test empCodes
# -------------------------------------------------------
Write-Host "[SETUP] Cleaning up today's attendance records..." -ForegroundColor Yellow

$today = (Get-Date).ToString("yyyy-MM-dd")
$cleanupCmd = "php artisan tinker --execute=`"`$d=\App\Models\Attendance::where('date','$today')->whereIn('empCode',['FIXTEST001','FIXTEST002'])->delete();echo `$d.' deleted';`""
Invoke-Expression $cleanupCmd

Write-Host ""

# -------------------------------------------------------
# Register two test users with unique empCodes but same corp_id
# -------------------------------------------------------
Write-Host "[SETUP] Registering test users..." -ForegroundColor Yellow

$user1Reg = @{
    corp_id = $testCorpId
    email_id = "fixtest_user1@gmail.com"
    username = "test"
    password = "123456"
    empcode = "FIXTEST001"
    company_name = "FixTestCompany"
} | ConvertTo-Json

try {
    $reg1 = Invoke-RestMethod -Uri "$baseUrl/userlogin/register" -Method POST -Body $user1Reg -ContentType "application/json"
    Write-Host "  User 1 registered successfully" -ForegroundColor Green
} catch {
    $errorResp = $_.Exception.Response
    $statusCode = [int]$errorResp.StatusCode
    if ($statusCode -eq 409) {
        Write-Host "  User 1 already exists (OK)" -ForegroundColor DarkYellow
    } else {
        Write-Host "  User 1 registration error ($statusCode)" -ForegroundColor Red
    }
}

$user2Reg = @{
    corp_id = $testCorpId
    email_id = "fixtest_rjk@gmail.com"
    username = "test"
    password = "654321"
    empcode = "FIXTEST002"
    company_name = "FixTestCompany"
} | ConvertTo-Json

try {
    $reg2 = Invoke-RestMethod -Uri "$baseUrl/userlogin/register" -Method POST -Body $user2Reg -ContentType "application/json"
    Write-Host "  User 2 registered successfully" -ForegroundColor Green
} catch {
    $errorResp = $_.Exception.Response
    $statusCode = [int]$errorResp.StatusCode
    if ($statusCode -eq 409) {
        Write-Host "  User 2 already exists (OK)" -ForegroundColor DarkYellow
    } else {
        Write-Host "  User 2 registration error ($statusCode)" -ForegroundColor Red
    }
}

Write-Host ""

# -------------------------------------------------------
# Step 1: Login as User 1
# -------------------------------------------------------
Write-Host "[STEP 1] Login as User 1 (test, fixtest_user1@gmail.com)..." -ForegroundColor Yellow
$login1Body = @{
    corp_id = $testCorpId
    email_id = "fixtest_user1@gmail.com"
    password = "123456"
} | ConvertTo-Json

$login1 = Invoke-RestMethod -Uri "$baseUrl/userlogin/login" -Method POST -Body $login1Body -ContentType "application/json"
Write-Host "  Login: $($login1.message)" -ForegroundColor Green
$user1Data = $login1.user
Write-Host "  empcode=$($user1Data.empcode), username=$($user1Data.username), company=$($user1Data.company_name)" -ForegroundColor Gray

# -------------------------------------------------------
# Step 2: User 1 checks in with a SHARED puid (simulating the bug)
# Both users send the same puid (e.g. device-based or username-based)
# -------------------------------------------------------
Write-Host ""
Write-Host "[STEP 2] User 1 checks in (shared puid='SHARED_PUID_test')..." -ForegroundColor Yellow

$sharedPuid = "SHARED_PUID_test"

$checkin1Body = @{
    puid = $sharedPuid
    corpId = $testCorpId
    userName = "test"
    empCode = $user1Data.empcode
    companyName = $user1Data.company_name
    time = "9:00 AM"
    Lat = "28.6139"
    Long = "77.2090"
    Address = "New Delhi"
} | ConvertTo-Json

$checkin1 = Invoke-RestMethod -Uri "$baseUrl/attendance/check" -Method POST -Body $checkin1Body -ContentType "application/json"
Write-Host "  Result: $($checkin1.message)" -ForegroundColor $(if($checkin1.status){"Green"}else{"Red"})
Write-Host "  checkIn=$($checkin1.data.checkIn), empCode=$($checkin1.data.empCode), status=$($checkin1.data.status)" -ForegroundColor Gray

# -------------------------------------------------------  
# Step 3: User 1 logs out (conceptual)
# -------------------------------------------------------
Write-Host ""
Write-Host "[STEP 3] User 1 logs out" -ForegroundColor Yellow

# -------------------------------------------------------
# Step 4: Login as User 2
# -------------------------------------------------------
Write-Host ""
Write-Host "[STEP 4] Login as User 2 (test, fixtest_rjk@gmail.com)..." -ForegroundColor Yellow
$login2Body = @{
    corp_id = $testCorpId
    email_id = "fixtest_rjk@gmail.com"
    password = "654321"
} | ConvertTo-Json

$login2 = Invoke-RestMethod -Uri "$baseUrl/userlogin/login" -Method POST -Body $login2Body -ContentType "application/json"
Write-Host "  Login: $($login2.message)" -ForegroundColor Green
$user2Data = $login2.user
Write-Host "  empcode=$($user2Data.empcode), username=$($user2Data.username), company=$($user2Data.company_name)" -ForegroundColor Gray

# -------------------------------------------------------
# Step 5: User 2 checks in with THE SAME shared puid
# -------------------------------------------------------
Write-Host ""
Write-Host "[STEP 5] User 2 checks in (same shared puid='SHARED_PUID_test')..." -ForegroundColor Yellow

$checkin2Body = @{
    puid = $sharedPuid
    corpId = $testCorpId
    userName = "test"
    empCode = $user2Data.empcode
    companyName = $user2Data.company_name
    time = "10:30 AM"
    Lat = "19.0760"
    Long = "72.8777"
    Address = "Mumbai"
} | ConvertTo-Json

$checkin2 = Invoke-RestMethod -Uri "$baseUrl/attendance/check" -Method POST -Body $checkin2Body -ContentType "application/json"
Write-Host "  Result: $($checkin2.message)" -ForegroundColor $(if($checkin2.status){"Green"}else{"Red"})
Write-Host "  checkIn=$($checkin2.data.checkIn), empCode=$($checkin2.data.empCode), status=$($checkin2.data.status)" -ForegroundColor Gray

# -------------------------------------------------------
# Step 6: User 2 logs out
# -------------------------------------------------------
Write-Host ""
Write-Host "[STEP 6] User 2 logs out" -ForegroundColor Yellow

# -------------------------------------------------------
# Step 7: User 1 logs back in - verify they see THEIR OWN check-in time
# -------------------------------------------------------
Write-Host ""
Write-Host "[STEP 7] User 1 logs back in - checking attendance..." -ForegroundColor Yellow

# Fetch via check-exists (what Flutter app uses)
$existsUrl1 = "$baseUrl/attendance/check-exists/$testCorpId/$($user1Data.empcode)/$($user1Data.company_name)"
$user1Att = Invoke-RestMethod -Uri $existsUrl1 -Method GET
Write-Host "  User 1 attendance: checkIn=$($user1Att.data.checkIn), status=$($user1Att.data.status)" -ForegroundColor Gray

$existsUrl2 = "$baseUrl/attendance/check-exists/$testCorpId/$($user2Data.empcode)/$($user2Data.company_name)"
$user2Att = Invoke-RestMethod -Uri $existsUrl2 -Method GET
Write-Host "  User 2 attendance: checkIn=$($user2Att.data.checkIn), status=$($user2Att.data.status)" -ForegroundColor Gray

# -------------------------------------------------------
# VERIFICATION
# -------------------------------------------------------
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  VERIFICATION RESULTS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$allPassed = $true

# Test 1: User 1 checkIn = 9:00 AM
if ($user1Att.data.checkIn -eq "9:00 AM") {
    Write-Host "  [PASS] User 1 checkIn = 9:00 AM (correct)" -ForegroundColor Green
} else {
    Write-Host "  [FAIL] User 1 checkIn = '$($user1Att.data.checkIn)' (expected '9:00 AM')" -ForegroundColor Red
    $allPassed = $false
}

# Test 2: User 2 checkIn = 10:30 AM
if ($user2Att.data.checkIn -eq "10:30 AM") {
    Write-Host "  [PASS] User 2 checkIn = 10:30 AM (correct)" -ForegroundColor Green
} else {
    Write-Host "  [FAIL] User 2 checkIn = '$($user2Att.data.checkIn)' (expected '10:30 AM')" -ForegroundColor Red
    $allPassed = $false
}

# Test 3: Both should still be status IN (no accidental checkout)
if ($user1Att.data.status -eq "IN") {
    Write-Host "  [PASS] User 1 status = IN (not accidentally checked out)" -ForegroundColor Green
} else {
    Write-Host "  [FAIL] User 1 status = '$($user1Att.data.status)' (expected 'IN')" -ForegroundColor Red
    $allPassed = $false
}

if ($user2Att.data.status -eq "IN") {
    Write-Host "  [PASS] User 2 status = IN (not accidentally checked out)" -ForegroundColor Green
} else {
    Write-Host "  [FAIL] User 2 status = '$($user2Att.data.status)' (expected 'IN')" -ForegroundColor Red
    $allPassed = $false
}

# Test 4: User 2 check-in should NOT have been treated as checkout of User 1
if ($checkin2.message -eq "Check-in successful") {
    Write-Host "  [PASS] User 2 got 'Check-in successful' (not 'Check-out')" -ForegroundColor Green
} else {
    Write-Host "  [FAIL] User 2 got '$($checkin2.message)' (expected 'Check-in successful')" -ForegroundColor Red
    $allPassed = $false
}

# Test 5: Cross-check via today-hours
Write-Host ""
Write-Host "  --- Cross-check via today-hours ---" -ForegroundColor Cyan
$h1 = Invoke-RestMethod -Uri "$baseUrl/attendance/today-hours/$($user1Data.empcode)/$testCorpId/$($user1Data.company_name)" -Method GET
$h2 = Invoke-RestMethod -Uri "$baseUrl/attendance/today-hours/$($user2Data.empcode)/$testCorpId/$($user2Data.company_name)" -Method GET

if ($h1.data.checkIn -eq "9:00 AM") {
    Write-Host "  [PASS] today-hours User 1 checkIn = 9:00 AM" -ForegroundColor Green
} else {
    Write-Host "  [FAIL] today-hours User 1 checkIn = '$($h1.data.checkIn)'" -ForegroundColor Red
    $allPassed = $false
}

if ($h2.data.checkIn -eq "10:30 AM") {
    Write-Host "  [PASS] today-hours User 2 checkIn = 10:30 AM" -ForegroundColor Green
} else {
    Write-Host "  [FAIL] today-hours User 2 checkIn = '$($h2.data.checkIn)'" -ForegroundColor Red
    $allPassed = $false
}

Write-Host ""
if ($allPassed) {
    Write-Host "  *** ALL TESTS PASSED - Bug is fixed! ***" -ForegroundColor Green
} else {
    Write-Host "  *** SOME TESTS FAILED ***" -ForegroundColor Red
}
Write-Host ""
