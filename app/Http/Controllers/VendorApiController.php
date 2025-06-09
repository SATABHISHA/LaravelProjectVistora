<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vendor;

class VendorApiController extends Controller
{
    // Add vendor (no duplicate vendor_code for corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'vendor_code' => 'required|string',
            // ...add other required fields as needed
        ]);

        $exists = Vendor::where('corp_id', $request->corp_id)
            ->where('vendor_code', $request->vendor_code)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Vendor code already exists for this corp_id'], 409);
        }

        $vendor = Vendor::create($request->all());
        return response()->json(['message' => 'Vendor added', 'data' => $vendor], 201);
    }

    // Update vendor by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $vendor = Vendor::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }
        $vendor->update($request->all());
        return response()->json(['message' => 'Vendor updated', 'data' => $vendor]);
    }

    // Delete vendor by corp_id and id
    public function destroy($corp_id, $id)
    {
        $vendor = Vendor::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }
        $vendor->delete();
        return response()->json(['message' => 'Vendor deleted']);
    }

    // Fetch vendors by corp_id (response inside data)
    public function getByCorpId($corp_id)
    {
        $data = Vendor::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }
}
