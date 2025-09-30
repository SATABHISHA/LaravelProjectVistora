<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ReleasedPayrollExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithEvents, WithColumnFormatting
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
            
            // Static columns first (including serial number) - keep as is
            $excelRow[] = $row['serialNo'] ?? '';
            $excelRow[] = $row['empCode'] ?? '';
            $excelRow[] = $row['empName'] ?? '';
            $excelRow[] = $row['designation'] ?? '';
            $excelRow[] = $row['paidDays'] ?? 0;
            $excelRow[] = $row['dateOfJoining'] ?? '';
            
            // Dynamic gross columns - FORCE 0 for empty values
            foreach ($this->dynamicHeaders as $key => $header) {
                if (strpos($key, 'gross_') === 0) {
                    $value = $row[$key] ?? 0;
                    // Force numeric zero for any falsy values
                    $excelRow[] = (empty($value) && $value !== 0) ? 0 : $value;
                }
            }
            
            $excelRow[] = $row['monthlyTotalGross'] ?? 0;
            
            // Dynamic deduction columns - FORCE 0 for empty values
            foreach ($this->dynamicHeaders as $key => $header) {
                if (strpos($key, 'deduction_') === 0) {
                    $value = $row[$key] ?? 0;
                    // Force numeric zero for any falsy values
                    $excelRow[] = (empty($value) && $value !== 0) ? 0 : $value;
                }
            }
            
            $excelRow[] = $row['monthlyTotalRecurringDeductions'] ?? 0;
            $excelRow[] = $row['netTakeHomeMonthly'] ?? 0;
            
            // Status column at the end
            $excelRow[] = $row['status'] ?? 'Released';
            
            $excelRows[] = $excelRow;
        }
        
        return $excelRows;
    }

    public function headings(): array
    {
        $headers = [
            'S.No.',
            'Employee Code',
            'Employee Name',
            'Designation',
            'Paid Days',
            'Date of Joining'
        ];

        // Add dynamic gross headers
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'gross_') === 0) {
                $headers[] = $header;
            }
        }

        $headers[] = 'Monthly Total Gross';

        // Add dynamic deduction headers
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'deduction_') === 0) {
                $headers[] = $header;
            }
        }

        $headers = array_merge($headers, [
            'Monthly Total Deductions',
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
            // Style for SubBranch info (row 3)
            3 => [
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
                $sheet->insertNewRowBefore(1, 3); // Insert only 3 rows at the top (removed empty row)
                
                // Set title (row 1) - Updated title
                $sheet->setCellValue('A1', "Salary Sheet for the month of {$this->companyInfo['month']} {$this->companyInfo['year']}");
                $sheet->mergeCells("A1:{$lastColumn}1");
                
                // Set company name (row 2)
                $sheet->setCellValue('A2', "Company: {$this->companyInfo['companyName']}");
                $sheet->mergeCells("A2:{$lastColumn}2");
                
                // Set SubBranch (row 3)
                $sheet->setCellValue('A3', "Sub Branch: {$this->companyInfo['subBranch']}");
                $sheet->mergeCells("A3:{$lastColumn}3");
                
                // No empty row - header starts at row 4
                
                // Set row heights for better visibility
                $sheet->getRowDimension(1)->setRowHeight(35);
                $sheet->getRowDimension(2)->setRowHeight(30);
                $sheet->getRowDimension(3)->setRowHeight(30);
                $sheet->getRowDimension(4)->setRowHeight(30); // Header row
                
                // Apply styling to title rows (1, 2, 3) with proper vertical centering
                // Title row (row 1)
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 18,
                        'color' => ['rgb' => '000000']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        // 'startColor' => ['rgb' => '1F4E79'],
                        'startColor' => ['rgb' => 'FFFFFF']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);
                
                // Company row (row 2)
                $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'color' => ['rgb' => '000000']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        // 'startColor' => ['rgb' => '4472C4'],
                        'startColor' => ['rgb' => 'FFFFFF']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);
                
                // Sub Branch row (row 3)
                $sheet->getStyle("A3:{$lastColumn}3")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => '000000']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        // 'startColor' => ['rgb' => '9B59B6'], // Purple color for SubBranch
                        'startColor' => ['rgb' => 'FFFFFF']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);
                
                // Set default row height for all data rows
                $lastDataRow = $sheet->getHighestRow();
                for ($row = 5; $row <= $lastDataRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }
                
                // Apply header row styling (row 4) - ONLY the header row
                $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
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
                
                // Get the range for data rows only (starting from row 5)
                $dataStartRow = 5;
                $totalsRow = $lastDataRow; // Last row is totals
                
                // FIRST: Apply proper alignment to ALL data rows with vertical centering
                if ($lastDataRow >= $dataStartRow) {
                    $allDataRange = "A{$dataStartRow}:{$lastColumn}{$lastDataRow}";
                    $sheet->getStyle($allDataRange)->applyFromArray([
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
                            'horizontal' => Alignment::HORIZONTAL_LEFT, // Left align for better readability
                            'vertical' => Alignment::VERTICAL_CENTER,   // Vertical center is key
                            'wrapText' => false
                        ]
                    ]);
                }
                
                // SECOND: Apply specific alignment for different column types (excluding totals row)
                if ($lastDataRow > $dataStartRow) {
                    // Center align serial number column (A) - both horizontal and vertical
                    $serialRange = "A{$dataStartRow}:A" . ($lastDataRow - 1);
                    $sheet->getStyle($serialRange)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                    
                    // Center align Paid Days column (E) - both horizontal and vertical
                    $paidDaysRange = "E{$dataStartRow}:E" . ($lastDataRow - 1);
                    $sheet->getStyle($paidDaysRange)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                    
                    // Right align numeric columns (G onwards to second last column)
                    $lastColumnIndex = ord($lastColumn);
                    for ($col = ord('G'); $col < $lastColumnIndex; $col++) {
                        $columnLetter = chr($col);
                        $numericRange = "{$columnLetter}{$dataStartRow}:{$columnLetter}" . ($lastDataRow - 1);
                        $sheet->getStyle($numericRange)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                                'vertical' => Alignment::VERTICAL_CENTER
                            ]
                        ]);
                    }
                    
                    // Center align and style status column (last column, excluding totals) 
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
                
                // THIRD: Apply alternating row colors (light gray for odd rows, excluding totals)
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
                
                // FOURTH: Style the totals row with attractive colors and proper alignment
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
                
                // FIFTH: Apply borders to the entire table (header + data + totals)
                $tableRange = "A4:{$lastColumn}{$lastDataRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);
                
                // Freeze the header row for easy scrolling
                $sheet->freezePane('A5');
                
                // Auto-size all columns for better readability
                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            }
        ];
    }

    public function columnFormats(): array
    {
        $formats = [];
        
        // Set number format for Paid Days (column E)
        $formats['E'] = '0';
        
        // Set number format for all dynamic columns (starting from column G)
        $columnIndex = 7; // Starting from column G
        
        // Dynamic gross columns
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'gross_') === 0) {
                $columnLetter = chr(64 + $columnIndex); // Convert to column letter
                $formats[$columnLetter] = '#,##0.00'; // Number format with 2 decimal places
                $columnIndex++;
            }
        }
        
        // Monthly Total Gross
        $columnLetter = chr(64 + $columnIndex);
        $formats[$columnLetter] = '#,##0.00';
        $columnIndex++;
        
        // Dynamic deduction columns
        foreach ($this->dynamicHeaders as $key => $header) {
            if (strpos($key, 'deduction_') === 0) {
                $columnLetter = chr(64 + $columnIndex);
                $formats[$columnLetter] = '#,##0.00';
                $columnIndex++;
            }
        }
        
        // Monthly Total Deductions and Net Take Home
        $columnLetter = chr(64 + $columnIndex);
        $formats[$columnLetter] = '#,##0.00'; // Monthly Total Deductions
        $columnIndex++;
        
        $columnLetter = chr(64 + $columnIndex);
        $formats[$columnLetter] = '#,##0.00'; // Net Take Home
        
        return $formats;
    }
}