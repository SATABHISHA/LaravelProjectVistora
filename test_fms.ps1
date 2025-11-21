# FMS Testing Script
$baseUrl = "http://127.0.0.1:8000/api"

Write-Host "`n========================================" -ForegroundColor Magenta
Write-Host "  FMS API COMPREHENSIVE TESTING" -ForegroundColor Magenta
Write-Host "========================================`n" -ForegroundColor Magenta

# Test 1: Upload a valid document (1MB)
Write-Host "TEST 1: Upload Valid Document (1MB Resume)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$testFile1MB = "$env:TEMP\resume_1mb.pdf"
$content1MB = New-Object byte[] 1048576  # 1MB
(New-Object Random).NextBytes($content1MB)
[System.IO.File]::WriteAllBytes($testFile1MB, $content1MB)

$response1 = curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=TEST001" `
    -F "companyName=TechCorp Solutions" `
    -F "empCode=EMP123" `
    -F "fileCategory=Resume" `
    -F "file=@$testFile1MB"

Write-Host $response1 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 2: Upload with fileCategory = Certificate (2MB)
Write-Host "TEST 2: Upload Certificate (2MB)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$testFile2MB = "$env:TEMP\certificate_2mb.pdf"
$content2MB = New-Object byte[] 2097152  # 2MB
(New-Object Random).NextBytes($content2MB)
[System.IO.File]::WriteAllBytes($testFile2MB, $content2MB)

$response2 = curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=TEST001" `
    -F "companyName=TechCorp Solutions" `
    -F "empCode=EMP456" `
    -F "fileCategory=Certificate" `
    -F "file=@$testFile2MB"

Write-Host $response2 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 3: Try uploading file > 5MB (should fail)
Write-Host "TEST 3: Upload File Exceeding 5MB (Expected Failure)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$testFile6MB = "$env:TEMP\large_6mb.pdf"
$content6MB = New-Object byte[] 6291456  # 6MB
[System.IO.File]::WriteAllBytes($testFile6MB, $content6MB)

$response3 = curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=TEST001" `
    -F "companyName=TechCorp Solutions" `
    -F "empCode=EMP789" `
    -F "fileCategory=Other" `
    -F "file=@$testFile6MB"

Write-Host $response3 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 4: Summary by Company
Write-Host "TEST 4: Summary by Company (Grouped by Category)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$response4 = curl.exe -s -X GET "$baseUrl/fms/summary-by-company?corpId=TEST001&companyName=TechCorp+Solutions"
Write-Host $response4 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 5: Files by Category
Write-Host "TEST 5: Files by Category (Resume)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$response5 = curl.exe -s -X GET "$baseUrl/fms/files-by-category?corpId=TEST001&companyName=TechCorp+Solutions&fileCategory=Resume"
Write-Host $response5 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 6: Company Storage Overview
Write-Host "TEST 6: Company Storage Overview (Multi-table Join)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$response6 = curl.exe -s -X GET "$baseUrl/fms/company-storage-overview?corp_id=TEST001"
Write-Host $response6 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 7: Try uploading when quota is exceeded
Write-Host "TEST 7: Upload More Files to Test Quota (4MB + 4MB)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

# Upload 4MB file (should succeed - total so far: 1+2+4=7MB < 100MB)
$testFile4MB_1 = "$env:TEMP\contract_4mb_1.pdf"
$content4MB = New-Object byte[] 4194304  # 4MB
[System.IO.File]::WriteAllBytes($testFile4MB_1, $content4MB)

$response7a = curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=TEST001" `
    -F "companyName=TechCorp Solutions" `
    -F "empCode=EMP111" `
    -F "fileCategory=Contract" `
    -F "file=@$testFile4MB_1"

Write-Host $response7a | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Upload another 4MB (total: 1+2+4+4=11MB < 100MB)
$testFile4MB_2 = "$env:TEMP\contract_4mb_2.pdf"
[System.IO.File]::WriteAllBytes($testFile4MB_2, $content4MB)

$response7b = curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=TEST001" `
    -F "companyName=TechCorp Solutions" `
    -F "empCode=EMP222" `
    -F "fileCategory=Contract" `
    -F "file=@$testFile4MB_2"

Write-Host $response7b | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 8: Updated Summary
Write-Host "TEST 8: Updated Summary After Multiple Uploads" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$response8 = curl.exe -s -X GET "$baseUrl/fms/summary-by-company?corpId=TEST001&companyName=TechCorp+Solutions"
Write-Host $response8 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Test 9: Files by Category (Contract - should show 2 files)
Write-Host "TEST 9: Files by Category (Contract - 2 files)" -ForegroundColor Cyan
Write-Host "-------------------------------------------" -ForegroundColor Gray

$response9 = curl.exe -s -X GET "$baseUrl/fms/files-by-category?corpId=TEST001&companyName=TechCorp+Solutions&fileCategory=Contract"
Write-Host $response9 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Cleanup temp files
Remove-Item $testFile1MB, $testFile2MB, $testFile6MB, $testFile4MB_1, $testFile4MB_2 -ErrorAction SilentlyContinue

Write-Host "`n========================================" -ForegroundColor Green
Write-Host "  TESTING COMPLETED" -ForegroundColor Green
Write-Host "========================================`n" -ForegroundColor Green
