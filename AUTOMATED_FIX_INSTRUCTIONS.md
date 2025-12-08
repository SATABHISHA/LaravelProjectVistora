# Automated Symlink Fix Instructions

## Quick Fix - 3 Simple Steps

### Step 1: Upload the Script
Upload `fix_symlink_automated.php` to your server root directory using FTP/SFTP or cPanel File Manager.

**Recommended location:** `/home/u473577775/domains/vistora.sroy.es/fix_symlink_automated.php`

### Step 2: Run the Script via SSH

```bash
# Connect to your server
ssh -p 65002 u473577775@82.25.106.143

# Navigate to Laravel root
cd /home/u473577775/domains/vistora.sroy.es

# Run the automated fix script
php fix_symlink_automated.php
```

### Step 3: Verify & Test

The script will automatically:
- ✓ Detect your Laravel directory structure
- ✓ Remove incorrectly placed symlinks
- ✓ Create the symlink in the correct public directory
- ✓ Verify the symlink works
- ✓ Test file accessibility
- ✓ Show example download URL

## Alternative: Run via Browser

If you prefer, you can also run the script via browser (less secure, delete after use):

1. Upload `fix_symlink_automated.php` to your public directory
2. Visit: `https://vistora.sroy.es/fix_symlink_automated.php`
3. **IMPORTANT:** Delete the file immediately after running it

## What the Script Does

```
1. Finds Laravel root directory (checks for artisan file)
2. Locates public directory (public_html, public, httpdocs, www)
3. Verifies storage/app/public exists and has files
4. Removes any incorrect symlinks in wrong locations
5. Creates correct symlink: public/storage -> ../storage/app/public
6. Verifies symlink is working
7. Tests file accessibility through symlink
8. Shows example download URL for testing
```

## Expected Output

```
=== Laravel Storage Symlink Fix Script ===

Step 1: Detecting Laravel root directory...
✓ Laravel root found at: /home/u473577775/domains/vistora.sroy.es

Step 2: Identifying public directory...
✓ Public directory found: /home/u473577775/domains/vistora.sroy.es/public_html

Step 3: Verifying storage directory...
✓ Storage directory exists: /home/u473577775/domains/vistora.sroy.es/storage/app/public
✓ Found X files in fms_documents

Step 4: Cleaning up incorrect symlinks...
✓ Removed symlink: /home/u473577775/domains/vistora.sroy.es/storage/public

Step 5: Creating symlink in public directory...
✓ Symlink created successfully

Step 6: Verifying symlink...
✓ Symlink exists
✓ Target directory is accessible

Step 7: Testing file accessibility...
✓ FMS documents directory is accessible via symlink
✓ Found X files accessible via symlink

Example file URL:
https://vistora.sroy.es/storage/fms_documents/filename.pdf

✓ Script completed successfully!
```

## After Success

1. **Test the download URL** shown in the output
2. **Delete the script** for security:
   ```bash
   rm fix_symlink_automated.php
   ```
3. **Clear Laravel cache**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

## Troubleshooting

### If the script fails:

**Error: "Could not find Laravel root directory"**
- Manually edit line 18 in the script to add your exact path
- Or run the script from your Laravel root directory

**Error: "Could not find public directory"**
- Check what your public directory is named (public_html, public, httpdocs)
- Add it to the `$publicDirNames` array in the script (line 44)

**Error: "Failed to create symlink"**
- Shared hosting may block symlink functions
- Use the manual SSH commands shown in the script output
- Or contact your hosting provider to enable symlink permissions

### Manual Fallback

If the automated script doesn't work, run these commands via SSH:

```bash
cd /home/u473577775/domains/vistora.sroy.es
rm storage/public  # Remove incorrect symlink
cd public_html     # or cd public (whatever your public directory is named)
ln -s ../storage/app/public storage
ls -la storage     # Verify it was created
```

## Security Note

This script is safe to run but should be **deleted after use** to prevent unauthorized execution.
