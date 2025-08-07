<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deal;

class UserDealApiController extends Controller
{
    public function store(Request $request)
    {
        // Validate input
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner'       => 'required|exists:users,id',
        ]);

        try {
            // Create deal
            $deal = Deal::create([
                'title'        => $request->input('title'),
                'descriptions' => $request->input('description'),
                'user_id'      => $request->input('owner'),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Deal created successfully.',
                'data'    => $deal,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to create deal: ' . $e->getMessage(),
            ], 500);
        }
    }
}
