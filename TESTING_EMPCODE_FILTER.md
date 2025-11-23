# Manual Testing Guide for empCode Filter

## Prerequisites
1. Start Laravel server: `php artisan serve`
2. Ensure database has test data

## Option 1: Insert Test Data via Tinker (Recommended)

Open a **new terminal** and run:

```bash
php artisan tinker
```

Then paste these commands:

```php
// Add storage quota
DB::table('company_storage')->insert([
    'corpId' => 'CORP001',
    'size' => 500,
    'sizeUit' => 'MB',
    'created_at' => now(),
    'updated_at' => now()
]);

// Add 3 test files: 2 for EMP001, 1 for EMP002
DB::table('fms_employee_documents')->insert([
    [
        'corpId' => 'CORP001',
        'companyName' => 'TestCo',
        'empCode' => 'EMP001',
        'fileCategory' => 'Resume',
        'filename' => 'resume_emp001_v1.pdf',
        'file' => 'fms_documents/test1.pdf',
        'file_size' => 50000,
        'created_at' => now(),
        'updated_at' => now()
    ],
    [
        'corpId' => 'CORP001',
        'companyName' => 'TestCo',
        'empCode' => 'EMP002',
        'fileCategory' => 'Resume',
        'filename' => 'resume_emp002.pdf',
        'file' => 'fms_documents/test2.pdf',
        'file_size' => 60000,
        'created_at' => now(),
        'updated_at' => now()
    ],
    [
        'corpId' => 'CORP001',
        'companyName' => 'TestCo',
        'empCode' => 'EMP001',
        'fileCategory' => 'Resume',
        'filename' => 'resume_emp001_v2.pdf',
        'file' => 'fms_documents/test3.pdf',
        'file_size' => 55000,
        'created_at' => now(),
        'updated_at' => now()
    ]
]);

echo "Test data inserted!\n";
exit;
```

## Option 2: Test API Calls

Open a **new PowerShell terminal** (don't close the server):

### Test 1: Get ALL Resume files (no empCode filter)
```powershell
curl.exe -s "http://127.0.0.1:8000/api/fms/files-by-category?corpId=CORP001&companyName=TestCo&fileCategory=Resume"
```

**Expected**: Returns 3 files (EMP001 x2, EMP002 x1)

### Test 2: Get Resume files for EMP001 only
```powershell
curl.exe -s "http://127.0.0.1:8000/api/fms/files-by-category?corpId=CORP001&companyName=TestCo&fileCategory=Resume&empCode=EMP001"
```

**Expected**: Returns 2 files (both for EMP001)

### Test 3: Get Resume files for EMP002 only
```powershell
curl.exe -s "http://127.0.0.1:8000/api/fms/files-by-category?corpId=CORP001&companyName=TestCo&fileCategory=Resume&empCode=EMP002"
```

**Expected**: Returns 1 file (for EMP002)

### Test 4: Get files for non-existent employee
```powershell
curl.exe -s "http://127.0.0.1:8000/api/fms/files-by-category?corpId=CORP001&companyName=TestCo&fileCategory=Resume&empCode=EMP999"
```

**Expected**: Returns 0 files (empty array)

## Clean Up Test Data

When done, run in tinker:

```php
DB::table('fms_employee_documents')->where('corpId', 'CORP001')->delete();
DB::table('company_storage')->where('corpId', 'CORP001')->delete();
```

## Summary

✅ **Feature Added**: Optional `empCode` parameter to `/api/fms/files-by-category`
✅ **Backward Compatible**: Works without empCode (returns all files in category)
✅ **Documentation Updated**: FMS_API_REFERENCE.md includes empCode examples
✅ **Validation**: empCode is nullable (optional), max 20 characters
