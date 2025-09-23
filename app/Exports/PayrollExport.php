<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PayrollExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    protected $data;
    protected $dynamicHeaders;
    protected $companyInfo;
    
    public function __construct($data, $dynamicHeaders, $companyInfo)
    {
        $this->data = $data;
        $this->dynamicHeaders = $dynamicHeaders;
        $this->companyInfo = $companyInfo;
    }

    public function array(): array
    {
        // Prepare data rows for Excel
        $excelRows = [];
        
        foreach ($this->data as $row) {
            $excelRow = [];
            
            // Static columns first (without status)
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
            
            // Status column at the end
            $excelRow[] = $row['status'] ?? '';
            
            $excelRows[] = $excelRow;
        }
        
        return $excelRows;
    }

    public function headings(): array
    {
        $headers = [
            'Employee Code',
            'Employee Name'
            // Removed status from here
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
            'Net Take Home Monthly',
            'Status' // Added status at the end
        ]);

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style for the main title (row 1)
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 18,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E79']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
            // Style for company info (row 2)
            2 => [
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
            // Style for period info (row 3)
            3 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]
            // Removed header row styling from here - it will be handled in registerEvents
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                
                // Insert title and company information rows at the top
                $sheet->insertNewRowBefore(1, 4); // Insert 4 rows at the top
                
                // Set title (row 1)
                $sheet->setCellValue('A1', 'PAYROLL REPORT');
                $sheet->mergeCells("A1:{$lastColumn}1");
                
                // Set company name (row 2)
                $sheet->setCellValue('A2', "Company: {$this->companyInfo['companyName']}");
                $sheet->mergeCells("A2:{$lastColumn}2");
                
                // Set period (row 3)
                $sheet->setCellValue('A3', "Period: {$this->companyInfo['month']} {$this->companyInfo['year']}");
                $sheet->mergeCells("A3:{$lastColumn}3");
                
                // Row 4 is empty for spacing
                
                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(25);
                $sheet->getRowDimension(4)->setRowHeight(15);
                $sheet->getRowDimension(5)->setRowHeight(25); // Header row
                
                // Apply header row styling (row 5) - ONLY the header row
                $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2F5597']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);
                
                // Get the range for data rows only (starting from row 6)
                $dataStartRow = 6;
                $lastDataRow = $sheet->getHighestRow();
                
                // FIRST: Reset ALL data rows to default formatting
                if ($lastDataRow >= $dataStartRow) {
                    $dataRange = "A{$dataStartRow}:{$lastColumn}{$lastDataRow}";
                    $sheet->getStyle($dataRange)->applyFromArray([
                        'font' => [
                            'bold' => false,
                            'size' => 11,
                            'color' => ['rgb' => '000000'] // Black text
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF'] // White background
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                }
                
                // SECOND: Apply alternating row colors (light gray for odd rows)
                for ($row = $dataStartRow; $row <= $lastDataRow; $row++) {
                    if (($row - $dataStartRow) % 2 == 1) { // Every other row (odd rows)
                        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'] // Light gray
                            ]
                        ]);
                    }
                }
                
                // THIRD: Apply borders to the entire table (header + data)
                $tableRange = "A5:{$lastColumn}{$lastDataRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);
                
                // FOURTH: Set specific alignment for different column types
                if ($lastDataRow >= $dataStartRow) {
                    // Left align text columns (A, B - Employee Code, Name)
                    $textRange = "A{$dataStartRow}:B{$lastDataRow}";
                    $sheet->getStyle($textRange)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                    
                    // Right align numeric columns (C to second last column)
                    $lastColumnBefore = chr(ord($lastColumn) - 1); // Get column before the last one
                    if ($lastColumnBefore >= 'C') {
                        $numericRange = "C{$dataStartRow}:{$lastColumnBefore}{$lastDataRow}";
                        $sheet->getStyle($numericRange)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                                'vertical' => Alignment::VERTICAL_CENTER
                            ]
                        ]);
                    }
                    
                    // Center align status column (last column)
                    $statusRange = "{$lastColumn}{$dataStartRow}:{$lastColumn}{$lastDataRow}";
                    $sheet->getStyle($statusRange)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                }
                
                // Freeze the header row for easy scrolling
                $sheet->freezePane('A6');
                
                // Auto-size all columns for better readability
                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            }
        ];
    }
}