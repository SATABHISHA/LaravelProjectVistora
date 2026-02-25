<?php

namespace App\Http\Controllers;

use App\Models\OfferLetter;
use App\Models\OfferLetterTemplate;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class OfferLetterApiController extends Controller
{
    // -----------------------------------------------------------
    // List offer letters for a corp
    // -----------------------------------------------------------
    public function index($corp_id)
    {
        $letters = OfferLetter::with(['candidate', 'application.jobPosting'])
            ->where('corp_id', $corp_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $letters]);
    }

    // -----------------------------------------------------------
    // Show single offer letter
    // -----------------------------------------------------------
    public function show($corp_id, $id)
    {
        $letter = OfferLetter::with(['candidate', 'application.jobPosting', 'template'])
            ->where('corp_id', $corp_id)
            ->where('id', $id)
            ->first();

        if (!$letter) {
            return response()->json(['status' => false, 'message' => 'Offer letter not found.'], 404);
        }

        return response()->json(['status' => true, 'data' => $letter]);
    }

    // -----------------------------------------------------------
    // Generate (create) an offer letter for a selected candidate
    // -----------------------------------------------------------
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id'          => 'required|string',
            'application_id'   => 'required|integer|exists:recruitment_applications,id',
            'template_id'      => 'required|integer|exists:offer_letter_templates,id',
            'date_of_joining'  => 'required|date',
            'ctc_annual'       => 'required|numeric|min:0',
            'generated_by'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Load application with candidate
        $application = RecruitmentApplication::with('candidate', 'jobPosting')
            ->where('corp_id', $request->corp_id)
            ->where('id', $request->application_id)
            ->first();

        if (!$application) {
            return response()->json(['status' => false, 'message' => 'Application not found for this corp.'], 404);
        }

        if ($application->final_decision !== 'Selected') {
            return response()->json(['status' => false, 'message' => 'Offer can only be generated for Selected candidates.'], 422);
        }

        $template  = OfferLetterTemplate::findOrFail($request->template_id);
        $candidate = $application->candidate;

        // Safely decode salary_components (handles array or JSON string)
        $salaryComponents = $template->salary_components ?? [];
        if (is_string($salaryComponents)) {
            $salaryComponents = json_decode($salaryComponents, true) ?? [];
        }

        // Build salary breakdown from template salary_components
        $salaryBreakdown = $this->buildSalaryBreakdown(
            $salaryComponents,
            (float) $request->ctc_annual
        );

        // Generate offer reference number
        $refNo = 'OL-' . strtoupper($request->corp_id) . '-' . date('Ymd') . '-' . rand(1000, 9999);

        // Auto-fill candidate name from candidate record
        $candidateName = trim(($candidate->first_name ?? '') . ' ' . ($candidate->last_name ?? ''));

        // Merge additional fields from request
        $data = [
            'corp_id'          => $request->corp_id,
            'application_id'   => $application->id,
            'candidate_id'     => $candidate->id,
            'template_id'      => $template->id,
            'offer_reference_no'=> $refNo,
            'candidate_name'   => $request->candidate_name ?? $candidateName,
            'designation'      => $request->designation ?? $application->jobPosting?->designation,
            'department'       => $request->department  ?? $application->jobPosting?->department,
            'location'         => $request->location    ?? $application->jobPosting?->location,
            'date_of_joining'  => $request->date_of_joining,
            'ctc_annual'       => $request->ctc_annual,
            'salary_breakdown' => $salaryBreakdown,
            'status'           => 'Draft',
            'generated_by'     => $request->generated_by,
        ];

        $letter = OfferLetter::create($data);

        // Update application status
        $application->update(['status' => 'Offer Sent']);

        return response()->json([
            'status'  => true,
            'message' => 'Offer letter generated successfully.',
            'data'    => $letter,
        ], 201);
    }

    // -----------------------------------------------------------
    // Render & download offer letter as PDF
    // -----------------------------------------------------------
    public function downloadPdf($corp_id, $id)
    {
        $letter = OfferLetter::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $template  = OfferLetterTemplate::findOrFail($letter->template_id);
        $candidate = RecruitmentCandidate::findOrFail($letter->candidate_id);

        // Convert logo to base64 data URI for PDF embedding
        $logoBase64      = $this->fileToBase64($template->company_logo_path);
        $signatureBase64 = $this->fileToBase64($template->digital_signature_path);

        // Rebuild salary breakdown
        $salaryBreakdown = is_array($letter->salary_breakdown) ? $letter->salary_breakdown : [];

        // Attempt to get company name from company_details table if available
        $companyName = '';
        try {
            $company = \App\Models\CompanyDetails::where('corp_id', $corp_id)->first();
            $companyName = $company?->company_name ?? '';
        } catch (\Exception $e) {
            $companyName = '';
        }

        $offer = $letter; // Blade template uses $offer

        $pdf = Pdf::loadView('offer_letter.template', compact(
            'offer', 'template', 'candidate', 'logoBase64',
            'signatureBase64', 'salaryBreakdown', 'companyName'
        ))->setPaper('A4', 'portrait');

        // Save PDF
        $pdfPath = 'offer_letters/' . $corp_id . '/' . $letter->offer_reference_no . '.pdf';
        Storage::disk('public')->put($pdfPath, $pdf->output());
        $letter->update(['pdf_path' => $pdfPath, 'status' => 'Sent', 'sent_at' => now()]);

        return $pdf->download($letter->offer_reference_no . '.pdf');
    }

    // -----------------------------------------------------------
    // Preview offer letter HTML (for frontend preview)
    // -----------------------------------------------------------
    public function preview($corp_id, $id)
    {
        $letter    = OfferLetter::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();
        $template  = OfferLetterTemplate::findOrFail($letter->template_id);
        $candidate = RecruitmentCandidate::findOrFail($letter->candidate_id);

        $logoBase64      = $this->fileToBase64($template->company_logo_path);
        $signatureBase64 = $this->fileToBase64($template->digital_signature_path);
        $salaryBreakdown = is_array($letter->salary_breakdown) ? $letter->salary_breakdown : [];

        $companyName = '';
        try {
            $company     = \App\Models\CompanyDetails::where('corp_id', $corp_id)->first();
            $companyName = $company?->company_name ?? '';
        } catch (\Exception $e) {}

        $offer = $letter; // Blade template uses $offer

        $html = view('offer_letter.template', compact(
            'offer', 'template', 'candidate', 'logoBase64',
            'signatureBase64', 'salaryBreakdown', 'companyName'
        ))->render();

        return response()->json([
            'status' => true,
            'html'   => $html,
        ]);
    }

    // -----------------------------------------------------------
    // Update offer letter status  (Accepted / Declined / Revoked)
    // -----------------------------------------------------------
    public function updateStatus(Request $request, $corp_id, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Draft,Sent,Accepted,Declined,Revoked',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $letter = OfferLetter::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();

        $updateData = ['status' => $request->status];
        if (in_array($request->status, ['Accepted', 'Declined'])) {
            $updateData['responded_at'] = now();
            // Update application status too
            $appStatus = $request->status === 'Accepted' ? 'Joined' : 'Rejected';
            $letter->application?->update(['status' => $appStatus]);
        }

        $letter->update($updateData);
        return response()->json(['status' => true, 'message' => 'Offer letter status updated.', 'data' => $letter]);
    }

    // -----------------------------------------------------------
    // Delete offer letter
    // -----------------------------------------------------------
    public function destroy($corp_id, $id)
    {
        $letter = OfferLetter::where('corp_id', $corp_id)->where('id', $id)->firstOrFail();

        if ($letter->pdf_path) {
            Storage::disk('public')->delete($letter->pdf_path);
        }

        $letter->delete();
        return response()->json(['status' => true, 'message' => 'Offer letter deleted.']);
    }

    // -----------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------

    /**
     * Build salary breakdown array from template components and total CTC.
     */
    private function buildSalaryBreakdown(array $components, float $ctcAnnual): array
    {
        $breakdown = [];
        $remaining = $ctcAnnual;

        foreach ($components as $comp) {
            $name     = $comp['component'] ?? 'Component';
            $calcType = $comp['calc_type'] ?? 'percentage'; // percentage | fixed
            $value    = (float) ($comp['value'] ?? 0);

            if ($calcType === 'percentage') {
                $annual      = round($ctcAnnual * $value / 100, 2);
                $calcDisplay = $value . '% of CTC';
            } else {
                $annual      = $value * 12;
                $calcDisplay = 'Fixed ₹' . number_format($value, 2) . ' /month';
            }

            $monthly = round($annual / 12, 2);

            $breakdown[] = [
                'component'    => $name,
                'calc_type'    => $calcType,
                'calc_display' => $calcDisplay,
                'monthly'      => $monthly,
                'annual'       => $annual,
            ];
        }

        return $breakdown;
    }

    /**
     * Convert a stored file to a base64 data URI.
     */
    private function fileToBase64(?string $storagePath): ?string
    {
        if (!$storagePath || !Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $content  = Storage::disk('public')->get($storagePath);
        $mimeType = Storage::disk('public')->mimeType($storagePath);
        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }
}
