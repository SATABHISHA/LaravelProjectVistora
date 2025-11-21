# FMS API Quick Reference

## Base URL
```
http://127.0.0.1:8000/api
```

---

## 1. Upload Document
```bash
POST /fms/upload-document
```

### cURL Example
```bash
curl -X POST http://127.0.0.1:8000/api/fms/upload-document \
  -F "corpId=TEST001" \
  -F "companyName=TechCorp Solutions" \
  -F "empCode=EMP123" \
  -F "fileCategory=Resume" \
  -F "file=@/path/to/document.pdf"
```

### PowerShell Example
```powershell
curl.exe -X POST http://127.0.0.1:8000/api/fms/upload-document `
  -F "corpId=TEST001" `
  -F "companyName=TechCorp Solutions" `
  -F "empCode=EMP123" `
  -F "fileCategory=Resume" `
  -F "file=@C:\path\to\document.pdf"
```

### Response (Success)
```json
{
  "status": true,
  "message": "File uploaded successfully",
  "data": {
    "id": 1,
    "filename": "document.pdf",
    "file_size": 1048576,
    "file_size_mb": 1,
    "uploaded_at": "2025-11-21T01:19:21.000000Z",
    "storage_used": "1 MB",
    "storage_allocated": "100 MB"
  }
}
```

### Response (File Too Large)
```json
{
  "status": false,
  "message": "Validation failed",
  "errors": {
    "file": ["The file field must not be greater than 5120 kilobytes."]
  }
}
```

### Response (Quota Exceeded)
```json
{
  "status": false,
  "message": "Storage limit exceeded. Please contact us to upgrade your plan."
}
```

---

## 2. Summary by Company
```bash
GET /fms/summary-by-company
```

### cURL Example
```bash
curl -X GET "http://127.0.0.1:8000/api/fms/summary-by-company?corpId=TEST001&companyName=TechCorp+Solutions"
```

### PowerShell Example
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/fms/summary-by-company" `
  -Method GET `
  -Body @{
    corpId = "TEST001"
    companyName = "TechCorp Solutions"
  }
$response | ConvertTo-Json -Depth 8
```

### Response
```json
{
  "status": true,
  "corpId": "TEST001",
  "companyName": "TechCorp Solutions",
  "summary": [
    {
      "fileCategory": "Certificate",
      "totalFiles": 1,
      "totalSize": "2 MB",
      "totalSizeBytes": "2097152"
    },
    {
      "fileCategory": "Contract",
      "totalFiles": 2,
      "totalSize": "8 MB",
      "totalSizeBytes": "8388608"
    },
    {
      "fileCategory": "Resume",
      "totalFiles": 1,
      "totalSize": "1 MB",
      "totalSizeBytes": "1048576"
    }
  ]
}
```

---

## 3. Files by Category
```bash
GET /fms/files-by-category
```

### cURL Example
```bash
curl -X GET "http://127.0.0.1:8000/api/fms/files-by-category?corpId=TEST001&companyName=TechCorp+Solutions&fileCategory=Resume"
```

### PowerShell Example
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/fms/files-by-category" `
  -Method GET `
  -Body @{
    corpId = "TEST001"
    companyName = "TechCorp Solutions"
    fileCategory = "Resume"
  }
$response | ConvertTo-Json -Depth 8
```

### Response
```json
{
  "status": true,
  "corpId": "TEST001",
  "companyName": "TechCorp Solutions",
  "fileCategory": "Resume",
  "totalFiles": 1,
  "files": [
    {
      "id": 1,
      "filename": "resume_1mb.pdf",
      "fileType": "PDF",
      "empCode": "EMP123",
      "file_size": "1 MB",
      "downloadUrl": "http://127.0.0.1:8000/storage/fms_documents/1763687960_resume_1mb.pdf",
      "uploaded_at": "2025-11-21T01:19:21.000000Z"
    }
  ]
}
```

---

## 4. Company Storage Overview
```bash
GET /fms/company-storage-overview
```

### cURL Example
```bash
curl -X GET "http://127.0.0.1:8000/api/fms/company-storage-overview?corp_id=TEST001"
```

### PowerShell Example
```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/fms/company-storage-overview" `
  -Method GET `
  -Body @{
    corp_id = "TEST001"
  }
$response | ConvertTo-Json -Depth 8
```

### Response
```json
{
  "status": true,
  "corp_id": "TEST001",
  "totalCompanies": 1,
  "companies": [
    {
      "companyName": "TechCorp Solutions",
      "totalEmployees": 15,
      "totalStorageUsedGB": 0.0102,
      "totalStorageUsedBytes": 11534336
    }
  ]
}
```

---

## Common File Categories
- Resume
- Certificate
- Contract
- Agreement
- Invoice
- Report
- Other

---

## File Size Limits
- **Maximum file size**: 5MB (5,120 KB)
- **Validation**: Both Laravel validator and controller logic
- **Storage quota**: Checked against `company_storage` table

---

## Storage Quota Management

### Add Storage Quota
```bash
curl -X POST "http://127.0.0.1:8000/api/company-storage/TEST001?size=100&sizeUit=MB"
```

### Check Storage Summary
```bash
curl -X GET "http://127.0.0.1:8000/api/company-storage/summary/TEST001?human=true"
```

---

## Error Codes

| Status Code | Meaning |
|------------|---------|
| 201 | Upload successful |
| 200 | Query successful |
| 400 | File exceeds 5MB or quota exceeded |
| 422 | Validation failed (missing/invalid fields) |
| 500 | Server error |

---

## Testing Script
Run comprehensive tests using the PowerShell script:

```powershell
.\test_fms.ps1
```

This will:
1. Create test files (1MB, 2MB, 4MB, 6MB)
2. Test all 4 API endpoints
3. Verify quota validation
4. Test file size limits
5. Check category grouping
6. Validate download URLs
7. Clean up test files

---

## File Download
Files are accessible via the generated `downloadUrl`:

```
http://127.0.0.1:8000/storage/fms_documents/{timestamp}_{filename}
```

Example:
```
http://127.0.0.1:8000/storage/fms_documents/1763687960_resume_1mb.pdf
```

**Note**: Ensure `php artisan storage:link` has been run to create the symbolic link.

---

## Database Schema

### fms_employee_documents
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| corpId | varchar(10) | Corporation ID |
| companyName | varchar(100) | Company name |
| empCode | varchar(20) | Employee code |
| fileCategory | varchar(50) | File category |
| filename | varchar(255) | Original filename |
| file | varchar(500) | Storage path |
| file_size | bigint | Size in bytes |
| created_at | timestamp | Upload time |
| updated_at | timestamp | Last modified |

### Indexes
- `corpId`
- `corpId, companyName`
- `corpId, companyName, fileCategory`
- `corpId, empCode`
