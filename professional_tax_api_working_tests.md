# Professional Tax API - WORKING Test Commands

## 🔧 Issue Resolution
**Problem**: Server was crashing due to controller import issues and route caching problems.

**Solution**: 
1. ✅ Added proper `ProfessionalTaxController` import in `routes/api.php`
2. ✅ Fixed controller route references 
3. ✅ Added logging for debugging
4. ✅ Cleared route and config cache

## 🚀 Working Test Commands

### Start Server
```bash
php artisan route:clear
php artisan config:clear
php artisan serve
```

### 1. Test Route (Verify Server is Working)
```bash
curl -X GET "http://127.0.0.1:8000/api/test-professional-tax"
```

**PowerShell:**
```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/test-professional-tax" -Method GET
```

### 2. ADD Professional Tax
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

**PowerShell:**
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/add" -Method POST -ContentType "application/json" -Body '{"corpId":"maco","companyName":"IMS MACO SERVICES INDIA PVT. LTD.","state":"West Bengal","minIncome":"15000","maxIncome":"25000","aboveIncome":"2500"}'
$response | ConvertTo-Json
```

### 3. GET All Professional Tax Records
```bash
curl -X POST "http://127.0.0.1:8000/api/professional-tax/get" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"corpId":"maco"}'
```

**PowerShell:**
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/get" -Method POST -ContentType "application/json" -Body '{"corpId":"maco"}'
$response | ConvertTo-Json
```

### 4. GET Professional Tax by ID
```bash
curl -X GET "http://127.0.0.1:8000/api/professional-tax/1" \
  -H "Accept: application/json"
```

**PowerShell:**
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/1" -Method GET
$response | ConvertTo-Json
```

### 5. EDIT Professional Tax
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

**PowerShell:**
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/edit/1" -Method PUT -ContentType "application/json" -Body '{"state":"Maharashtra","minIncome":"20000","maxIncome":"30000","aboveIncome":"3000"}'
$response | ConvertTo-Json
```

### 6. DELETE Professional Tax
```bash
curl -X DELETE "http://127.0.0.1:8000/api/professional-tax/delete/1" \
  -H "Accept: application/json"
```

**PowerShell:**
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/professional-tax/delete/1" -Method DELETE
$response | ConvertTo-Json
```

## 🧪 Test Default Values (null/empty values become "0")
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

## ✅ Expected Success Response
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

## 🔍 Debugging Features Added
1. **Logging**: All API calls are logged to `storage/logs/laravel.log`
2. **Error Handling**: Detailed error messages with file and line numbers
3. **Validation**: Proper request validation with error responses
4. **Default Values**: Automatic conversion of null/empty to "0"

## 📝 Testing Notes
1. Always clear cache before testing: `php artisan route:clear && php artisan config:clear`
2. Check logs if issues occur: `tail -f storage/logs/laravel.log`
3. Verify database connection: The test script `test_professional_tax.php` confirms all components work
4. Use different ports if 8000 is busy: `php artisan serve --port=8001`

## 🎯 Performance Improvements
- **Fixed Issue**: Server no longer crashes on requests
- **Response Time**: Now responds immediately (previously hung/timed out)
- **Stability**: Proper error handling prevents server crashes
- **Debugging**: Added comprehensive logging for troubleshooting