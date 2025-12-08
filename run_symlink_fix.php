<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Symlink Fix</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .button {
            background: #4CAF50;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
            display: inline-block;
            text-decoration: none;
        }
        .button:hover {
            background: #45a049;
        }
        .output {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            max-height: 600px;
            overflow-y: auto;
        }
        .success {
            color: #4CAF50;
        }
        .error {
            color: #f44336;
        }
        .warning {
            color: #ff9800;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
        }
        .danger {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Laravel Storage Symlink Fix</h1>
        
        <div class="info">
            <strong>ℹ️ What this does:</strong><br>
            This script will automatically fix your storage symlink so that file downloads work correctly on your live server.
        </div>

        <?php if (!isset($_GET['run'])): ?>
            
            <p><strong>Click the button below to run the automated fix:</strong></p>
            
            <a href="?run=1" class="button">▶️ Run Fix Now</a>
            
            <div style="margin-top: 30px;">
                <h3>The script will:</h3>
                <ul>
                    <li>✓ Detect your Laravel directory structure</li>
                    <li>✓ Find the correct public directory</li>
                    <li>✓ Remove any incorrectly placed symlinks</li>
                    <li>✓ Create the correct symlink: <code>public/storage → ../storage/app/public</code></li>
                    <li>✓ Verify the symlink works</li>
                    <li>✓ Test file accessibility</li>
                </ul>
            </div>

            <div class="danger" style="margin-top: 30px;">
                <strong>⚠️ IMPORTANT - Security Notice:</strong><br>
                After running this script successfully, <strong>DELETE THIS FILE IMMEDIATELY</strong> for security reasons.
            </div>

        <?php else: ?>
            
            <div class="output">
<?php
// Execute the fix script and capture output
ob_start();

try {
    include('fix_symlink_automated.php');
    $output = ob_get_clean();
    
    // Add syntax highlighting
    $output = str_replace('✓', '<span class="success">✓</span>', $output);
    $output = str_replace('✗', '<span class="error">✗</span>', $output);
    $output = str_replace('⚠', '<span class="warning">⚠</span>', $output);
    
    echo $output;
    
} catch (Exception $e) {
    ob_end_clean();
    echo '<span class="error">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>
            </div>

            <div class="info" style="margin-top: 20px;">
                <strong>✅ Next Steps:</strong><br>
                1. Test a download URL in your browser<br>
                2. If successful, <strong>DELETE this file immediately</strong>: <code>run_symlink_fix.php</code><br>
                3. Also delete: <code>fix_symlink_automated.php</code><br>
                4. Clear Laravel cache: <code>php artisan config:clear</code>
            </div>

            <a href="?" class="button" style="background: #2196F3;">🔄 Run Again</a>

        <?php endif; ?>

    </div>
</body>
</html>
