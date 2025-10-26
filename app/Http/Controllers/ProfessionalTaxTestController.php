<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProfessionalTax;
use Illuminate\Support\Facades\Validator;

class ProfessionalTaxTestController extends Controller
{
    public function test()
    {
        return response()->json([
            'status' => true,
            'message' => 'Professional Tax Controller is working',
            'timestamp' => now()
        ]);
    }

    public function addSimple(Request $request)
    {
        try {
            $data = [
                'corpId' => $request->corpId ?? 'test',
                'companyName' => $request->companyName ?? 'Test Company',
                'state' => $request->state ?? 'Test State',
                'minIncome' => $request->minIncome ?: '0',
                'maxIncome' => $request->maxIncome ?: '0',
                'aboveIncome' => $request->aboveIncome ?: '0'
            ];

            $professionalTax = ProfessionalTax::create($data);

            return response()->json([
                'status' => true,
                'message' => 'Professional tax record created successfully',
                'data' => $professionalTax
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}