<?php
/**
 * Storage Symlink Diagnostic and Fix Script
 * Run this on your live server to diagnose and fix storage symlink issues
 * 
 * Access via: https://vistora.sroy.es/diagnose_and_fix_storage.php
 * 
 * DELETE THIS FILE AFTER USE FOR SECURITY!
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Storage Symlink Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; max-width: 1000px; margin: 0 auto; background: #1a1a2e; color: #eee; }
        h1, h2 { color: #00d4ff; }
        .success { color: #00ff88; }
        .error { color: #ff4444; }
        .warning { color: #ffaa00; }
        .info { color: #00aaff; }
        pre { background: #16213e; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .box { background: #16213e; padding: 20px; border-radius: 10px; margin: 20px 0; }
        button { background: #00d4ff; color: #000; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        button:hover { background: #00ff88; }
        .danger-btn { background: #ff4444; }
        .danger-btn:hover { background: #ff6666; }
    </style>
</head>
<body>
<h1>🔧 Storage Symlink Diagnostic & Fix</h1>

<?php
// Get action
$action = $_GET['action'] ?? 'diagnose';

echo "<div class='box'>";
echo "<h2>📊 System Information</h2>";
echo "<pre>";
echo "Current Working Directory: " . getcwd() . "\n";
echo "Script Location: " . __DIR__ . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "</pre>";
echo "</div>";

// Try to find Laravel root
$scriptDir = __DIR__;
$laravelRoot = null;

// Check if artisan exists in current directory or parent
$possibleRoots = [
    $scriptDir,
    dirname($scriptDir),
    '/home/u473577775/domains/vistora.sroy.es',
];

foreach ($possibleRoots as $path) {
    if (file_exists($path . '/artisan')) {
        $laravelRoot = $path;
        break;
    }
}

echo "<div class='box'>";
echo "<h2>📁 Directory Structure</h2>";
echo "<pre>";

if ($laravelRoot) {
    echo "<span class='success'>✓ Laravel Root Found: $laravelRoot</span>\n\n";
    
    // Find public directory
    $publicDirs = ['public_html', 'public', 'httpdocs', 'www'];
    $publicDir = null;
    
    foreach ($publicDirs as $dir) {
        $testPath = $laravelRoot . '/' . $dir;
        if (is_dir($testPath)) {
            $publicDir = $testPath;
            echo "<span class='success'>✓ Public Directory: $testPath</span>\n";
            break;
        }
    }
    
    if (!$publicDir) {
        // Check if script is in public directory
        if (strpos($scriptDir, 'public') !== false || strpos($scriptDir, 'public_html') !== false) {
            $publicDir = $scriptDir;
            echo "<span class='success'>✓ Public Directory (script location): $publicDir</span>\n";
        } else {
            echo "<span class='error'>✗ Public directory not found</span>\n";
        }
    }
    
    // Check storage directories
    $storagePath = $laravelRoot . '/storage/app/public';
    if (is_dir($storagePath)) {
        echo "<span class='success'>✓ Storage Path Exists: $storagePath</span>\n";
        
        // Check for fms_documents
        $fmsPath = $storagePath . '/fms_documents';
        if (is_dir($fmsPath)) {
            $files = glob($fmsPath . '/*');
            echo "<span class='success'>✓ FMS Documents Directory: " . count($files) . " files found</span>\n";
            
            if (count($files) > 0) {
                echo "\n   Sample files:\n";
                foreach (array_slice($files, 0, 5) as $file) {
                    echo "   - " . basename($file) . "\n";
                }
            }
        } else {
            echo "<span class='warning'>⚠ FMS Documents directory not found at: $fmsPath</span>\n";
        }
    } else {
        echo "<span class='error'>✗ Storage Path Missing: $storagePath</span>\n";
    }
    
    // Check symlink status
    echo "\n<strong>Symlink Status:</strong>\n";
    
    if ($publicDir) {
        $symlinkPath = $publicDir . '/storage';
        
        if (is_link($symlinkPath)) {
            $target = readlink($symlinkPath);
            echo "<span class='info'>→ Symlink exists at: $symlinkPath</span>\n";
            echo "<span class='info'>→ Points to: $target</span>\n";
            
            // Check if target is accessible
            if (is_dir($symlinkPath)) {
                echo "<span class='success'>✓ Symlink target is accessible</span>\n";
                
                // Check if fms_documents is accessible through symlink
                $symlinkedFms = $symlinkPath . '/fms_documents';
                if (is_dir($symlinkedFms)) {
                    echo "<span class='success'>✓ fms_documents accessible via symlink</span>\n";
                } else {
                    echo "<span class='error'>✗ fms_documents NOT accessible via symlink</span>\n";
                }
            } else {
                echo "<span class='error'>✗ Symlink target is NOT accessible (broken link)</span>\n";
            }
        } elseif (is_dir($symlinkPath)) {
            echo "<span class='warning'>⚠ '$symlinkPath' exists as a regular directory (not a symlink)</span>\n";
        } else {
            echo "<span class='error'>✗ No symlink exists at: $symlinkPath</span>\n";
        }
    }
    
    // Check for incorrectly placed symlinks
    $wrongSymlink = $laravelRoot . '/storage/public';
    if (is_link($wrongSymlink)) {
        echo "<span class='warning'>⚠ Incorrect symlink found at: $wrongSymlink (should be removed)</span>\n";
    }
    
} else {
    echo "<span class='error'>✗ Laravel root not found!</span>\n";
    echo "Tried paths:\n";
    foreach ($possibleRoots as $path) {
        echo "  - $path\n";
    }
}

echo "</pre>";
echo "</div>";

// Action buttons
echo "<div class='box'>";
echo "<h2>🛠️ Actions</h2>";

if ($action === 'diagnose') {
    echo "<form method='get'>";
    echo "<button type='submit' name='action' value='fix'>🔧 Fix Symlink Now</button>";
    echo "<button type='submit' name='action' value='test' class='danger-btn'>🧪 Test File Access</button>";
    echo "</form>";
}

// Fix action
if ($action === 'fix' && $laravelRoot && $publicDir) {
    echo "<h3>Attempting to fix symlink...</h3>";
    echo "<pre>";
    
    $symlinkPath = $publicDir . '/storage';
    $targetPath = '../storage/app/public';
    $absoluteTarget = $laravelRoot . '/storage/app/public';
    
    // Step 1: Remove incorrect symlinks
    $wrongSymlink = $laravelRoot . '/storage/public';
    if (is_link($wrongSymlink)) {
        if (@unlink($wrongSymlink)) {
            echo "<span class='success'>✓ Removed incorrect symlink: $wrongSymlink</span>\n";
        } else {
            echo "<span class='warning'>⚠ Could not remove: $wrongSymlink</span>\n";
        }
    }
    
    // Step 2: Handle existing storage in public
    if (is_link($symlinkPath)) {
        if (@unlink($symlinkPath)) {
            echo "<span class='success'>✓ Removed existing symlink: $symlinkPath</span>\n";
        } else {
            echo "<span class='error'>✗ Could not remove existing symlink</span>\n";
        }
    } elseif (is_dir($symlinkPath)) {
        echo "<span class='warning'>⚠ $symlinkPath is a directory, not removing automatically</span>\n";
    }
    
    // Step 3: Create symlink
    if (!file_exists($symlinkPath)) {
        // Change to public directory
        $originalDir = getcwd();
        chdir($publicDir);
        
        // Try ln -s command first
        $cmd = "ln -s $targetPath storage 2>&1";
        $output = shell_exec($cmd);
        
        if (is_link($symlinkPath)) {
            echo "<span class='success'>✓ Symlink created successfully using shell command</span>\n";
        } else {
            // Try PHP symlink
            if (@symlink($targetPath, 'storage')) {
                echo "<span class='success'>✓ Symlink created successfully using PHP</span>\n";
            } elseif (@symlink($absoluteTarget, 'storage')) {
                echo "<span class='success'>✓ Symlink created successfully using absolute path</span>\n";
            } else {
                echo "<span class='error'>✗ Failed to create symlink</span>\n";
                echo "Shell output: $output\n";
                echo "\n<span class='warning'>Manual fix required:</span>\n";
                echo "Run these commands via SSH:\n";
                echo "  cd $publicDir\n";
                echo "  ln -s $targetPath storage\n";
            }
        }
        
        chdir($originalDir);
    } else {
        echo "<span class='warning'>⚠ Storage path already exists, cannot create symlink</span>\n";
    }
    
    // Verify
    echo "\n<strong>Verification:</strong>\n";
    if (is_link($symlinkPath)) {
        echo "<span class='success'>✓ Symlink exists</span>\n";
        echo "Points to: " . readlink($symlinkPath) . "\n";
        
        if (is_dir($symlinkPath . '/fms_documents')) {
            $files = glob($symlinkPath . '/fms_documents/*');
            echo "<span class='success'>✓ FMS documents accessible: " . count($files) . " files</span>\n";
        }
    } else {
        echo "<span class='error'>✗ Symlink not created</span>\n";
    }
    
    echo "</pre>";
    
    echo "<form method='get'>";
    echo "<button type='submit' name='action' value='diagnose'>🔍 Re-run Diagnostic</button>";
    echo "<button type='submit' name='action' value='test'>🧪 Test File Access</button>";
    echo "</form>";
}

// Test action
if ($action === 'test') {
    echo "<h3>Testing File Access...</h3>";
    echo "<pre>";
    
    // Get APP_URL from .env if possible
    $envPath = $laravelRoot . '/.env';
    $appUrl = 'https://vistora.sroy.es';
    
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        if (preg_match('/APP_URL=(.+)/', $envContent, $matches)) {
            $appUrl = trim($matches[1]);
            echo "APP_URL from .env: $appUrl\n";
        }
    }
    
    // Find a test file
    $testFile = null;
    if ($publicDir && is_dir($publicDir . '/storage/fms_documents')) {
        $files = glob($publicDir . '/storage/fms_documents/*');
        if (!empty($files)) {
            $testFile = basename($files[0]);
        }
    }
    
    if ($testFile) {
        $testUrl = rtrim($appUrl, '/') . '/storage/fms_documents/' . $testFile;
        echo "\n<span class='info'>Test URL:</span>\n";
        echo "<a href='$testUrl' target='_blank' style='color: #00d4ff;'>$testUrl</a>\n\n";
        
        // Try to access via curl or file_get_contents
        $headers = @get_headers($testUrl);
        if ($headers) {
            $statusCode = $headers[0];
            if (strpos($statusCode, '200') !== false) {
                echo "<span class='success'>✓ File is accessible! Status: $statusCode</span>\n";
            } elseif (strpos($statusCode, '403') !== false) {
                echo "<span class='error'>✗ 403 Forbidden - Check permissions</span>\n";
            } elseif (strpos($statusCode, '404') !== false) {
                echo "<span class='error'>✗ 404 Not Found - Symlink may not be working</span>\n";
            } else {
                echo "<span class='warning'>⚠ Status: $statusCode</span>\n";
            }
        } else {
            echo "<span class='warning'>⚠ Could not check URL headers</span>\n";
        }
    } else {
        echo "<span class='warning'>⚠ No test files found in fms_documents</span>\n";
    }
    
    echo "</pre>";
    
    echo "<form method='get'>";
    echo "<button type='submit' name='action' value='diagnose'>🔍 Re-run Diagnostic</button>";
    echo "<button type='submit' name='action' value='fix'>🔧 Fix Symlink</button>";
    echo "</form>";
}

echo "</div>";

// Permissions check
echo "<div class='box'>";
echo "<h2>📝 Permissions Check</h2>";
echo "<pre>";

if ($laravelRoot) {
    $pathsToCheck = [
        $laravelRoot . '/storage',
        $laravelRoot . '/storage/app',
        $laravelRoot . '/storage/app/public',
        $laravelRoot . '/storage/app/public/fms_documents',
    ];
    
    foreach ($pathsToCheck as $path) {
        if (file_exists($path)) {
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] : fileowner($path);
            echo "$path\n  Permissions: $perms, Owner: $owner\n\n";
        }
    }
}

echo "</pre>";
echo "</div>";

// Security warning
echo "<div class='box' style='border: 2px solid #ff4444;'>";
echo "<h2 style='color: #ff4444;'>⚠️ Security Warning</h2>";
echo "<p>DELETE THIS FILE AFTER USE!</p>";
echo "<p>This script exposes sensitive server information and should not remain on production servers.</p>";
echo "<pre>rm " . basename(__FILE__) . "</pre>";
echo "</div>";

?>
</body>
</html>
