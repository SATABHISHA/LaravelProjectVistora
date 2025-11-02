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

class PayrollArrearsExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithEvents, WithColumnFormatting
{
    protected $data;
    protected $dynamicHeaders;
    protected $companyInfo;
    protected $arrearsInfo;
    
    public function __construct($data, $dynamicHeaders, $companyInfo, $arrearsInfo)
    {
        $this->data = $data;
        $this->dynamicHeaders = $dynamicHeaders;
        $this->companyInfo = $companyInfo;
        $this->arrearsInfo = $arrearsInfo;
    }

    public function array(): array
    {
        $excelRows = [];

        foreach ($this->data as $row) {
            $excelRow = [];

            // Static columns first
            $excelRow[] = $row['serialNo'] ?? '';
            $excelRow[] = $row['empCode'] ?? '';
            $excelRow[] = $row['empName'] ?? '';
            $excelRow[] = $row['designation'] ?? '';
            $excelRow[] = $row['arrearStatus'] ?? '';
            $excelRow[] = $row['arrearsEffectiveFrom'] ?? '';
            $excelRow[] = $row['arrearsMonthCount'] ?? 0;
            
            // Helper to normalize values
            $normalize = function($val) {
                if ($val === null || $val === '') {
                    return 0.0;
                }
                return is_numeric($val) ? (float)$val : $val;
            };

            // Current month gross components
            foreach ($this->dynamicHeaders['gross'] as $key => $header) {
                $value = array_key_exists($key, $row) ? $row[$key] : null;
                $excelRow[] = $normalize($value);
            }

            // Current Monthly Total Gross
            $excelRow[] = $normalize($row['monthlyTotalGross'] ?? 0);

            // Arrears gross components breakup
            foreach ($this->dynamicHeaders['gross'] as $key => $header) {
                $arrearsKey = 'arrears_' . $key;
                $value = array_key_exists($arrearsKey, $row) ? $row[$arrearsKey] : null;
                $excelRow[] = $normalize($value);
            }

            // Total Gross Arrears
            $excelRow[] = $normalize($row['totalGrossArrears'] ?? 0);

            // Current month deductions
            foreach ($this->dynamicHeaders['deductions'] as $key => $header) {
                $value = array_key_exists($key, $row) ? $row[$key] : null;
                $excelRow[] = $normalize($value);
            }

            // Current Monthly Total Deductions
            $excelRow[] = $normalize($row['monthlyTotalDeductions'] ?? 0);

            // Arrears deductions breakup
            foreach ($this->dynamicHeaders['deductions'] as $key => $header) {
                $arrearsKey = 'arrears_' . $key;
                $value = array_key_exists($arrearsKey, $row) ? $row[$arrearsKey] : null;
                $excelRow[] = $normalize($value);
            }

            // Total Deduction Arrears
            $excelRow[] = $normalize($row['totalDeductionArrears'] ?? 0);

            // Net Arrears Payable
            $excelRow[] = $normalize($row['netArrearsPayable'] ?? 0);

            // Net Take Home (Current Month + Arrears)
            $excelRow[] = $normalize($row['netTakeHomeWithArrears'] ?? 0);

            // Status column
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
            'Arrear Status',
            'Arrears From',
            'Arrear Months',
        ];

        // Current month gross headers
        foreach ($this->dynamicHeaders['gross'] as $key => $header) {
            $headers[] = $header . ' (Current)';
        }
        $headers[] = 'Current Monthly Gross';

        // Arrears gross headers
        foreach ($this->dynamicHeaders['gross'] as $key => $header) {
            $headers[] = $header . ' (Arrears)';
        }
        $headers[] = 'Total Gross Arrears';

        // Current month deduction headers
        foreach ($this->dynamicHeaders['deductions'] as $key => $header) {
            $headers[] = $header . ' (Current)';
        }
        $headers[] = 'Current Monthly Deductions';

        // Arrears deduction headers
        foreach ($this->dynamicHeaders['deductions'] as $key => $header) {
            $headers[] = $header . ' (Arrears)';
        }
        $headers[] = 'Total Deduction Arrears';

        $headers[] = 'Net Arrears Payable';
        $headers[] = 'Net Take Home (With Arrears)';
        $headers[] = 'Status';

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '000000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ],
            3 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ],
            4 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF9C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                
                // Insert 4 rows at the top for headers
                $sheet->insertNewRowBefore(1, 4);
                
                // Row 1: Main Title
                $sheet->setCellValue('A1', "Salary Sheet with Arrears for {$this->companyInfo['month']} {$this->companyInfo['year']}");
                $sheet->mergeCells("A1:{$lastColumn}1");
                
                // Row 2: Company Info
                $sheet->setCellValue('A2', "Company: {$this->companyInfo['companyName']}");
                $sheet->mergeCells("A2:{$lastColumn}2");
                
                // Row 3: Sub Branch Info
                $sheet->setCellValue('A3', "Sub Branch: {$this->companyInfo['subBranch']}");
                $sheet->mergeCells("A3:{$lastColumn}3");
                
                // Row 4: Arrears Summary
                $arrearsMsg = "Arrears Summary: {$this->arrearsInfo['totalEmployeesWithArrears']} employees with salary revision | " .
                              "{$this->arrearsInfo['employeesWithoutRevision']} without revision | " .
                              "Total: {$this->arrearsInfo['totalEmployees']} employees";
                $sheet->setCellValue('A4', $arrearsMsg);
                $sheet->mergeCells("A4:{$lastColumn}4");
                
                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(35);
                $sheet->getRowDimension(2)->setRowHeight(30);
                $sheet->getRowDimension(3)->setRowHeight(30);
                $sheet->getRowDimension(4)->setRowHeight(30);
                $sheet->getRowDimension(5)->setRowHeight(30); // Header row
                
                // Apply styles to info rows
                for ($i = 1; $i <= 4; $i++) {
                    $sheet->getStyle("A{$i}:{$lastColumn}{$i}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => $i == 1 ? 18 : ($i == 4 ? 12 : 14),
                            'color' => ['rgb' => '000000']
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $i == 4 ? 'FFF9C4' : 'FFFFFF']
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                }
                
                $lastDataRow = $sheet->getHighestRow();
                
                // Style header row (row 5)
                $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true
                    ]
                ]);
                
                $dataStartRow = 6;
                $totalsRow = $lastDataRow;
                
                // Style data rows
                if ($lastDataRow >= $dataStartRow) {
                    $allDataRange = "A{$dataStartRow}:{$lastColumn}{$lastDataRow}";
                    $sheet->getStyle($allDataRange)->applyFromArray([
                        'font' => ['bold' => false, 'size' => 11, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => false
                        ]
                    ]);
                }
                
                // Alternating row colors
                for ($row = $dataStartRow; $row < $totalsRow; $row++) {
                    if (($row - $dataStartRow) % 2 == 1) {
                        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]
                        ]);
                    }
                }
                
                // Style totals row
                $sheet->getStyle("A{$totalsRow}:{$lastColumn}{$totalsRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF6B35']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);
                
                // Apply borders
                $tableRange = "A5:{$lastColumn}{$lastDataRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);
                
                // Freeze panes
                $sheet->freezePane('A6');
                
                // Auto-size columns
                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            }
        ];
    }

    public function columnFormats(): array
    {
        $formats = [];
        $columnIndex = 8; // Starting from column H (after static columns)
        
        // Format all numeric columns
        $totalColumns = 7 + // Static columns
                       count($this->dynamicHeaders['gross']) + 1 + // Current gross + total
                       count($this->dynamicHeaders['gross']) + 1 + // Arrears gross + total
                       count($this->dynamicHeaders['deductions']) + 1 + // Current deductions + total
                       count($this->dynamicHeaders['deductions']) + 1 + // Arrears deductions + total
                       2; // Net arrears + Net take home
        
        for ($i = $columnIndex; $i <= $totalColumns; $i++) {
            $columnLetter = $this->getColumnLetter($i);
            $formats[$columnLetter] = '#,##0.00';
        }
        
        return $formats;
    }
    
    private function getColumnLetter($columnIndex)
    {
        $letter = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)) . $letter;
            $columnIndex = floor($columnIndex / 26);
        }
        return $letter;
    }
}
