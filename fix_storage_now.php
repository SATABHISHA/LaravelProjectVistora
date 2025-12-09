<?php
/**
 * Direct Storage Symlink Fix for Hostinger
 * 
 * Server structure detected:
 * - public_html IS the web root AND Laravel root
 * - Files stored in: public_html/storage/app/public/fms_documents/
 * - Symlink needed: public_html/storage (points to storage/app/public)
 * 
 * DELETE THIS FILE AFTER USE!
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Storage Symlink Fix for Hostinger ===\n\n";

$publicHtml = '/home/u473577775/domains/vistora.sroy.es/public_html';
$storageAppPublic = $publicHtml . '/storage/app/public';
$symlinkLocation = $publicHtml . '/storage';

echo "Public HTML: $publicHtml\n";
echo "Storage App Public: $storageAppPublic\n";
echo "Symlink Location: $symlinkLocation\n\n";

// Step 1: Check if storage/app/public exists
echo "Step 1: Checking storage/app/public exists...\n";
if (is_dir($storageAppPublic)) {
    echo "✓ Storage directory exists\n";
    $files = glob($storageAppPublic . '/fms_documents/*');
    echo "✓ Found " . count($files) . " files in fms_documents\n\n";
} else {
    die("✗ ERROR: Storage directory not found at: $storageAppPublic\n");
}

// Step 2: Check current state of /storage
echo "Step 2: Checking current /storage state...\n";
if (is_link($symlinkLocation)) {
    $target = readlink($symlinkLocation);
    echo "Current symlink points to: $target\n";
    echo "Removing existing symlink...\n";
    unlink($symlinkLocation);
    echo "✓ Removed existing symlink\n\n";
} elseif (is_dir($symlinkLocation)) {
    echo "'/storage' exists as a DIRECTORY (not symlink)\n";
    echo "This is the actual storage folder - we need a DIFFERENT approach!\n\n";
    
    // For this hosting setup, files are stored in storage/app/public
    // But URL expects /storage/fms_documents/file.pdf
    // So we need: storage symlink -> storage/app/public (but storage exists as folder!)
    
    // Alternative: Create symlink INSIDE public_html that points to storage/app/public
    // But we can't replace the actual storage directory
    
    echo "=== ALTERNATIVE APPROACH ===\n";
    echo "Since /storage is the actual Laravel storage folder, we need to:\n";
    echo "1. Either expose storage/app/public directly via web server config\n";
    echo "2. Or create an alias/route to serve files\n\n";
    
    // Let's check if there's a /public subfolder we should use
    $publicSubfolder = $publicHtml . '/public';
    if (is_dir($publicSubfolder)) {
        echo "Found /public subfolder at: $publicSubfolder\n";
        $symlinkInPublic = $publicSubfolder . '/storage';
        
        if (is_link($symlinkInPublic)) {
            echo "Symlink exists at $symlinkInPublic -> " . readlink($symlinkInPublic) . "\n";
        } elseif (!file_exists($symlinkInPublic)) {
            echo "Creating symlink in /public folder...\n";
            chdir($publicSubfolder);
            $result = @symlink('../storage/app/public', 'storage');
            if ($result) {
                echo "✓ Created symlink at $symlinkInPublic\n";
            } else {
                // Try shell command
                $cmd = 'ln -s ../storage/app/public storage 2>&1';
                $output = shell_exec($cmd);
                if (is_link($symlinkInPublic)) {
                    echo "✓ Created symlink using shell command\n";
                } else {
                    echo "✗ Failed to create symlink: $output\n";
                }
            }
        }
    }
    
    echo "\n=== RECOMMENDED FIX ===\n";
    echo "Since public_html IS both Laravel root AND web root,\n";
    echo "and /storage is the actual storage folder,\n";
    echo "we need to create a DIFFERENT symlink name.\n\n";
    
    // Create symlink called 'files' that points to storage/app/public
    $filesSymlink = $publicHtml . '/files';
    echo "Creating /files symlink -> storage/app/public\n";
    
    if (is_link($filesSymlink)) {
        unlink($filesSymlink);
    }
    
    if (!file_exists($filesSymlink)) {
        chdir($publicHtml);
        $result = @symlink('storage/app/public', 'files');
        if ($result) {
            echo "✓ Created /files symlink successfully!\n";
            echo "\nNEW Download URL format:\n";
            echo "https://vistora.sroy.es/files/fms_documents/FILENAME.pdf\n\n";
            echo "⚠️ You need to update FmsController.php to use '/files/' instead of '/storage/'\n";
        } else {
            $cmd = 'ln -s storage/app/public files 2>&1';
            $output = shell_exec($cmd);
            if (is_link($filesSymlink)) {
                echo "✓ Created /files symlink using shell command!\n";
                echo "\nNEW Download URL format:\n";
                echo "https://vistora.sroy.es/files/fms_documents/FILENAME.pdf\n\n";
            } else {
                echo "✗ Failed: $output\n";
            }
        }
    }
    
    // Also try .htaccess rewrite as alternative
    echo "\n=== ALTERNATIVE: .htaccess Rewrite ===\n";
    $htaccessPath = $publicHtml . '/.htaccess';
    $rewriteRule = "\n# Serve storage files\nRewriteRule ^storage/(.*)$ /storage/app/public/$1 [L]\n";
    
    if (file_exists($htaccessPath)) {
        $htaccessContent = file_get_contents($htaccessPath);
        if (strpos($htaccessContent, 'storage/app/public') === false) {
            echo "Adding rewrite rule to .htaccess...\n";
            // Don't auto-modify, just show what to add
            echo "Add this to your .htaccess:\n";
            echo $rewriteRule;
        } else {
            echo "Rewrite rule already exists in .htaccess\n";
        }
    }
    
    exit;
}

// If we get here, /storage doesn't exist or was removed
echo "Step 3: Creating symlink...\n";
chdir($publicHtml);

// Try PHP symlink
$result = @symlink('storage/app/public', 'storage');
if ($result && is_link($symlinkLocation)) {
    echo "✓ Symlink created successfully using PHP!\n";
} else {
    // Try shell command
    $cmd = 'ln -s storage/app/public storage 2>&1';
    $output = shell_exec($cmd);
    
    if (is_link($symlinkLocation)) {
        echo "✓ Symlink created successfully using shell!\n";
    } else {
        echo "✗ Failed to create symlink\n";
        echo "Shell output: $output\n";
        echo "\nManual fix required via SSH:\n";
        echo "cd $publicHtml\n";
        echo "ln -s storage/app/public storage\n";
    }
}

// Verify
echo "\nStep 4: Verifying...\n";
if (is_link($symlinkLocation)) {
    echo "✓ Symlink exists at: $symlinkLocation\n";
    echo "✓ Points to: " . readlink($symlinkLocation) . "\n";
    
    $testPath = $symlinkLocation . '/fms_documents';
    if (is_dir($testPath)) {
        $files = glob($testPath . '/*');
        echo "✓ FMS documents accessible: " . count($files) . " files\n";
        
        if (count($files) > 0) {
            echo "\nTest URL:\n";
            echo "https://vistora.sroy.es/storage/fms_documents/" . basename($files[0]) . "\n";
        }
    }
} else {
    echo "✗ Symlink verification failed\n";
}

echo "\n=== DONE ===\n";
echo "DELETE THIS FILE: rm fix_storage_now.php\n";
