<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentBank;

class PaymentBankApiController extends Controller
{
    // Add payment bank
    public function store(Request $request)
    {
        $request->validate([
            'corp_id' => 'required|string',
            'bank_name' => 'required|string',
            'account_no' => 'required|string',
            // Add other validation rules as needed
        ]);

        // Prevent duplicate account_no for the same corp_id
        $exists = PaymentBank::where('corp_id', $request->corp_id)
            ->where('account_no', $request->account_no)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Account number already exists for this corp_id'], 409);
        }

        $paymentBank = PaymentBank::create($request->all());

        return response()->json(['message' => 'Payment bank added successfully', 'payment_bank' => $paymentBank], 201);
    }

    // Fetch all payment banks by corp_id
    public function getByCorpId($corp_id)
    {
        $banks = PaymentBank::where('corp_id', $corp_id)->get();
        return response()->json(['data' => $banks]);
    }

    // Update payment bank by corp_id and id
    public function update(Request $request, $corp_id, $id)
    {
        $paymentBank = PaymentBank::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$paymentBank) {
            return response()->json(['message' => 'Payment bank not found'], 404);
        }

        // Prevent duplicate account_no for the same corp_id (if changed)
        if ($request->has('account_no')) {
            $exists = PaymentBank::where('corp_id', $corp_id)
                ->where('account_no', $request->account_no)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Account number already exists for this corp_id'], 409);
            }
        }

        $paymentBank->update($request->all());

        return response()->json(['message' => 'Payment bank updated successfully', 'payment_bank' => $paymentBank]);
    }

    // Delete payment bank by corp_id and id
    public function destroy($corp_id, $id)
    {
        $paymentBank = PaymentBank::where('corp_id', $corp_id)->where('id', $id)->first();

        if (!$paymentBank) {
            return response()->json(['message' => 'Payment bank not found'], 404);
        }

        $paymentBank->delete();

        return response()->json(['message' => 'Payment bank deleted successfully']);
    }
}
