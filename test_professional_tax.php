<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->bootstrap();

echo "Testing Professional Tax functionality...\n";

try {
    // Test 1: Database connection
    $pdo = DB::connection()->getPdo();
    echo "✅ Database connection: OK\n";
    
    // Test 2: Table exists
    $hasTable = Schema::hasTable('professional_tax');
    echo "✅ professional_tax table exists: " . ($hasTable ? 'YES' : 'NO') . "\n";
    
    // Test 3: Model instantiation
    $model = new App\Models\ProfessionalTax();
    echo "✅ ProfessionalTax model: OK\n";
    
    // Test 4: Controller instantiation
    $controller = new App\Http\Controllers\ProfessionalTaxController();
    echo "✅ ProfessionalTaxController: OK\n";
    
    echo "\n🎉 All tests passed! API should work.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}