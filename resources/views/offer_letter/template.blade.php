<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #222; }
        .page { padding: 40px 50px; }

        /* Header */
        .header-bar { border-bottom: 3px solid #1a3c5e; padding-bottom: 14px; margin-bottom: 20px; }
        .header-top  { display: table; width: 100%; }
        .logo-cell   { display: table-cell; width: 120px; vertical-align: middle; }
        .logo-cell img { max-width: 110px; max-height: 70px; }
        .company-cell{ display: table-cell; vertical-align: middle; padding-left: 16px; }
        .company-name{ font-size: 16pt; font-weight: bold; color: #1a3c5e; }
        .ref-date    { font-size: 9pt; color: #666; margin-top: 4px; }

        /* Title */
        .offer-title { text-align: center; font-size: 14pt; font-weight: bold;
                       color: #1a3c5e; letter-spacing: 1px;
                       margin: 18px 0 14px; text-transform: uppercase; }

        /* Content paragraphs */
        .section { margin-bottom: 12px; line-height: 1.7; }
        .label  { font-weight: bold; min-width: 160px; display: inline-block; }

        /* Salary table */
        table.salary { width: 100%; border-collapse: collapse; margin: 14px 0 8px; }
        table.salary th { background: #1a3c5e; color: #fff; padding: 7px 10px; text-align: left; font-size: 10pt; }
        table.salary td { padding: 6px 10px; border-bottom: 1px solid #dde; font-size: 10pt; }
        table.salary tr:nth-child(even) td { background: #f5f7fa; }
        table.salary tfoot td { font-weight: bold; background: #e8ecf1; }

        /* Signature block */
        .signature-block { margin-top: 36px; display: table; width: 100%; }
        .sig-left  { display: table-cell; width: 55%; vertical-align: bottom; }
        .sig-right { display: table-cell; width: 45%; text-align: center; vertical-align: bottom; }
        .sig-right img { max-width: 160px; max-height: 70px; display: block; margin: 0 auto 4px; }
        .sig-line  { border-top: 1.5px solid #555; padding-top: 4px; font-size: 9.5pt; }
        .notes { font-size: 9pt; color: #555; margin-top: 10px; font-style: italic; }

        /* Footer */
        .footer-rule { border-top: 2px solid #1a3c5e; margin-top: 30px; padding-top: 8px;
                        font-size: 8.5pt; color: #888; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    <!-------- HEADER -------->
    <div class="header-bar">
        <div class="header-top">
            <div class="logo-cell">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Company Logo">
                @endif
            </div>
            <div class="company-cell">
                <div class="company-name">{{ $companyName ?? '' }}</div>
                <div class="ref-date">
                    Ref: {{ $offer->offer_reference_no }}
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    Date: {{ now()->format('d M Y') }}
                </div>
            </div>
        </div>
    </div>

    <!-------- TITLE -------->
    <div class="offer-title">Offer of Employment</div>

    <!-------- HEADER CONTENT -------->
    @if($template->header_content)
        <div class="section">{!! nl2br(e($template->header_content)) !!}</div>
    @endif

    <!-------- CANDIDATE & POSITION DETAILS -------->
    <div class="section">
        <p><span class="label">Candidate Name  :</span> {{ $offer->candidate_name }}</p>
        <p><span class="label">Designation     :</span> {{ $offer->designation }}</p>
        <p><span class="label">Department      :</span> {{ $offer->department }}</p>
        <p><span class="label">Location        :</span> {{ $offer->location }}</p>
        <p><span class="label">Date of Joining :</span> {{ \Carbon\Carbon::parse($offer->date_of_joining)->format('d M Y') }}</p>
    </div>

    <!-------- BODY CONTENT (from template) -------->
    @if($template->body_content)
        <div class="section">{!! nl2br(e($template->body_content)) !!}</div>
    @endif

    <!-------- SALARY STRUCTURE -------->
    @if(!empty($salaryBreakdown))
    <div class="section">
        <strong>Compensation Structure (Annual CTC: {{ $template->salary_currency ?? 'INR' }}
        &nbsp;{{ number_format($offer->ctc_annual, 2) }})</strong>
        <table class="salary">
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Calculation</th>
                    <th>Monthly ({{ $template->salary_currency ?? 'INR' }})</th>
                    <th>Annual ({{ $template->salary_currency ?? 'INR' }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salaryBreakdown as $row)
                <tr>
                    <td>{{ $row['component'] }}</td>
                    <td>{{ $row['calc_display'] }}</td>
                    <td>{{ number_format($row['monthly'], 2) }}</td>
                    <td>{{ number_format($row['annual'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total CTC</td>
                    <td>{{ number_format($offer->ctc_annual / 12, 2) }}</td>
                    <td>{{ number_format($offer->ctc_annual, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        @if($template->salary_notes)
            <p class="notes">{{ $template->salary_notes }}</p>
        @endif
    </div>
    @endif

    <!-------- FOOTER CONTENT (T&C) -------->
    @if($template->footer_content)
        <div class="section">{!! nl2br(e($template->footer_content)) !!}</div>
    @endif

    <!-------- SIGNATURE BLOCK -------->
    <div class="signature-block">
        <div class="sig-left">
            <p>Accepted by:</p>
            <br><br>
            <div class="sig-line">
                {{ $offer->candidate_name }}<br>
                <span style="font-size:9pt; color:#666;">Candidate Signature &amp; Date</span>
            </div>
        </div>
        <div class="sig-right">
            @if($signatureBase64)
                <img src="{{ $signatureBase64 }}" alt="Authorized Signature">
            @endif
            <div class="sig-line">
                {{ $template->signatory_name ?? 'Authorized Signatory' }}<br>
                @if($template->signatory_designation)
                    <span style="font-size:9pt; color:#666;">{{ $template->signatory_designation }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-------- PAGE FOOTER -------->
    <div class="footer-rule">
        This is a confidential document. If you have received this in error, please notify us immediately.
    </div>

</div>
</body>
</html>
