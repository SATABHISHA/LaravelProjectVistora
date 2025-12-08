<?php
/**
 * Manual Symlink Creator for Shared Hosting
 * 
 * This script creates a symbolic link from public/storage to storage/app/public
 * without using PHP's symlink() function (which is often disabled on shared hosting)
 * 
 * Upload this file to your Laravel root directory on the server and run it once via browser
 * Example: https://vistora.sroy.es/create_symlink.php
 */

// Define paths
$target = __DIR__ . '/storage/app/public';
$link = __DIR__ . '/public/storage';

// Check if target directory exists
if (!is_dir($target)) {
    die("Error: Target directory does not exist: $target");
}

// Check if link already exists
if (file_exists($link)) {
    if (is_link($link)) {
        echo "Symbolic link already exists at: $link<br>";
        echo "Points to: " . readlink($link) . "<br>";
        echo "<br>If downloads are not working, try deleting the existing link and re-running this script.";
    } else {
        echo "Error: A file or directory already exists at: $link<br>";
        echo "Please delete it manually and re-run this script.";
    }
    exit;
}

// Try to create symlink using symlink()
if (function_exists('symlink')) {
    if (@symlink($target, $link)) {
        echo "✅ SUCCESS: Symbolic link created successfully!<br>";
        echo "From: $link<br>";
        echo "To: $target<br>";
        echo "<br>You can now delete this file for security.";
    } else {
        echo "❌ FAILED: Could not create symbolic link using symlink() function.<br>";
        echo "This is likely due to server restrictions.<br><br>";
        echo "Alternative solution below...";
    }
} else {
    echo "⚠️ symlink() function is disabled on this server.<br><br>";
}

// Alternative: Create using system command (works on some shared hosting)
if (!is_link($link)) {
    echo "<br><br>Attempting alternative method...<br>";
    
    $command = "ln -s " . escapeshellarg($target) . " " . escapeshellarg($link);
    $output = [];
    $return_var = 0;
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && is_link($link)) {
        echo "✅ SUCCESS: Symbolic link created using system command!<br>";
        echo "From: $link<br>";
        echo "To: $target<br>";
        echo "<br>You can now delete this file for security.";
    } else {
        echo "❌ FAILED: Could not create symbolic link.<br><br>";
        echo "<strong>Manual Instructions:</strong><br>";
        echo "1. Connect to your server via SSH or cPanel File Manager<br>";
        echo "2. Navigate to the public directory<br>";
        echo "3. Create a symbolic link with this command:<br>";
        echo "<code>ln -s ../storage/app/public storage</code><br><br>";
        echo "Or contact your hosting provider to enable symlink creation.";
    }
}
?>
