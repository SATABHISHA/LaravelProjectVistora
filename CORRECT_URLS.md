# 🌐 Correct URLs for Arrears API Testing

## ✅ Laravel Development Server is Running!

**Server URL:** `http://127.0.0.1:8000`

---

## 📥 Working Download URLs

### For Nippo Company:
```
http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=test&companyName=Nippo&year=2025&month=November
```

### For IMS MACO Company:
```
http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=maco&companyName=IMS+MACO+SERVICES+INDIA+PVT.+LTD.&year=2025&month=November
```

---

## 🌐 Test Pages URLs

### Nippo Test Page:
**OPTION 1 (Recommended):** Use PowerShell to test directly:
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=test&companyName=Nippo&year=2025&month=November" -OutFile "Nippo_Arrears_Nov2025.xlsx"
```

### IMS MACO Test Page:
**OPTION 1 (Recommended):** Use PowerShell to test directly:
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=maco&companyName=IMS+MACO+SERVICES+INDIA+PVT.+LTD.&year=2025&month=November" -OutFile "IMS_MACO_Arrears_Nov2025.xlsx"
```

**OPTION 2:** Open in browser:
```
http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=maco&companyName=IMS+MACO+SERVICES+INDIA+PVT.+LTD.&year=2025&month=November
```

---

## 🚀 Quick Test Commands

### Test 1: Download Nippo Arrears (1 employee, 3 months)
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=test&companyName=Nippo&year=2025&month=November" -OutFile "$env:USERPROFILE\Downloads\Nippo_Arrears_Nov2025.xlsx"
```

### Test 2: Download IMS MACO Arrears (26 employees, various months)
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=maco&companyName=IMS+MACO+SERVICES+INDIA+PVT.+LTD.&year=2025&month=November" -OutFile "$env:USERPROFILE\Downloads\IMS_MACO_Arrears_Nov2025.xlsx"
```

Files will be saved to your **Downloads** folder!

---

## 📝 Important Notes

1. **Laravel Development Server Must Be Running**
   - The server is currently running on `http://127.0.0.1:8000`
   - Keep the terminal open while testing
   - Press Ctrl+C to stop the server when done

2. **Alternative: Use Apache (if configured)**
   - If you have Apache/XAMPP configured for this project, use:
   ```
   http://localhost/LaravelProjectVistora/public/api/payroll/export-with-arrears?...
   ```

3. **Browser Testing**
   - Simply paste the URL in your browser
   - The Excel file will download automatically
   - No need for test HTML pages with direct API access

---

## ✅ Verification

After running the PowerShell commands above, check your Downloads folder:
```powershell
Get-ChildItem "$env:USERPROFILE\Downloads\*Arrears*.xlsx" | Select-Object Name, Length, LastWriteTime
```

---

## 🔧 Troubleshooting

### If download fails:
1. Ensure Laravel server is running (`php artisan serve`)
2. Check if port 8000 is available
3. Try using `localhost` instead of `127.0.0.1`
4. Verify database connection in `.env` file

### Check API Response:
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/payroll/export-with-arrears?corpId=test&companyName=Nippo&year=2025&month=November" | Select-Object StatusCode, ContentType
```

Expected: `StatusCode: 200, ContentType: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
