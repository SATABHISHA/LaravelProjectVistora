<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - {{ $data['empCode'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .document-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
        }
        .employee-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .employee-row {
            display: table-row;
        }
        .employee-cell {
            display: table-cell;
            padding: 5px 0;
            width: 50%;
        }
        .employee-cell.left {
            padding-right: 20px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .salary-table th,
        .salary-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        .salary-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .section-header {
            background-color: #e0e0e0 !important;
            font-weight: bold;
            text-align: center !important;
        }
        .amount {
            text-align: right !important;
        }
        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .net-pay {
            background-color: #d4edda !important;
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        /* Print styles for PDF conversion */
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
        }
        
        /* Additional styles for browser PDF generation */
        @page {
            size: A4;
            margin: 1cm;
        }
        .period-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            background-color: #f8f9fa;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="no-print" style="background-color: #e7f3ff; padding: 10px; margin-bottom: 20px; border: 1px solid #b3d9ff; border-radius: 4px;">
        <strong>📄 Salary Slip Ready!</strong><br>
        <small>To save as PDF: Press <strong>Ctrl+P</strong> (Windows) or <strong>Cmd+P</strong> (Mac) and choose "Save as PDF"</small>
    </div>

    <div class="header">
        <div class="company-name">{{ $data['companyName'] }}</div>
        <div class="document-title">SALARY SLIP</div>
    </div>

    <div class="period-info">
        <strong>Pay Period: {{ $data['month'] }} {{ $data['year'] }}</strong>
    </div>

    <div class="employee-info">
        <div class="employee-row">
            <div class="employee-cell left">
                <span class="label">Employee Code:</span> {{ $data['empCode'] }}
            </div>
            <div class="employee-cell">
                <span class="label">Employee Name:</span> {{ $employeeDetails['full_name'] ?? 'N/A' }}
            </div>
        </div>
        <div class="employee-row">
            <div class="employee-cell left">
                <span class="label">Designation:</span> {{ $employeeDetails['designation'] ?? 'N/A' }}
            </div>
            <div class="employee-cell">
                <span class="label">Date of Joining:</span> {{ $employeeDetails['date_of_joining'] ?? 'N/A' }}
            </div>
        </div>
        <div class="employee-row">
            <div class="employee-cell left">
                <span class="label">Department:</span> {{ $employeeDetails['department'] ?? 'N/A' }}
            </div>
            <div class="employee-cell">
                <span class="label">Status:</span> {{ $data['status'] }}
            </div>
        </div>
    </div>

    <table class="salary-table">
        <thead>
            <tr>
                <th width="60%">Component</th>
                <th width="20%">Type</th>
                <th width="20%">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Gross Salary Components -->
            @if(count($data['gross']) > 0)
                <tr class="section-header">
                    <td colspan="3">EARNINGS</td>
                </tr>
                @foreach($data['gross'] as $item)
                    <tr>
                        <td>{{ $item['componentName'] ?? 'Unknown Component' }}</td>
                        <td>Gross</td>
                        <td class="amount">{{ number_format((float)($item['calculatedValue'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td><strong>Total Earnings</strong></td>
                    <td></td>
                    <td class="amount"><strong>{{ number_format($summary['totalGross']['monthly'], 2) }}</strong></td>
                </tr>
            @endif

            <!-- Other Benefits & Allowances -->
            @if(count($data['otherBenefitsAllowances']) > 0)
                <tr class="section-header">
                    <td colspan="3">ALLOWANCES & BENEFITS</td>
                </tr>
                @foreach($data['otherBenefitsAllowances'] as $item)
                    <tr>
                        <td>{{ $item['componentName'] ?? 'Unknown Component' }}</td>
                        <td>Benefit</td>
                        <td class="amount">{{ number_format((float)($item['calculatedValue'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td><strong>Total Allowances & Benefits</strong></td>
                    <td></td>
                    <td class="amount"><strong>{{ number_format($summary['totalBenefits']['monthly'], 2) }}</strong></td>
                </tr>
            @endif

            <!-- Deductions -->
            @if(count($data['deductions']) > 0)
                <tr class="section-header">
                    <td colspan="3">DEDUCTIONS</td>
                </tr>
                @foreach($data['deductions'] as $item)
                    <tr>
                        <td>{{ $item['componentName'] ?? 'Unknown Component' }}</td>
                        <td>Deduction</td>
                        <td class="amount">{{ number_format((float)($item['calculatedValue'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td><strong>Total Deductions</strong></td>
                    <td></td>
                    <td class="amount"><strong>{{ number_format($summary['totalDeductions']['monthly'], 2) }}</strong></td>
                </tr>
            @endif

            <!-- Net Pay -->
            <tr class="net-pay">
                <td><strong>NET PAY</strong></td>
                <td></td>
                <td class="amount"><strong>₹ {{ number_format($summary['netSalary']['monthly'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <div style="display: table; width: 100%;">
            <div style="display: table-row;">
                <div style="display: table-cell; width: 50%; padding-right: 20px;">
                    <strong>Summary:</strong><br>
                    Gross Salary: ₹ {{ number_format($summary['totalGross']['monthly'], 2) }}<br>
                    Total Benefits: ₹ {{ number_format($summary['totalBenefits']['monthly'], 2) }}<br>
                    Total Deductions: ₹ {{ number_format($summary['totalDeductions']['monthly'], 2) }}<br>
                    <strong>Net Salary: ₹ {{ number_format($summary['netSalary']['monthly'], 2) }}</strong>
                </div>
                <div style="display: table-cell; width: 50%; text-align: center;">
                    <div style="border: 1px solid #333; padding: 15px; margin-top: 10px;">
                        <strong>Company Seal & Signature</strong>
                        <div style="height: 40px;"></div>
                        <div style="border-top: 1px solid #333; margin-top: 20px; padding-top: 5px;">
                            Authorized Signatory
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>This is a system generated salary slip and does not require a signature.</p>
        <p>Generated on: {{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>