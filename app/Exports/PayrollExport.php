<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Font;

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
            
            // Dynamic gross columns
            foreach ($this->dynamicHeaders as $key => $header) {
                if (strpos($key, 'gross_') === 0) {
                    $excelRow[] = $row[$key] ?? 0;
                }
            }
            
            $excelRow[] = $row['monthlyTotalGross'] ?? 0;
            $excelRow[] = $row['annualTotalGross'] ?? 0;
            
            // Dynamic allowance columns
            foreach ($this->dynamicHeaders as $key => $header) {
                if (strpos($key, 'allowance_') === 0) {
                    $excelRow[] = $row[$key] ?? 0;
                }
            }
            
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
            
            $excelRows[] = $excelRow;
        }
        
        return $excelRows;
    }

    public function headings(): array
    {
        $headers = [
            'Employee Code',
            'Employee Name'
        ];

        // Add dynamic gross headers
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'gross_') === 0) {
                $headers[] = $header;
            }
        }

        $headers[] = 'Monthly Total Gross';
        $headers[] = 'Annual Total Gross';

        // Add dynamic allowance headers
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'allowance_') === 0) {
                $headers[] = $header;
            }
        }

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
            'Net Take Home Monthly'
        ]);

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Make first row (company info) bold and larger
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => '0066CC']
                ]
            ],
            // Make header row bold
            3 => ['font' => ['bold' => true]],
        ];
    }
}