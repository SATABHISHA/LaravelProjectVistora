<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check for duplicate attendance records
$records = DB::table('attendances')
    ->where('corpId', 'test')
    ->where('empCode', 'EMP001')
    ->whereBetween('date', ['2025-11-01', '2025-11-30'])
    ->where('attendanceStatus', 'Absent')
    ->select('date', 'attendanceStatus', 'status', 'id')
    ->orderBy('date')
    ->get();

echo "Total absent records: " . $records->count() . "\n\n";
echo "ID | Date | AttendanceStatus | Status\n";
echo "----------------------------------------\n";

foreach ($records as $r) {
    echo "{$r->id} | {$r->date} | {$r->attendanceStatus} | {$r->status}\n";
}

echo "\n\nChecking for duplicates by date:\n";
$duplicates = DB::table('attendances')
    ->where('corpId', 'test')
    ->where('empCode', 'EMP001')
    ->whereBetween('date', ['2025-11-01', '2025-11-30'])
    ->where('attendanceStatus', 'Absent')
    ->select('date', DB::raw('COUNT(*) as count'))
    ->groupBy('date')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->count() > 0) {
    echo "Found duplicate dates:\n";
    foreach ($duplicates as $dup) {
        echo "Date: {$dup->date} - Count: {$dup->count}\n";
    }
} else {
    echo "No duplicate dates found.\n";
}

// Now check the correct count - using DISTINCT date
$correctCount = DB::table('attendances')
    ->where('corpId', 'test')
    ->where('empCode', 'EMP001')
    ->whereBetween('date', ['2025-11-01', '2025-11-30'])
    ->where('attendanceStatus', 'Absent')
    ->distinct()
    ->count('date');

echo "\n\nCorrect absent days count (DISTINCT dates): $correctCount\n";
