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
    protected $companyName;
    protected $month;
    protected $year;
    protected $dynamicHeaders;

    public function __construct($data, $companyName, $month, $year, $dynamicHeaders = [])
    {
        $this->data = $data;
        $this->companyName = $companyName;
        $this->month = $month;
        $this->year = $year;
        $this->dynamicHeaders = $dynamicHeaders;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        $headers = [
            'Employee Code',
            'Full Name',
            'Designation',
            'Date Of Joining',
            'Gross Salary',
            'Gross Deduction',
            'Net Take Home'
        ];
        
        // Add dynamic headers
        return array_merge($headers, $this->dynamicHeaders);
    }

    public function styles(Worksheet $sheet)
    {
        // Add top headers
        $sheet->setCellValue('A1', 'Company Name: ' . $this->companyName);
        $sheet->setCellValue('A2', 'Salary Month: ' . $this->month);
        $sheet->setCellValue('A3', 'Year: ' . $this->year);
        
        // Calculate last column for merging
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));
        
        // Merge cells for top headers
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->mergeCells('A3:' . $lastCol . '3');
        
        // Style top headers
        $sheet->getStyle('A1:' . $lastCol . '3')->getFont()->setBold(true);
        
        // Style column headers (row 4)
        $headerRow = 4;
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6E6FA');
        
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true, 'size' => 12]],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            $headerRow => ['font' => ['bold' => true]],
        ];
    }
}
