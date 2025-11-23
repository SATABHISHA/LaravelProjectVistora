Write-Host "`n=== Employee List API Test ===" -ForegroundColor Magenta

$baseUrl = "http://127.0.0.1:8000/api"
$corpId = "maco"
$companyName = "IMS MACO SERVICES INDIA PVT. LTD."

Write-Host "`nEndpoint: GET /employee-list" -ForegroundColor Cyan
Write-Host "Parameters:" -ForegroundColor Yellow
Write-Host "  corp_id: $corpId" -ForegroundColor Gray
Write-Host "  company_name: $companyName" -ForegroundColor Gray

Write-Host "`nSending request..." -ForegroundColor Yellow

Start-Sleep -Seconds 2

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/employee-list" -Method GET -Body @{
        corp_id = $corpId
        company_name = $companyName
    } -ErrorAction Stop
    
    Write-Host "`n========================================" -ForegroundColor Green
    Write-Host "  SUCCESS" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    
    Write-Host "`nResponse:" -ForegroundColor Cyan
    $response | ConvertTo-Json -Depth 10
    
    Write-Host "`n----------------------------------------" -ForegroundColor Yellow
    Write-Host "Summary:" -ForegroundColor Yellow
    Write-Host "  Status: $($response.status)" -ForegroundColor White
    Write-Host "  Message: $($response.message)" -ForegroundColor White
    Write-Host "  Corp ID: $($response.corp_id)" -ForegroundColor White  
    Write-Host "  Company: $($response.company_name)" -ForegroundColor White
    Write-Host "  Total Employees: $($response.total_employees)" -ForegroundColor White
    
    if ($response.total_employees -gt 0) {
        Write-Host "`nFirst 3 employees:" -ForegroundColor Cyan
        $response.data | Select-Object -First 3 | Format-Table -AutoSize
    }
    
} catch {
    Write-Host "`n========================================" -ForegroundColor Red
    Write-Host "  ERROR" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

Write-Host "`n"
