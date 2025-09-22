<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $data;
    protected $dynamicHeaders;
    
    public function __construct($data, $dynamicHeaders)
    {
        $this->data = $data;
        $this->dynamicHeaders = $dynamicHeaders;
    }

    public function array(): array
    {
        // Prepare data rows for Excel
        $excelRows = [];
        
        foreach ($this->data as $row) {
            $excelRow = [];
            
            // Static columns first
            $excelRow[] = $row['empCode'] ?? '';
            $excelRow[] = $row['empName'] ?? '';
            $excelRow[] = $row['companyName'] ?? '';
            
            // Dynamic gross columns
            foreach ($this->dynamicHeaders as $key => $header) {
                if (strpos($key, 'gross_') === 0) {
                    $excelRow[] = $row[$key] ?? 0;
                }
            }
            
            $excelRow[] = $row['monthlyTotalGross'] ?? 0;
            $excelRow[] = $row['annualTotalGross'] ?? 0;
            
            // Dynamic benefit columns
            foreach ($this->dynamicHeaders as $key => $header) {
                if (strpos($key, 'benefit_') === 0) {
                    $excelRow[] = $row[$key] ?? 0;
                }
            }
            
            $excelRow[] = $row['monthlyTotalBenefits'] ?? 0;
            $excelRow[] = $row['annualTotalBenefits'] ?? 0;
            
            // Dynamic deduction columns
            foreach ($this->dynamicHeaders as $key => $header) {
                if (strpos($key, 'deduction_') === 0) {
                    $excelRow[] = $row[$key] ?? 0;
                }
            }
            
            $excelRow[] = $row['monthlyTotalRecurringDeductions'] ?? 0;
            $excelRow[] = $row['annualTotalRecurringDeductions'] ?? 0;
            $excelRow[] = $row['netTakeHomeMonthly'] ?? 0;
            $excelRow[] = $row['year'] ?? '';
            $excelRow[] = $row['month'] ?? '';
            
            $excelRows[] = $excelRow;
        }
        
        return $excelRows;
    }

    public function headings(): array
    {
        $headers = [
            'Employee Code',
            'Employee Name', 
            'Company Name'
        ];

        // Add dynamic gross headers
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'gross_') === 0) {
                $headers[] = $header;
            }
        }

        $headers[] = 'Monthly Total Gross';
        $headers[] = 'Annual Total Gross';

        // Add dynamic benefit headers
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'benefit_') === 0) {
                $headers[] = $header;
            }
        }

        $headers[] = 'Monthly Total Benefits';
        $headers[] = 'Annual Total Benefits';

        // Add dynamic deduction headers
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'deduction_') === 0) {
                $headers[] = $header;
            }
        }

        $headers = array_merge($headers, [
            'Monthly Total Deductions',
            'Annual Total Deductions',
            'Net Take Home Monthly',
            'Year',
            'Month'
        ]);

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Make header row bold
            1 => ['font' => ['bold' => true]],
        ];
    }
}