# Symlink Setup Instructions for Live Server

## Problem
The `php artisan storage:link` command fails on shared hosting because the `symlink()` PHP function is disabled.

## Solution

### Option 1: Using the Provided Script (Recommended)

1. Upload the `create_symlink.php` file to your Laravel root directory on the live server
2. Open your browser and navigate to: `https://vistora.sroy.es/create_symlink.php`
3. The script will attempt to create the symlink automatically
4. If successful, delete the `create_symlink.php` file for security
5. Test your download URLs - they should now work!

### Option 2: Manual Symlink Creation via SSH

If you have SSH access:

```bash
cd /path/to/your/laravel/public
ln -s ../storage/app/public storage
```

### Option 3: Manual Symlink via cPanel File Manager

1. Login to cPanel
2. Open File Manager
3. Navigate to `public_html` (or your Laravel public directory)
4. Look for the "Create Symbolic Link" option
5. Create a link named `storage` that points to `../storage/app/public`

### Option 4: Contact Hosting Provider

If none of the above work, contact your hosting provider and request:
- Enable `symlink()` PHP function, OR
- Manually create a symbolic link from `public/storage` to `storage/app/public`

## Verification

After creating the symlink, verify it works:

1. Check if `public/storage` exists and is a symbolic link
2. Try accessing a file through the FMS API
3. The download URL should look like: `https://vistora.sroy.es/storage/fms_documents/yourfile.pdf`

## Current File Upload Status

✅ Files are uploading correctly to `storage/app/public/fms_documents/`
✅ FMS API is generating correct download URLs using `asset('storage/...')`
❌ Symlink missing - causing 404 errors on download URLs

Once the symlink is created, all downloads will work automatically!
