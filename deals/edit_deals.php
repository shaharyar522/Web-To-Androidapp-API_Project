<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deal;
use Illuminate\Support\Facades\Auth;

class UserDealApiController extends Controller
{
    // ✅ View Deals
    public function index(Request $request)
    {
        $query = Deal::with('ownerInfo')->whereNull('deleted_at');

        // Optional: filter by logged-in user
        if ($request->has('user_only') && $request->user_only == 1 && Auth::check()) {
            $query->where('user_id', Auth::id());
        }

        // Sorting
        if ($request->filled('sort')) {
            $sortOptions = [
                'latest' => ['id', 'desc'],
                'oldest' => ['id', 'asc'],
                'a_to_z' => ['title', 'asc'],
                'z_to_a' => ['title', 'desc'],
            ];
            if (isset($sortOptions[$request->sort])) {
                $query->orderBy(...$sortOptions[$request->sort]);
            }
        } else {
            $query->orderBy('id', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $deals = $query->paginate($perPage);

        return response()->json($deals);
    }

    // ✅ Post a Deal
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner'       => 'required|exists:users,id',
        ]);

        try {
            $deal = Deal::create([
                'title'        => $request->input('title'),
                'descriptions' => $request->input('description'),
                'user_id'      => $request->input('owner'),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Deal created successfully.',
                'data'    => $deal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to create deal: ' . $e->getMessage()
            ], 500);
        }
    }

    // ✅ Edit/Update Deal
    public function update(Request $request, $id)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner'       => 'required|exists:users,id',
        ]);

        $deal = Deal::find($id);

        if (!$deal) {
            return response()->json([
                'status'  => false,
                'message' => 'Deal not found.'
            ], 404);
        }

        try {
            $deal->title        = $request->input('title', $deal->title);
            $deal->descriptions = $request->input('description', $deal->descriptions);
            $deal->user_id      = $request->input('owner', $deal->user_id);
            $deal->save();

            return response()->json([
                'status'  => true,
                'message' => 'Deal updated successfully.',
                'data'    => $deal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to update deal: ' . $e->getMessage()
            ], 500);
        }
    }
}
