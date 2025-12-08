# 🚀 QUICK DEPLOYMENT GUIDE - Symlink Fix

## ✅ Easiest Method - Web Browser (Recommended)

### Steps:
1. **Pull latest code on your server:**
   ```bash
   cd /home/u473577775/domains/vistora.sroy.es
   git pull origin main
   ```

2. **Access the web runner in your browser:**
   ```
   https://vistora.sroy.es/run_symlink_fix.php
   ```

3. **Click "Run Fix Now" button**

4. **After success, DELETE the files for security:**
   ```bash
   rm run_symlink_fix.php fix_symlink_automated.php
   ```

5. **Test a download URL** (example shown in output)

---

## 📋 Alternative Method - SSH Command Line

### Steps:
1. **SSH into your server:**
   ```bash
   ssh -p 65002 u473577775@82.25.106.143
   ```

2. **Navigate to Laravel root:**
   ```bash
   cd /home/u473577775/domains/vistora.sroy.es
   ```

3. **Pull latest code:**
   ```bash
   git pull origin main
   ```

4. **Run the automated script:**
   ```bash
   php fix_symlink_automated.php
   ```

5. **Clean up:**
   ```bash
   rm fix_symlink_automated.php run_symlink_fix.php
   ```

---

## 🧪 What Happens During Fix

```
✓ Detects Laravel root directory
✓ Finds public directory (public_html/public)
✓ Verifies storage/app/public exists
✓ Removes incorrect symlink from /storage/public
✓ Creates correct symlink: public/storage → ../storage/app/public
✓ Verifies symlink works
✓ Tests file accessibility
✓ Shows example download URL
```

---

## 📊 Expected Result

### Before Fix:
```
❌ URL: https://vistora.sroy.es/public/storage/fms_documents/file.pdf
❌ Status: 404 Not Found
```

### After Fix:
```
✅ URL: https://vistora.sroy.es/storage/fms_documents/file.pdf
✅ Status: 200 OK (File downloads successfully)
```

---

## 🔍 Verification Steps

1. **Check symlink exists:**
   ```bash
   ls -la /home/u473577775/domains/vistora.sroy.es/public_html/storage
   ```
   
   **Expected output:**
   ```
   lrwxrwxrwx 1 u473577775 o1006914196 21 Dec  8 03:30 storage -> ../storage/app/public
   ```

2. **Test file accessibility:**
   ```bash
   ls /home/u473577775/domains/vistora.sroy.es/public_html/storage/fms_documents/
   ```
   
   **Expected:** List of uploaded files

3. **Test in browser:**
   - Open: `https://vistora.sroy.es/storage/fms_documents/[filename].pdf`
   - **Expected:** File downloads or displays

---

## 🛠 Troubleshooting

### Issue: "Could not find Laravel root"
**Solution:** Manually specify path in script or run from correct directory

### Issue: "Could not find public directory"
**Solution:** Check if your directory is named `public_html`, `public`, or `httpdocs`

### Issue: "Failed to create symlink"
**Manual fix:**
```bash
cd /home/u473577775/domains/vistora.sroy.es/public_html
ln -s ../storage/app/public storage
```

### Issue: Still getting 404 errors
**Check:**
1. .htaccess file in public directory
2. APP_URL in .env (should be `https://vistora.sroy.es`)
3. Clear cache: `php artisan config:clear`

---

## 🔒 Security Checklist

- [ ] Delete `run_symlink_fix.php` after use
- [ ] Delete `fix_symlink_automated.php` after use
- [ ] Delete `AUTOMATED_FIX_INSTRUCTIONS.md` (optional)
- [ ] Delete this file `DEPLOYMENT_GUIDE.md` (optional)
- [ ] Clear Laravel cache
- [ ] Test download URLs work

---

## 📞 Support

If you encounter issues:

1. **Check server logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Check web server error log:**
   ```bash
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```

3. **Verify permissions:**
   ```bash
   ls -la storage/app/public
   ls -la public_html/storage
   ```

---

## ✨ Final Notes

- The symlink approach is the **correct Laravel way**
- Files remain in `/storage/app/public/`
- Public access via `/public/storage/` (symlink)
- URL generation uses `config('app.url') . '/storage/' . $file->file`
- No `/public/` prefix in URLs on production

**After successful fix, your FMS file downloads will work perfectly! 🎉**
