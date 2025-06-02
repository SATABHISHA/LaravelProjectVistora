<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompanyDetails;
use Illuminate\Support\Facades\Storage;

class CompanyDetailsApiController extends Controller
{
    // Register company details
    public function register(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'company_name' => 'required|string',
            'company_logo' => 'nullable|file|mimes:jpg,jpeg,png,gif,svg|max:2048',
            'registered_address' => 'required|string',
            'pin' => 'required|string',
            'country' => 'required|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'phone' => 'required|string',
            'fax' => 'nullable|string',
            'currency' => 'required|string',
            'contact_person' => 'required|string',
            'industry' => 'required|string',
            'signatory_name' => 'required|string',
            'gstin' => 'required|string',
            'fcbk_url' => 'nullable|string',
            'youtube_url' => 'nullable|string',
            'twiter_url' => 'nullable|string',
            'insta_url' => 'nullable|string',
            'active_yn' => 'boolean'
        ]);

        $data = $request->all();

        if ($request->hasFile('company_logo')) {
            $file = $request->file('company_logo');
            $path = $file->store('company_logos', 'public');
            $data['company_logo'] = $path;
        }

        $company = CompanyDetails::create($data);

        return response()->json(['message' => 'Company registered successfully', 'company' => $company], 201);
    }

    // Fetch company details by corp_id
    public function show($corp_id)
    {
        $data = \App\Models\CompanyDetails::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }

    // Update company details by company_id and corp_id
    public function update(Request $request, $company_id, $corp_id)
    {
        $company = CompanyDetails::where('company_id', $company_id)
            ->where('corp_id', $corp_id)
            ->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $request->validate([
            'company_name' => 'sometimes|required|string',
            'company_logo' => 'nullable|file|mimes:jpg,jpeg,png,gif,svg|max:2048',
            'registered_address' => 'sometimes|required|string',
            'pin' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'phone' => 'sometimes|required|string',
            'fax' => 'nullable|string',
            'currency' => 'sometimes|required|string',
            'contact_person' => 'sometimes|required|string',
            'industry' => 'sometimes|required|string',
            'signatory_name' => 'sometimes|required|string',
            'gstin' => 'sometimes|required|string',
            'fcbk_url' => 'nullable|string',
            'youtube_url' => 'nullable|string',
            'twiter_url' => 'nullable|string',
            'insta_url' => 'nullable|string',
            'active_yn' => 'boolean'
        ]);

        $data = $request->all();

        if ($request->hasFile('company_logo')) {
            $file = $request->file('company_logo');
            $path = $file->store('company_logos', 'public');
            $data['company_logo'] = $path;
        }

        $company->update($data);

        return response()->json(['message' => 'Company updated successfully', 'company' => $company]);
    }

    // Delete company details by company_id and corp_id
    public function destroy($company_id, $corp_id)
    {
        $company = CompanyDetails::where('company_id', $company_id)
            ->where('corp_id', $corp_id)
            ->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Delete the logo file if it exists
        if ($company->company_logo && Storage::disk('public')->exists($company->company_logo)) {
            Storage::disk('public')->delete($company->company_logo);
        }

        $company->delete();

        return response()->json(['message' => 'Company deleted successfully']);
    }
}
