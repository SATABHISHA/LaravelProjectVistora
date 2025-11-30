<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Test parameters - Check what data exists
echo "Checking what corp IDs and employee codes exist in attendances table...\n\n";

$sampleData = DB::table('attendances')
    ->select('corpId', 'empCode', 'date', 'attendanceStatus', 'status')
    ->orderBy('date', 'desc')
    ->limit(10)
    ->get();

echo "Recent attendance records:\n";
foreach ($sampleData as $record) {
    echo "Corp: {$record->corpId}, Emp: {$record->empCode}, Date: {$record->date}, AttStatus: {$record->attendanceStatus}, Status: {$record->status}\n";
}

echo "\n\n";

// Now test with actual data
$corpId = 'MACO';
$empcode = 'MCI0534';

// Get current month start and end dates
$now = Carbon::create(2025, 11, 1, 0, 0, 0, 'Asia/Kolkata'); // Use November for testing
$monthStart = $now->copy()->startOfMonth()->format('Y-m-d');
$monthEnd = $now->copy()->endOfMonth()->format('Y-m-d');

echo "Testing attendance for:\n";
echo "Corp ID: $corpId\n";
echo "Employee: $empcode\n";
echo "Month: {$now->format('F Y')}\n";
echo "Date Range: $monthStart to $monthEnd\n\n";

// Get all attendance records for this employee in current month
$allAttendances = DB::table('attendances')
    ->where('corpId', $corpId)
    ->where('empCode', $empcode)
    ->whereBetween('date', [$monthStart, $monthEnd])
    ->get();

echo "Total attendance records: " . $allAttendances->count() . "\n\n";

// Show first 5 records
echo "Sample records:\n";
foreach ($allAttendances->take(5) as $att) {
    echo "Date: {$att->date}, Status: {$att->attendanceStatus}, Status2: {$att->status}\n";
}

echo "\n";

// Count absences using NEW logic (fixed)
$totalAbsencesNew = DB::table('attendances')
    ->where('corpId', $corpId)
    ->where('empCode', $empcode)
    ->whereBetween('date', [$monthStart, $monthEnd])
    ->where('attendanceStatus', 'Absent')
    ->count();

echo "Total absences (NEW fixed logic): $totalAbsencesNew\n";

// Count absences using OLD logic (with orWhere issue)
$totalAbsencesOld = DB::table('attendances')
    ->where('corpId', $corpId)
    ->where('empCode', $empcode)
    ->whereBetween('date', [$monthStart, $monthEnd])
    ->where(function($query) {
        $query->where('attendanceStatus', 'Absent')
              ->orWhere('status', 'ABSENT');
    })
    ->count();

echo "Total absences (OLD logic with orWhere): $totalAbsencesOld\n\n";

// Check different status values
$statuses = DB::table('attendances')
    ->where('corpId', $corpId)
    ->where('empCode', $empcode)
    ->whereBetween('date', [$monthStart, $monthEnd])
    ->select('attendanceStatus', 'status', DB::raw('COUNT(*) as count'))
    ->groupBy('attendanceStatus', 'status')
    ->get();

echo "Status breakdown:\n";
foreach ($statuses as $status) {
    echo "attendanceStatus: '{$status->attendanceStatus}', status: '{$status->status}', count: {$status->count}\n";
}
