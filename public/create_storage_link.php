<?php
/**
 * Manual Storage Symlink Creator
 * Run this once via browser to create the storage symlink
 * URL: https://vistora.sroy.es/public/create_storage_link.php
 * DELETE THIS FILE AFTER SUCCESSFUL EXECUTION
 */

// Define paths
$publicPath = __DIR__;
$storagePath = dirname(__DIR__) . '/storage/app/public';
$linkPath = $publicPath . '/storage';

echo "<h2>Storage Symlink Creator</h2>";
echo "<pre>";

echo "Public Path: " . $publicPath . "\n";
echo "Storage Path: " . $storagePath . "\n";
echo "Link Path: " . $linkPath . "\n\n";

// Check if storage folder exists
if (!is_dir($storagePath)) {
    echo "ERROR: Storage path does not exist: $storagePath\n";
    echo "Creating storage/app/public directory...\n";
    mkdir($storagePath, 0755, true);
    echo "Created!\n\n";
}

// Check if fms_documents folder exists
$fmsPath = $storagePath . '/fms_documents';
if (!is_dir($fmsPath)) {
    echo "Creating fms_documents directory...\n";
    mkdir($fmsPath, 0755, true);
    echo "Created!\n\n";
}

// Check if link already exists
if (file_exists($linkPath)) {
    if (is_link($linkPath)) {
        echo "Symlink already exists at: $linkPath\n";
        echo "Target: " . readlink($linkPath) . "\n";
        echo "</pre>";
        exit;
    } else {
        echo "A file/folder already exists at $linkPath (not a symlink)\n";
        echo "Please remove it manually first.\n";
        echo "</pre>";
        exit;
    }
}

// Create the symlink
$target = '../storage/app/public';
echo "Creating symlink...\n";
echo "Link: $linkPath\n";
echo "Target: $target\n\n";

// Try to create symlink
if (@symlink($target, $linkPath)) {
    echo "SUCCESS! Symlink created successfully!\n\n";
    echo "Test URL: https://vistora.sroy.es/public/storage/fms_documents/\n";
    echo "\n⚠️ IMPORTANT: Delete this file now for security!\n";
} else {
    echo "ERROR: Could not create symlink using symlink() function.\n";
    echo "The symlink() function may be disabled.\n\n";
    
    // Alternative: Try using shell command
    echo "Trying shell command alternative...\n";
    $command = "ln -s ../storage/app/public " . escapeshellarg($linkPath);
    $output = shell_exec($command . " 2>&1");
    
    if (is_link($linkPath)) {
        echo "SUCCESS! Symlink created using shell command!\n\n";
        echo "Test URL: https://vistora.sroy.es/public/storage/fms_documents/\n";
        echo "\n⚠️ IMPORTANT: Delete this file now for security!\n";
    } else {
        echo "Shell command also failed.\n";
        echo "Output: $output\n\n";
        
        echo "=== MANUAL SOLUTION ===\n";
        echo "Please run this command via SSH:\n\n";
        echo "cd /home/u473577775/domains/vistora.sroy.es/public_html/public\n";
        echo "ln -s ../storage/app/public storage\n\n";
        echo "Or ask your hosting provider to enable symlink() function.\n";
    }
}

echo "</pre>";
?>
