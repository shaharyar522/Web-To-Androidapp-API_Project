<?php

// ✅ View Single Deal Details
public function show($id)
{
    $deal = Deal::with('ownerInfo')->whereNull('deleted_at')->find($id);

    if (!$deal) {
        return response()->json([
            'status'  => false,
            'message' => 'Deal not found.'
        ], 404);
    }

    return response()->json([
        'status'  => true,
        'message' => 'Deal details fetched successfully.',
        'data'    => $deal
    ]);
}

?>