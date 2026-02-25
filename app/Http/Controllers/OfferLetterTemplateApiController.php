<?php

namespace App\Http\Controllers;

use App\Models\OfferLetterTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OfferLetterTemplateApiController extends Controller
{
    // List all templates for a corp
    public function index($corp_id)
    {
        $templates = OfferLetterTemplate::where('corp_id', $corp_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $templates]);
    }

    // Show a single template
    public function show($corp_id, $id)
    {
        $template = OfferLetterTemplate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->first();

        if (!$template) {
            return response()->json(['status' => false, 'message' => 'Template not found.'], 404);
        }

        // Append public URLs for logo and signature
        $template->company_logo_url        = $template->company_logo_path
            ? Storage::disk('public')->url($template->company_logo_path) : null;
        $template->digital_signature_url   = $template->digital_signature_path
            ? Storage::disk('public')->url($template->digital_signature_path) : null;

        return response()->json(['status' => true, 'data' => $template]);
    }

    // Create template (with optional logo and signature files)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corp_id'           => 'required|string',
            'template_name'     => 'required|string|max:255',
            'company_logo'      => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
            'digital_signature' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
            'salary_components' => 'nullable|string',  // JSON string
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['company_logo', 'digital_signature']);

        if ($request->hasFile('company_logo')) {
            $data['company_logo_path'] = $request->file('company_logo')
                ->store('offer_letter_templates/' . $request->corp_id . '/logos', 'public');
        }

        if ($request->hasFile('digital_signature')) {
            $data['digital_signature_path'] = $request->file('digital_signature')
                ->store('offer_letter_templates/' . $request->corp_id . '/signatures', 'public');
        }

        $template = OfferLetterTemplate::create($data);
        return response()->json(['status' => true, 'message' => 'Template created.', 'data' => $template], 201);
    }

    // Update template
    public function update(Request $request, $corp_id, $id)
    {
        $template = OfferLetterTemplate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->except(['company_logo', 'digital_signature']);

        // Update company logo
        if ($request->hasFile('company_logo')) {
            if ($template->company_logo_path) {
                Storage::disk('public')->delete($template->company_logo_path);
            }
            $data['company_logo_path'] = $request->file('company_logo')
                ->store('offer_letter_templates/' . $corp_id . '/logos', 'public');
        }

        // Update digital signature
        if ($request->hasFile('digital_signature')) {
            if ($template->digital_signature_path) {
                Storage::disk('public')->delete($template->digital_signature_path);
            }
            $data['digital_signature_path'] = $request->file('digital_signature')
                ->store('offer_letter_templates/' . $corp_id . '/signatures', 'public');
        }

        $template->update($data);
        return response()->json(['status' => true, 'message' => 'Template updated.', 'data' => $template]);
    }

    // Delete template
    public function destroy($corp_id, $id)
    {
        $template = OfferLetterTemplate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        if ($template->company_logo_path) {
            Storage::disk('public')->delete($template->company_logo_path);
        }
        if ($template->digital_signature_path) {
            Storage::disk('public')->delete($template->digital_signature_path);
        }

        $template->delete();
        return response()->json(['status' => true, 'message' => 'Template deleted.']);
    }

    // Upload / replace only the company logo
    public function uploadLogo(Request $request, $corp_id, $id)
    {
        $validator = Validator::make($request->all(), [
            'company_logo' => 'required|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $template = OfferLetterTemplate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        if ($template->company_logo_path) {
            Storage::disk('public')->delete($template->company_logo_path);
        }

        $path = $request->file('company_logo')
            ->store('offer_letter_templates/' . $corp_id . '/logos', 'public');

        $template->update(['company_logo_path' => $path]);

        return response()->json([
            'status'  => true,
            'message' => 'Company logo uploaded.',
            'url'     => Storage::disk('public')->url($path),
        ]);
    }

    // Upload / replace only the digital signature
    public function uploadSignature(Request $request, $corp_id, $id)
    {
        $validator = Validator::make($request->all(), [
            'digital_signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $template = OfferLetterTemplate::where('corp_id', $corp_id)
            ->where('id', $id)
            ->firstOrFail();

        if ($template->digital_signature_path) {
            Storage::disk('public')->delete($template->digital_signature_path);
        }

        $path = $request->file('digital_signature')
            ->store('offer_letter_templates/' . $corp_id . '/signatures', 'public');

        $template->update(['digital_signature_path' => $path]);

        return response()->json([
            'status'  => true,
            'message' => 'Digital signature uploaded.',
            'url'     => Storage::disk('public')->url($path),
        ]);
    }
}
