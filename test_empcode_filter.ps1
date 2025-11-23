# Test empCode filter for FMS files-by-category endpoint
$baseUrl = "http://127.0.0.1:8000/api"

Write-Host "`n========================================" -ForegroundColor Magenta
Write-Host "  FMS empCode Filter Testing" -ForegroundColor Magenta
Write-Host "========================================`n" -ForegroundColor Magenta

# Step 1: Add storage quota
Write-Host "Step 1: Adding storage quota..." -ForegroundColor Cyan
curl.exe -s -X POST "$baseUrl/company-storage/CORP001?size=500&sizeUit=MB"
Write-Host ""

# Step 2: Create test files
Write-Host "Step 2: Creating test files..." -ForegroundColor Cyan
$testFile1 = "$env:TEMP\resume_emp001_v1.pdf"
$testFile2 = "$env:TEMP\resume_emp002.pdf"
$testFile3 = "$env:TEMP\resume_emp001_v2.pdf"

$content = New-Object byte[] 102400  # 100KB each
[System.IO.File]::WriteAllBytes($testFile1, $content)
[System.IO.File]::WriteAllBytes($testFile2, $content)
[System.IO.File]::WriteAllBytes($testFile3, $content)
Write-Host "3 test files created (100KB each)" -ForegroundColor Green
Write-Host ""

# Step 3: Upload files for different employees
Write-Host "Step 3: Uploading files..." -ForegroundColor Cyan
Write-Host "  - Uploading resume for EMP001 (v1)..." -ForegroundColor Gray
curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=CORP001" `
    -F "companyName=Test Company" `
    -F "empCode=EMP001" `
    -F "fileCategory=Resume" `
    -F "file=@$testFile1" | ConvertFrom-Json | ConvertTo-Json -Depth 5

Write-Host "`n  - Uploading resume for EMP002..." -ForegroundColor Gray
curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=CORP001" `
    -F "companyName=Test Company" `
    -F "empCode=EMP002" `
    -F "fileCategory=Resume" `
    -F "file=@$testFile2" | ConvertFrom-Json | ConvertTo-Json -Depth 5

Write-Host "`n  - Uploading resume for EMP001 (v2)..." -ForegroundColor Gray
curl.exe -s -X POST "$baseUrl/fms/upload-document" `
    -F "corpId=CORP001" `
    -F "companyName=Test Company" `
    -F "empCode=EMP001" `
    -F "fileCategory=Resume" `
    -F "file=@$testFile3" | ConvertFrom-Json | ConvertTo-Json -Depth 5

Write-Host ""

# Step 4: Test WITHOUT empCode filter (should return all 3)
Write-Host "Step 4: Fetching ALL Resume files (no empCode filter)..." -ForegroundColor Cyan
$response1 = curl.exe -s -X GET "$baseUrl/fms/files-by-category?corpId=CORP001&companyName=Test+Company&fileCategory=Resume"
Write-Host $response1 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Step 5: Test WITH empCode=EMP001 (should return 2 files)
Write-Host "Step 5: Fetching Resume files for EMP001 only..." -ForegroundColor Cyan
$response2 = curl.exe -s -X GET "$baseUrl/fms/files-by-category?corpId=CORP001&companyName=Test+Company&fileCategory=Resume&empCode=EMP001"
Write-Host $response2 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Step 6: Test WITH empCode=EMP002 (should return 1 file)
Write-Host "Step 6: Fetching Resume files for EMP002 only..." -ForegroundColor Cyan
$response3 = curl.exe -s -X GET "$baseUrl/fms/files-by-category?corpId=CORP001&companyName=Test+Company&fileCategory=Resume&empCode=EMP002"
Write-Host $response3 | ConvertFrom-Json | ConvertTo-Json -Depth 8
Write-Host ""

# Cleanup
Remove-Item $testFile1, $testFile2, $testFile3 -ErrorAction SilentlyContinue

Write-Host "`n========================================" -ForegroundColor Green
Write-Host "  TESTING COMPLETED" -ForegroundColor Green
Write-Host "========================================`n" -ForegroundColor Green
