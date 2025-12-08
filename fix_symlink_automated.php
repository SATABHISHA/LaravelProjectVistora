<?php
/**
 * Automated Symlink Fix Script for Laravel Storage
 * 
 * This script will:
 * 1. Detect the correct Laravel directory structure
 * 2. Remove any incorrectly placed symlinks
 * 3. Create the symlink in the correct public directory
 * 4. Verify the symlink was created successfully
 * 5. Test file accessibility
 */

echo "=== Laravel Storage Symlink Fix Script ===\n\n";

// Step 1: Detect Laravel root directory
echo "Step 1: Detecting Laravel root directory...\n";
$currentDir = getcwd();
echo "Current directory: $currentDir\n";

// Check if we're in Laravel root by looking for artisan file
$laravelRoot = $currentDir;
if (!file_exists($laravelRoot . '/artisan')) {
    // Try to find Laravel root
    $possiblePaths = [
        dirname(__FILE__),
        '/home/u473577775/domains/vistora.sroy.es',
        '/home/u473577775/public_html',
    ];
    
    $found = false;
    foreach ($possiblePaths as $path) {
        if (file_exists($path . '/artisan')) {
            $laravelRoot = $path;
            $found = true;
            echo "✓ Laravel root found at: $laravelRoot\n";
            break;
        }
    }
    
    if (!$found) {
        die("✗ ERROR: Could not find Laravel root directory. Please run this script from your Laravel installation.\n");
    }
} else {
    echo "✓ Already in Laravel root directory\n";
}

chdir($laravelRoot);
echo "\n";

// Step 2: Identify public directory
echo "Step 2: Identifying public directory...\n";
$publicDirNames = ['public_html', 'public', 'httpdocs', 'www'];
$publicDir = null;

foreach ($publicDirNames as $dirName) {
    $testPath = $laravelRoot . '/' . $dirName;
    if (is_dir($testPath)) {
        $publicDir = $testPath;
        echo "✓ Public directory found: $publicDir\n";
        break;
    }
}

if (!$publicDir) {
    die("✗ ERROR: Could not find public directory. Tried: " . implode(', ', $publicDirNames) . "\n");
}
echo "\n";

// Step 3: Check storage directory
echo "Step 3: Verifying storage directory...\n";
$storageDir = $laravelRoot . '/storage/app/public';
if (!is_dir($storageDir)) {
    die("✗ ERROR: Storage directory not found at: $storageDir\n");
}
echo "✓ Storage directory exists: $storageDir\n";

// Check if there are files in storage
$files = glob($storageDir . '/fms_documents/*');
if (count($files) > 0) {
    echo "✓ Found " . count($files) . " files in fms_documents\n";
} else {
    echo "⚠ Warning: No files found in fms_documents directory\n";
}
echo "\n";

// Step 4: Remove incorrect symlinks
echo "Step 4: Cleaning up incorrect symlinks...\n";
$incorrectSymlinks = [
    $laravelRoot . '/storage/public',
    $publicDir . '/storage',
];

foreach ($incorrectSymlinks as $symlinkPath) {
    if (is_link($symlinkPath)) {
        echo "Found existing symlink at: $symlinkPath\n";
        if (unlink($symlinkPath)) {
            echo "✓ Removed symlink: $symlinkPath\n";
        } else {
            echo "⚠ Warning: Could not remove symlink: $symlinkPath\n";
        }
    } elseif (is_dir($symlinkPath) && !is_link($symlinkPath)) {
        echo "⚠ Warning: Found directory (not symlink) at: $symlinkPath - leaving it as is\n";
    }
}
echo "\n";

// Step 5: Create the correct symlink
echo "Step 5: Creating symlink in public directory...\n";
$symlinkTarget = $publicDir . '/storage';
$storageTarget = '../storage/app/public';

// Calculate relative path from public directory to storage
$publicDirBasename = basename($publicDir);
if ($publicDirBasename !== 'public') {
    // If using public_html or other name, adjust the relative path
    $storageTarget = '../storage/app/public';
}

echo "Symlink location: $symlinkTarget\n";
echo "Target path: $storageTarget\n";

// Change to public directory to create relative symlink
chdir($publicDir);

// Try to create symlink using exec (ln -s command)
$command = "ln -s $storageTarget storage 2>&1";
$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo "✓ Symlink created successfully using ln command\n";
} else {
    // Fallback to PHP symlink() function
    echo "⚠ ln command failed, trying PHP symlink() function...\n";
    
    if (symlink($storageTarget, 'storage')) {
        echo "✓ Symlink created successfully using PHP symlink()\n";
    } else {
        echo "✗ ERROR: Failed to create symlink. Output: " . implode("\n", $output) . "\n";
        echo "Please create the symlink manually using SSH:\n";
        echo "cd $publicDir\n";
        echo "ln -s $storageTarget storage\n";
        exit(1);
    }
}
echo "\n";

// Step 6: Verify symlink
echo "Step 6: Verifying symlink...\n";
chdir($publicDir);
if (is_link('storage')) {
    $linkTarget = readlink('storage');
    echo "✓ Symlink exists\n";
    echo "  Links to: $linkTarget\n";
    
    // Verify target directory is accessible
    if (is_dir('storage')) {
        echo "✓ Target directory is accessible\n";
    } else {
        echo "✗ ERROR: Symlink exists but target is not accessible\n";
    }
} else {
    echo "✗ ERROR: Symlink was not created\n";
}
echo "\n";

// Step 7: Test file accessibility
echo "Step 7: Testing file accessibility...\n";
$testFilePath = $publicDir . '/storage/fms_documents';
if (is_dir($testFilePath)) {
    echo "✓ FMS documents directory is accessible via symlink\n";
    
    $testFiles = glob($testFilePath . '/*');
    if (count($testFiles) > 0) {
        echo "✓ Found " . count($testFiles) . " files accessible via symlink\n";
        
        // Show first file as example
        $firstFile = basename($testFiles[0]);
        echo "\nExample file URL:\n";
        echo "https://vistora.sroy.es/storage/fms_documents/$firstFile\n";
    } else {
        echo "⚠ No files found (upload a file to test)\n";
    }
} else {
    echo "✗ ERROR: Cannot access files through symlink\n";
}
echo "\n";

// Step 8: Final summary
echo "=== Summary ===\n";
echo "Laravel Root: $laravelRoot\n";
echo "Public Directory: $publicDir\n";
echo "Storage Location: $storageDir\n";
echo "Symlink Created: $publicDir/storage -> $storageTarget\n";
echo "\n";

// Step 9: Additional checks
echo "Step 9: Additional verification...\n";
exec('ls -la ' . escapeshellarg($publicDir . '/storage'), $lsOutput);
if (!empty($lsOutput)) {
    echo "Symlink details:\n";
    echo implode("\n", $lsOutput) . "\n";
}
echo "\n";

echo "✓ Script completed successfully!\n";
echo "\nNext steps:\n";
echo "1. Test a download URL in your browser\n";
echo "2. If it works, delete this script for security\n";
echo "3. Clear Laravel cache: php artisan config:clear\n";
?>
