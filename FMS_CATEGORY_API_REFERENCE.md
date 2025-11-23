# FMS Category API Reference

## Table: fms_categories
Fields: corpId (string,10), companyName (string,100), empCode (string,20), fullName (string,200), fileCategory (string,50), timestamps.
Unique: (corpId, companyName, empCode, fileCategory)

---
## 1. Create Category
POST /api/fms/category

Body (JSON):
{
  "corpId": "maco",
  "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
  "empCode": "EMP001",
  "fullName": "John M Doe",
  "fileCategory": "Resume"
}

Response 201:
{
  "status": true,
  "message": "Category created successfully",
  "data": { ... created record ... }
}

Conflict 409 (duplicate):
{
  "status": false,
  "message": "Category already exists for this employee"
}

Curl:
```
curl -X POST http://127.0.0.1:8000/api/fms/category \
 -H "Content-Type: application/json" \
 -d '{"corpId":"maco","companyName":"IMS MACO SERVICES INDIA PVT. LTD.","empCode":"EMP001","fullName":"John M Doe","fileCategory":"Resume"}'
```

---
## 2. Update Category
PUT /api/fms/category/{id}

Body (any of):
{
  "fullName": "John Michael Doe",
  "fileCategory": "Experience Certificate"
}

Responses:
- 200 Success
- 404 Not found
- 409 Duplicate (if changing fileCategory to existing one for same employee)

Curl:
```
curl -X PUT http://127.0.0.1:8000/api/fms/category/15 \
 -H "Content-Type: application/json" \
 -d '{"fileCategory":"Experience Certificate"}'
```

---
## 3. Delete Category
DELETE /api/fms/category/{id}

Response 200:
{
  "status": true,
  "message": "Category deleted successfully"
}

404 if missing.

Curl:
```
curl -X DELETE http://127.0.0.1:8000/api/fms/category/15
```

---
## 4. Category Summary
GET /api/fms/category-summary?corpId=maco&companyName=IMS%20MACO%20SERVICES%20INDIA%20PVT.%20LTD.&empCode=EMP001&fileCategory=Resume

Required: corpId, companyName
Optional: empCode, fileCategory

Response 200:
{
  "status": true,
  "message": "Category summary retrieved successfully",
  "filters": {
    "corpId": "maco",
    "companyName": "IMS MACO SERVICES INDIA PVT. LTD.",
    "empCode": "EMP001",
    "fileCategory": "Resume"
  },
  "totalCategories": 1,
  "data": [
    {
      "fileCategory": "Resume",
      "totalFiles": 3,
      "totalFileSizeBytes": 452187,
      "totalFileSizeMB": 0.4313
    }
  ]
}

Curl (no optional filters):
```
curl "http://127.0.0.1:8000/api/fms/category-summary?corpId=maco&companyName=IMS%20MACO%20SERVICES%20INDIA%20PVT.%20LTD."
```

Curl (all filters):
```
curl "http://127.0.0.1:8000/api/fms/category-summary?corpId=maco&companyName=IMS%20MACO%20SERVICES%20INDIA%20PVT.%20LTD.&empCode=EMP001&fileCategory=Resume"
```

Notes:
- Summary derives counts and sizes from fms_employee_documents only; fms_categories table is for metadata and uniqueness control.
- fileCategory filtering narrows to single category aggregation; without it all categories under applied filters are returned.
- Size MB uses 1,048,576 bytes per MB and rounds to 4 decimals.

---
## Error Formats
422 Validation failed:
{
  "status": false,
  "message": "Validation failed",
  "errors": { "corpId": ["The corpId field is required."] }
}

409 Conflict (duplicate):
{
  "status": false,
  "message": "Category already exists for this employee"
}

404 Not found (update/delete):
{
  "status": false,
  "message": "Category not found"
}

---
## Testing Checklist
1. Create several categories for same employee with different fileCategory values.
2. Attempt duplicate create (should 409).
3. Update fullName only (should succeed).
4. Update fileCategory to existing one (should 409).
5. Delete a category and ensure summary excludes its fileCategory if no documents exist.
6. Run summary with/without empCode and fileCategory filters.

---
## Migration Reference
File: database/migrations/2025_11_23_000001_create_fms_categories_table.php
Unique composite index ensures no duplicate category per employee.
