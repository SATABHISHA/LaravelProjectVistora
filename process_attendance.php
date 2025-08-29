<?php
// filepath: /Users/debashispal/Downloads/git/LaravelProjectVistora/process_attendance.php

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

try {
    // Set execution time limit to 0 (no time limit)
    set_time_limit(0);
    
    Log::info('Starting attendance processing at ' . now());
    
    $yesterday = Carbon::yesterday()->format('Y-m-d');
    Log::info("Processing attendance for date: $yesterday");
    
    // First, update employees who checked in but didn't check out
    $updatedCount = DB::table('attendances')
        ->where('date', $yesterday)
        ->where('status', 'IN')
        ->update([
            'checkOut' => 'N/A', 
            'status' => 'OUT', 
            'attendanceStatus' => 'Absent',
            'totalHrsForTheDay' => '0',
            'updated_at' => now()
        ]);
    
    Log::info("Updated $updatedCount records with missing checkouts");
    
    // Next, insert records for employees who didn't check in at all
    $query = "
        INSERT INTO attendances (
            puid, corpId, userName, empCode, companyName, 
            checkIn, checkOut, status, attendanceStatus, 
            totalHrsForTheDay, date, created_at, updated_at
        )
        SELECT 
            UUID(), -- Generate UUID for puid
            u.corp_id,
            u.username,
            u.empcode,
            u.company_name,
            'N/A',
            'N/A',
            'OUT',
            'Absent',
            '0',
            ?, -- Yesterday's date
            NOW(),
            NOW()
        FROM userlogin u
        WHERE 
            u.active_yn = 1
            AND u.username IS NOT NULL 
            AND u.corp_id IS NOT NULL
            AND u.empcode IS NOT NULL
            AND u.company_name IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM attendances a 
                WHERE a.corpId = u.corp_id 
                AND a.empCode = u.empcode
                AND a.date = ?
            )
    ";
    
    $insertResult = DB::insert($query, [$yesterday, $yesterday]);
    
    Log::info("Created absence records for employees with no attendance");
    Log::info("Attendance processing completed successfully");
    
    echo "Attendance processing completed successfully\n";

} catch (\Exception $e) {
    Log::error('Attendance processing failed: ' . $e->getMessage());
    Log::error($e->getTraceAsString());
    echo "Error: " . $e->getMessage() . "\n";
}