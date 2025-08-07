<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deal;
use Illuminate\Support\Facades\Auth;

class UserDealApiController extends Controller
{
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
}
