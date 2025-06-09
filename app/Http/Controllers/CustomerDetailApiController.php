<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerDetail;

class CustomerDetailApiController extends Controller
{
    // Add customer (no duplicate cust_code for corp_id)
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'cust_code' => 'required|string',
            // ...add other required fields as needed
        ]);

        $exists = CustomerDetail::where('corp_id', $request->corp_id)
            ->where('cust_code', $request->cust_code)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Customer code already exists for this corp_id'], 409);
        }

        $customer = CustomerDetail::create($request->all());
        return response()->json(['message' => 'Customer added', 'data' => $customer], 201);
    }

    // Fetch customers by corp_id (response inside data)
    public function getByCorpId($corp_id)
    {
        $data = CustomerDetail::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $data]);
    }

    // Update customer by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $customer = CustomerDetail::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
        $customer->update($request->all());
        return response()->json(['message' => 'Customer updated', 'data' => $customer]);
    }

    // Delete customer by corp_id and id
    public function destroy($corp_id, $id)
    {
        $customer = CustomerDetail::where('corp_id', $corp_id)->where('id', $id)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
        $customer->delete();
        return response()->json(['message' => 'Customer deleted']);
    }
}
