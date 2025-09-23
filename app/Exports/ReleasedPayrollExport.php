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

class ReleasedPayrollExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
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
            
            // Static columns first (including new columns)
            $excelRow[] = $row['empCode'] ?? '';
            $excelRow[] = $row['empName'] ?? '';
            $excelRow[] = $row['designation'] ?? ''; // New column
            $excelRow[] = $row['dateOfJoining'] ?? ''; // New column
            
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
            
            // Status column at the end (will always be 'Released')
            $excelRow[] = $row['status'] ?? 'Released';
            
            $excelRows[] = $excelRow;
        }
        
        return $excelRows;
    }

    public function headings(): array
    {
        $headers = [
            'Employee Code',
            'Employee Name',
            'Designation', // New column
            'Date of Joining' // New column
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
            'Status'
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
            ],
            // Style for status filter info (row 4)
            4 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E74C3C'] // Red color for Released status
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
            // Style for SubBranch info (row 5) - NEW
            5 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '9B59B6'] // Purple color for SubBranch
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                
                // Insert title and company information rows at the top
                $sheet->insertNewRowBefore(1, 6); // Insert 6 rows at the top (added SubBranch row)
                
                // Set title (row 1)
                $sheet->setCellValue('A1', 'RELEASED PAYROLL REPORT');
                $sheet->mergeCells("A1:{$lastColumn}1");
                
                // Set company name (row 2)
                $sheet->setCellValue('A2', "Company: {$this->companyInfo['companyName']}");
                $sheet->mergeCells("A2:{$lastColumn}2");
                
                // Set period (row 3)
                $sheet->setCellValue('A3', "Period: {$this->companyInfo['month']} {$this->companyInfo['year']}");
                $sheet->mergeCells("A3:{$lastColumn}3");
                
                // Set status filter (row 4)
                $sheet->setCellValue('A4', "Status Filter: Released Only");
                $sheet->mergeCells("A4:{$lastColumn}4");
                
                // Set SubBranch (row 5) - NEW
                $sheet->setCellValue('A5', "SubBranch: {$this->companyInfo['subBranch']}");
                $sheet->mergeCells("A5:{$lastColumn}5");
                
                // Row 6 is empty for spacing
                
                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(25);
                $sheet->getRowDimension(4)->setRowHeight(20); // Status filter row
                $sheet->getRowDimension(5)->setRowHeight(25); // SubBranch row
                $sheet->getRowDimension(6)->setRowHeight(15); // Empty spacing row
                $sheet->getRowDimension(7)->setRowHeight(25); // Header row
                
                // Apply header row styling (row 7) - ONLY the header row
                $sheet->getStyle("A7:{$lastColumn}7")->applyFromArray([
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
                
                // Get the range for data rows only (starting from row 8)
                $dataStartRow = 8;
                $lastDataRow = $sheet->getHighestRow();
                $totalsRow = $lastDataRow; // Last row is totals
                
                // FIRST: Reset ALL data rows to default formatting (except totals row)
                if ($lastDataRow > $dataStartRow) {
                    $dataRange = "A{$dataStartRow}:{$lastColumn}" . ($lastDataRow - 1); // Exclude totals row
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
                
                // SECOND: Apply alternating row colors (light gray for odd rows, excluding totals)
                for ($row = $dataStartRow; $row < $totalsRow; $row++) {
                    if (($row - $dataStartRow) % 2 == 1) { // Every other row (odd rows)
                        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'] // Light gray
                            ]
                        ]);
                    }
                }
                
                // THIRD: Style the totals row with attractive colors
                $sheet->getStyle("A{$totalsRow}:{$lastColumn}{$totalsRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF'] // White text
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FF6B35'] // Attractive orange/red color
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);
                
                // FOURTH: Apply borders to the entire table (header + data + totals)
                $tableRange = "A7:{$lastColumn}{$lastDataRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);
                
                // FIFTH: Set specific alignment for different column types (excluding totals row)
                if ($lastDataRow > $dataStartRow) {
                    // Left align text columns (A, B, C, D - Employee Code, Name, Designation, Date)
                    $textRange = "A{$dataStartRow}:D" . ($lastDataRow - 1);
                    $sheet->getStyle($textRange)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                    
                    // Right align numeric columns (E to second last column)
                    $lastColumnBefore = chr(ord($lastColumn) - 1); // Get column before the last one
                    if ($lastColumnBefore >= 'E') {
                        $numericRange = "E{$dataStartRow}:{$lastColumnBefore}" . ($lastDataRow - 1);
                        $sheet->getStyle($numericRange)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                                'vertical' => Alignment::VERTICAL_CENTER
                            ]
                        ]);
                    }
                    
                    // Center align and style status column (last column, excluding totals) with green background for Released
                    $statusRange = "{$lastColumn}{$dataStartRow}:{$lastColumn}" . ($lastDataRow - 1);
                    $sheet->getStyle($statusRange)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D5EFDF'] // Light green background for Released status
                        ],
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '27AE60'] // Green text for Released
                        ]
                    ]);
                }
                
                // Freeze the header row for easy scrolling
                $sheet->freezePane('A8');
                
                // Auto-size all columns for better readability
                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            }
        ];
    }
}