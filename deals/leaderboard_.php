// ✅ Leaderboard API (Filtered)
public function leaderboard(Request $request)
{
    $query = \App\Models\User::where("role_id", 3)
        ->whereNull("deleted_at")
        ->withCount(['deals as total_deals' => function ($q) use ($request) {
            if ($request->tag === 'today') {
                $q->whereDate('created_at', now()->toDateString());
            } elseif ($request->tag === 'week') {
                $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($request->tag === 'monthly') {
                $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            }
        }])
        ->orderByDesc('total_deals');

    $users = $query->get();

    return response()->json([
        'status' => true,
        'message' => 'Leaderboard data fetched successfully.',
        'data' => $users
    ]);
}
