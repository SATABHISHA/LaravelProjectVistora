# Professional Tax API Testing Commands

## Start Laravel Server First
```bash
php artisan serve
```

## 1. ADD Professional Tax API
```bash
curl -X POST "http://127.0.0.1:8000/api/professional-tax/add" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "state": "West Bengal",
    "minIncome": "15000",
    "maxIncome": "25000",
    "aboveIncome": "2500"
  }'
```

## 2. GET All Professional Tax Records
```bash
curl -X POST "http://127.0.0.1:8000/api/professional-tax/get" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corpId": "maco"
  }'
```

## 3. GET Professional Tax by ID (replace {id} with actual ID from add response)
```bash
curl -X GET "http://127.0.0.1:8000/api/professional-tax/1" \
  -H "Accept: application/json"
```

## 4. EDIT Professional Tax (replace {id} with actual ID)
```bash
curl -X PUT "http://127.0.0.1:8000/api/professional-tax/edit/1" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "state": "Maharashtra",
    "minIncome": "20000",
    "maxIncome": "30000",
    "aboveIncome": "3000"
  }'
```

## 5. DELETE Professional Tax (replace {id} with actual ID)
```bash
curl -X DELETE "http://127.0.0.1:8000/api/professional-tax/delete/1" \
  -H "Accept: application/json"
```

## PowerShell Commands (Alternative)

### 1. ADD
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/add" -Method POST -ContentType "application/json" -Body '{"corpId":"maco","companyName":"IMS MACO SERVICES INDIA PVT. LTD.","state":"West Bengal","minIncome":"15000","maxIncome":"25000","aboveIncome":"2500"}'
$response | ConvertTo-Json
```

### 2. GET All
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/get" -Method POST -ContentType "application/json" -Body '{"corpId":"maco"}'
$response | ConvertTo-Json
```

### 3. GET by ID
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/1" -Method GET
$response | ConvertTo-Json
```

### 4. EDIT
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/edit/1" -Method PUT -ContentType "application/json" -Body '{"state":"Maharashtra","minIncome":"20000","maxIncome":"30000","aboveIncome":"3000"}'
$response | ConvertTo-Json
```

### 5. DELETE
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/delete/1" -Method DELETE
$response | ConvertTo-Json
```

## Expected Responses

### Successful ADD Response:
```json
{
  "status": true,
  "message": "Professional tax record created successfully",
  "data": {
    "id": 1,
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "state": "West Bengal",
    "minIncome": "15000",
    "maxIncome": "25000",
    "aboveIncome": "2500",
    "created_at": "2025-10-26T...",
    "updated_at": "2025-10-26T..."
  }
}
```

### Default Values Test (null/empty values):
```bash
curl -X POST "http://127.0.0.1:8000/api/professional-tax/add" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "corpId": "test",
    "companyName": "Test Company",
    "state": "Test State",
    "minIncome": null,
    "maxIncome": "",
    "aboveIncome": null
  }'
```

This should return minIncome, maxIncome, and aboveIncome as "0".

## Testing Notes:
1. Start with ADD API first to create a record
2. Note the ID from the response for EDIT and DELETE operations
3. Test default values (null/empty) to verify they become "0"
4. Use GET APIs to verify changes after EDIT operations
5. All APIs return JSON responses with status, message, and data fields