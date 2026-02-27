<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - {{ $payroll->empCode }}</title>
    <style>
        @page {
            margin: 15px;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            font-size: 12px;
            color: #333;
        }
        .container {
            border: 1px solid #ddd;
            padding: 15px;
        }
        .header, .section-title {
            text-align: center;
            padding: 5px;
            background-color: #000;
            color: #fff;
            font-weight: bold;
        }
        .header {
            margin-bottom: 10px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 15px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
        }
        .logo {
            position: absolute;
            top: 20px;
            left: 25px;
            max-height: 50px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-table td {
            padding: 4px 8px;
            border: 1px solid #ccc;
            width: 25%;
        }
        .info-table td:nth-child(odd) {
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .main-content {
            display: flex;
            width: 100%;
        }
        .earnings-section, .deductions-section {
            width: 50%;
            vertical-align: top;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th, .data-table td {
            padding: 4px 8px;
            border: 1px solid #ccc;
            text-align: left;
        }
        .data-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .data-table td:nth-child(n+2) {
            text-align: right;
        }
        .totals-row td {
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .summary-table td {
            padding: 6px 8px;
            border: 1px solid #ccc;
        }
        .summary-table td:first-child {
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-style: italic;
            font-size: 10px;
        }
        .cut-here {
            text-align: center;
            border-top: 1px dashed #999;
            padding-top: 5px;
        }
        .section-title-sm {
            background-color: #000;
            color: #fff;
            font-weight: bold;
            padding: 4px;
            text-align: center;
            margin-top: 10px;
            margin-bottom: 0;
        }
        .flex-container {
            display: -webkit-box;
            -webkit-box-orient: horizontal;
        }
        .flex-item {
            -webkit-box-flex: 1;
        }
        .flex-item:first-child {
            padding-right: 5px;
        }
        .flex-item:last-child {
            padding-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($company && $company->companyLogo)
            <img src="{{ $company->companyLogo }}" alt="Company Logo" class="logo">
        @endif
        <div class="company-info">
            <div class="company-name">{{ $company->companyName ?? $dynamicCompanyName ?? 'N/A' }}</div>
            @if($company)
                <div>{{ $company->addressLine1 ?? '' }}</div>
                @if($company->addressLine2)<div>{{ $company->addressLine2 }}</div>@endif
                <div>{{ $company->city ?? '' }}@if($company->pincode)-{{ $company->pincode }}@endif</div>
            @endif
        </div>
        <div class="header">
            PAYSLIP FOR {{ strtoupper($monthName) }} {{ $year }}
        </div>
        <table class="info-table">
            <tr>
                <td>Name</td>
                <td>{{ $employee->FirstName ?? '' }} {{ $employee->MiddleName ?? '' }} {{ $employee->LastName ?? '' }}</td>
                <td>PAN</td>
                <td>{{ $statutory->panNo ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Employee Code</td>
                <td>{{ $employee->EmpCode ?? 'N/A' }}</td>
                <td>Sex</td>
                <td>{{ $employee->Gender ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Designation</td>
                <td>{{ $employment->Designation ?? 'N/A' }}</td>
                <td>Account Number</td>
                <td>{{ $bank->bankAccNo ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Location</td>
                <td>{{ $employment->SubBranch ?? 'N/A' }}</td>
                <td>PF Account Number</td>
                <td>{{ $statutory->pfNo ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Joining Date</td>
                <td>{{ $employment->dateOfJoining ? date('d/m/Y', strtotime($employment->dateOfJoining)) : 'N/A' }}</td>
                <td>PF UAN</td>
                <td>{{ $statutory->uanNo ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Leaving Date</td>
                <td>{{ $employment->dateOfLeaving ? date('d/m/Y', strtotime($employment->dateOfLeaving)) : '' }}</td>
                <td>ESI Number</td>
                <td>{{ $statutory->esiNo ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Tax Regime</td>
                <td>{{ $statutory->taxRegime ?? 'N/A' }}</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        </table>
        <table class="info-table">
            <tr>
                <td style="text-align:center;">PAY DAYS:</td>
                <td style="text-align:center;">ATTENDANCE ARREAR DAYS:</td>
                <td style="text-align:center;">INCREMENT ARREAR DAYS:</td>
            </tr>
            <tr>
                <td style="text-align:center;">{{ number_format($payDays, 2) }}</td>
                <td style="text-align:center;">0.00</td>
                <td style="text-align:center;">0.00</td>
            </tr>
        </table>
        <div class="flex-container">
            <div class="flex-item">
                <div class="section-title-sm">EARNINGS (INR)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>COMPONENTS</th>
                            <th>MONTHLY</th>
                            <th>ARREAR</th>
                            <th>TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($earnings as $item)
                        <tr>
                            <td>{{ $item['componentName'] }}</td>
                            <td>{{ number_format($item['calculatedValue'], 2) }}</td>
                            <td>0.00</td>
                            <td>{{ number_format($item['calculatedValue'], 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="totals-row">
                            <td>TOTAL EARNINGS</td>
                            <td>{{ number_format($totalEarnings, 2) }}</td>
                            <td>0.00</td>
                            <td>{{ number_format($totalEarnings, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex-item">
                <div class="section-title-sm">DEDUCTIONS (INR)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>COMPONENTS</th>
                            <th>TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deductions as $item)
                        <tr>
                            <td>{{ $item['componentName'] }}</td>
                            <td>{{ number_format($item['calculatedValue'], 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="totals-row">
                            <td>TOTAL DEDUCTIONS</td>
                            <td>{{ number_format($totalDeductions, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="section-title-sm">CONTRIBUTION</div>
        <table class="data-table">
            <tbody>
                <tr>
                    <td style="font-weight:bold;">Employer PF</td>
                    <td style="text-align:right;">{{-- Employer PF logic needed --}}</td>
                </tr>
            </tbody>
        </table>
        <table class="summary-table">
            <tr>
                <td>NET PAY (INR)</td>
                <td>{{ number_format($netPay, 2) }}</td>
            </tr>
            <tr>
                <td>NET PAY IN WORDS</td>
                <td>{{ $netPayInWords }}</td>
            </tr>
        </table>
        <div class="section-title-sm">LEAVE BALANCE</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>LEAVE TYPE</th>
                    <th>OPENING BALANCE</th>
                    <th>AVAILED LEAVE</th>
                    <th>CLOSING BALANCE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
        <div class="cut-here">Cut Here ....................................................................................................................................................................................................</div>
    </div>
</body>
</html>
