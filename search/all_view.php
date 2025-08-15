<?php
/**
 * POST-only Search API that mirrors your Laravel SearchController buckets:
 * - posts  (feeds)
 * - peoples (users, role_id = 3)
 * - referrals (eq_jobs.job_type='referral')
 * - jobs (eq_jobs.job_type='regular')
 * - deals (deals)
 *
 * Notes:
 * - Uses the same scoring spirit as your controller (100 on full match, otherwise word-ratio to 50).
 * - Does NOT assume columns that may not exist. It checks INFORMATION_SCHEMA first.
 * - “boosts” keyword search is included when the boosts table/columns are present.
 * - Separate pagination per bucket, plus a merged top section (5 from each), sorted by created_at desc.
 */

header('Content-Type: application/json');

// Enforce POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// DB connection (expects $conn = new PDO(...))
require_once __DIR__ . '/connection.php';

// ---------- Helpers ----------
function get_current_db(PDO $conn): string {
    return (string)$conn->query("SELECT DATABASE()")->fetchColumn();
}

function table_exists(PDO $conn, string $table): bool {
    $db = get_current_db($conn);
    $q = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1");
    $q->execute([$db, $table]);
    return (bool)$q->fetchColumn();
}

function column_exists(PDO $conn, string $table, string $column): bool {
    $db = get_current_db($conn);
    $q = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $q->execute([$db, $table, $column]);
    return (bool)$q->fetchColumn();
}

/**
 * Score like your controller:
 * - 100 if whole search string appears in any combined field text
 * - else 100 if ALL words (>=2) appear across combined fields
 * - else 50 * (#foundWords / #totalWords)
 * - else 0
 */
function score_like_controller(array $row, array $fields, string $search): int {
    $search = strtolower(trim($search));
    if ($search === '') return 0;

    $words = preg_split('/\s+/', $search);
    $combined = '';

    foreach ($fields as $f) {
        if (!array_key_exists($f, $row)) continue;
        $v = $row[$f];
        if ($v === null) continue;
        $combined .= ' ' . strtolower((string)$v);
    }
    $combined = trim($combined);

    if ($combined === '') return 0;

    // full-string match
    if (strpos($combined, $search) !== false) return 100;

    // word-based
    $totalWords = count($words);
    $found = 0;
    foreach ($words as $w) {
        if ($w === '') continue;
        if (strpos($combined, $w) !== false) $found++;
    }

    if ($totalWords > 1 && $found === $totalWords) return 100;
    if ($found > 0 && $totalWords > 0) return (int) floor(50 * ($found / $totalWords));

    return 0;
}

/**
 * Slice per page
 */
function paginate_array(array $items, int $page, int $perPage): array {
    $offset = max(0, ($page - 1) * $perPage);
    return array_slice($items, $offset, $perPage);
}

// ---------- Inputs ----------
$all_per_page       = isset($_POST['all_par_page']) ? (int)$_POST['all_par_page'] : 25;
$post_per_page      = isset($_POST['post_par_page']) ? (int)$_POST['post_par_page'] : 9;
$people_per_page    = isset($_POST['people_par_page']) ? (int)$_POST['people_par_page'] : 9;
$referrals_per_page = isset($_POST['referrals_par_page']) ? (int)$_POST['referrals_par_page'] : 9;
$jobs_per_page      = isset($_POST['job_par_page']) ? (int)$_POST['job_par_page'] : 9;
$deals_per_page     = isset($_POST['deal_par_page']) ? (int)$_POST['deal_par_page'] : 9;

$posts_page      = isset($_POST['posts_page']) ? (int)$_POST['posts_page'] : 1;
$peoples_page    = isset($_POST['peoples_page']) ? (int)$_POST['peoples_page'] : 1;
$referrals_page  = isset($_POST['referrals_page']) ? (int)$_POST['referrals_page'] : 1;
$jobs_page       = isset($_POST['jobs_page']) ? (int)$_POST['jobs_page'] : 1;
$deals_page      = isset($_POST['deals_page']) ? (int)$_POST['deals_page'] : 1;
$merged_page     = isset($_POST['page']) ? (int)$_POST['page'] : 1;

$searchValue = isset($_POST['search_value']) ? strtolower(trim((string)$_POST['search_value'])) : '';
$hasSearch   = $searchValue !== '';

// ---------- Optional resources ----------
$hasBoosts = table_exists($conn, 'boosts') && column_exists($conn, 'boosts', 'keywords') &&
             column_exists($conn, 'boosts', 'product_id') && column_exists($conn, 'boosts', 'model');

// Some tables may not have soft deletes—check each
$feeds_has_deleted     = column_exists($conn, 'feeds', 'deleted_at');
$users_has_deleted     = column_exists($conn, 'users', 'deleted_at');
$jobs_has_deleted      = table_exists($conn, 'eq_jobs') && column_exists($conn, 'eq_jobs', 'deleted_at');
$deals_has_deleted     = column_exists($conn, 'deals', 'deleted_at');

// ---------- 1) POSTS (feeds) ----------
$feeds = [];
if (table_exists($conn, 'feeds')) {
    $select = "SELECT f.*";
    if ($hasBoosts) {
        $select .= ", (SELECT b.keywords FROM boosts b WHERE b.product_id = f.id AND b.model = 'Feed' LIMIT 1) AS boost_keywords";
    }
    $sql = "$select FROM feeds f WHERE 1=1";
    if ($feeds_has_deleted) $sql .= " AND f.deleted_at IS NULL";

    $params = [];
    if ($hasSearch) {
        $sql .= " AND (LOWER(f.title) LIKE :s_feeds
                    OR LOWER(f.descriptions) LIKE :s_feeds
                    OR LOWER(f.tags) LIKE :s_feeds
                    OR LOWER(f.photos) LIKE :s_feeds";
        if ($hasBoosts) {
            $sql .= " OR EXISTS (
                        SELECT 1 FROM boosts b
                        WHERE b.product_id = f.id
                          AND b.model = 'Feed'
                          AND LOWER(b.keywords) LIKE :s_feeds
                      )";
        }
        $sql .= ")";
        $params[':s_feeds'] = "%{$searchValue}%";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // scoring fields similar to your controller list for posts
    $postScoreFields = ['title', 'descriptions', 'tags', 'photos'];
    if ($hasBoosts) $postScoreFields[] = 'boost_keywords';

    foreach ($feeds as &$r) {
        $r['accuracy_score'] = $hasSearch ? score_like_controller($r, $postScoreFields, $searchValue) : 0;
        $r['type'] = 'post';
    }
    unset($r);

    if ($hasSearch) {
        $feeds = array_values(array_filter($feeds, fn($x) => ($x['accuracy_score'] ?? 0) > 0));
        usort($feeds, fn($a,$b) => ($b['accuracy_score'] <=> $a['accuracy_score']));
    } else {
        // created_at desc if exists, else id desc
        if (!empty($feeds) && array_key_exists('created_at', $feeds[0])) {
            usort($feeds, fn($a,$b) => strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
        } else {
            usort($feeds, fn($a,$b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
        }
    }
}
$feeds_total = count($feeds);
$feeds_page_items = paginate_array($feeds, $posts_page, $post_per_page);

// ---------- 2) PEOPLES (users role_id=3) ----------
$peoples = [];
if (table_exists($conn, 'users') && column_exists($conn, 'users', 'role_id')) {
    $select = "SELECT u.*";
    if ($hasBoosts) {
        $select .= ", (SELECT b.keywords FROM boosts b WHERE b.product_id = u.id AND b.model = 'User' LIMIT 1) AS boost_keywords";
    }
    $sql = "$select FROM users u WHERE u.role_id = 3";
    if ($users_has_deleted) $sql .= " AND u.deleted_at IS NULL";
    $params = [];

    if ($hasSearch) {
        $sql .= " AND (LOWER(u.law_firm) LIKE :s_users
                    OR LOWER(u.first_name) LIKE :s_users
                    OR LOWER(u.last_name) LIKE :s_users";
        if ($hasBoosts) {
            $sql .= " OR EXISTS (
                        SELECT 1 FROM boosts b
                        WHERE b.product_id = u.id
                          AND b.model = 'User'
                          AND LOWER(b.keywords) LIKE :s_users
                      )";
        }
        $sql .= ")";
        $params[':s_users'] = "%{$searchValue}%";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $peoples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $peopleScoreFields = ['first_name', 'last_name', 'law_firm', 'image', 'address', 'intro', 'bio'];
    if ($hasBoosts) $peopleScoreFields[] = 'boost_keywords';

    foreach ($peoples as &$r) {
        $r['accuracy_score'] = $hasSearch ? score_like_controller($r, $peopleScoreFields, $searchValue) : 0;
        $r['type'] = 'person';
    }
    unset($r);

    if ($hasSearch) {
        $peoples = array_values(array_filter($peoples, fn($x) => ($x['accuracy_score'] ?? 0) > 0));
        usort($peoples, fn($a,$b) => ($b['accuracy_score'] <=> $a['accuracy_score']));
    } else {
        if (!empty($peoples) && array_key_exists('created_at', $peoples[0])) {
            usort($peoples, fn($a,$b) => strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
        } else {
            usort($peoples, fn($a,$b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
        }
    }
}
$peoples_total = count($peoples);
$peoples_page_items = paginate_array($peoples, $peoples_page, $people_per_page);

// ---------- 3) JOBS (eq_jobs, job_type=regular) ----------
$jobs = [];
if (table_exists($conn, 'eq_jobs')) {
    $select = "SELECT j.*";
    if ($hasBoosts) {
        $select .= ", (SELECT b.keywords FROM boosts b WHERE b.product_id = j.id AND b.model = 'EQJobs' LIMIT 1) AS boost_keywords";
    }
    $sql = "$select FROM eq_jobs j WHERE j.job_type = 'regular'";
    if ($jobs_has_deleted) $sql .= " AND j.deleted_at IS NULL";
    $params = [];

    if ($hasSearch) {
        // In your controller, most job fields in WHERE are commented out; you kept title + boost.
        $sql .= " AND (LOWER(j.title) LIKE :s_jobs";
        if ($hasBoosts) {
            $sql .= " OR EXISTS (
                        SELECT 1 FROM boosts b
                        WHERE b.product_id = j.id
                          AND b.model = 'EQJobs'
                          AND LOWER(b.keywords) LIKE :s_jobs
                      )";
        }
        $sql .= ")";
        $params[':s_jobs'] = "%{$searchValue}%";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $jobScoreFields = [
        'title','descriptions','notes','firm','position','representative',
        'job_city','job_state'
    ];
    if ($hasBoosts) $jobScoreFields[] = 'boost_keywords';

    foreach ($jobs as &$r) {
        $r['accuracy_score'] = $hasSearch ? score_like_controller($r, $jobScoreFields, $searchValue) : 0;
        $r['type'] = 'job';
    }
    unset($r);

    if ($hasSearch) {
        $jobs = array_values(array_filter($jobs, fn($x) => ($x['accuracy_score'] ?? 0) > 0));
        usort($jobs, fn($a,$b) => ($b['accuracy_score'] <=> $a['accuracy_score']));
    } else {
        if (!empty($jobs) && array_key_exists('created_at', $jobs[0])) {
            usort($jobs, fn($a,$b) => strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
        } else {
            usort($jobs, fn($a,$b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
        }
    }
}
$jobs_total = count($jobs);
$jobs_page_items = paginate_array($jobs, $jobs_page, $jobs_per_page);

// ---------- 4) REFERRALS (eq_jobs, job_type=referral) ----------
$referrals = [];
if (table_exists($conn, 'eq_jobs')) {
    $select = "SELECT j.*";
    if ($hasBoosts) {
        $select .= ", (SELECT b.keywords FROM boosts b WHERE b.product_id = j.id AND b.model = 'EQJobs' LIMIT 1) AS boost_keywords";
    }
    // Optional: get referred user's name if you store user id in `referred_by`
    $hasUsers = table_exists($conn, 'users') && column_exists($conn, 'users', 'first_name') && column_exists($conn, 'users', 'last_name');
    if ($hasUsers && column_exists($conn, 'eq_jobs', 'referred_by')) {
        $select .= ",
            (SELECT u.first_name FROM users u WHERE u.id = j.referred_by LIMIT 1) AS referred_first_name,
            (SELECT u.last_name  FROM users u WHERE u.id = j.referred_by LIMIT 1) AS referred_last_name";
    }

    $sql = "$select FROM eq_jobs j WHERE j.job_type = 'referral'";
    if ($jobs_has_deleted) $sql .= " AND j.deleted_at IS NULL";
    $params = [];

    if ($hasSearch) {
        $sql .= " AND (LOWER(j.title) LIKE :s_refs
                    OR LOWER(j.descriptions) LIKE :s_refs";
        if ($hasBoosts) {
            $sql .= " OR EXISTS (
                        SELECT 1 FROM boosts b
                        WHERE b.product_id = j.id
                          AND b.model = 'EQJobs'
                          AND LOWER(b.keywords) LIKE :s_refs
                      )";
        }
        $sql .= ")";
        $params[':s_refs'] = "%{$searchValue}%";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $refScoreFields = [
        'title','descriptions','notes','firm','position','representative'
    ];
    if ($hasUsers && column_exists($conn, 'eq_jobs', 'referred_by')) {
        $refScoreFields[] = 'referred_first_name';
        $refScoreFields[] = 'referred_last_name';
    }
    if ($hasBoosts) $refScoreFields[] = 'boost_keywords';

    foreach ($referrals as &$r) {
        $r['accuracy_score'] = $hasSearch ? score_like_controller($r, $refScoreFields, $searchValue) : 0;
        $r['type'] = 'referral';
    }
    unset($r);

    if ($hasSearch) {
        $referrals = array_values(array_filter($referrals, fn($x) => ($x['accuracy_score'] ?? 0) > 0));
        usort($referrals, fn($a,$b) => ($b['accuracy_score'] <=> $a['accuracy_score']));
    } else {
        if (!empty($referrals) && array_key_exists('created_at', $referrals[0])) {
            usort($referrals, fn($a,$b) => strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
        } else {
            usort($referrals, fn($a,$b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
        }
    }
}
$referrals_total = count($referrals);
$referrals_page_items = paginate_array($referrals, $referrals_page, $referrals_per_page);

// ---------- 5) DEALS (deals) ----------
$deals = [];
if (table_exists($conn, 'deals')) {
    $sql = "SELECT d.* FROM deals d WHERE 1=1";
    if ($deals_has_deleted) $sql .= " AND d.deleted_at IS NULL";
    $params = [];

    if ($hasSearch) {
        // Your controller searches title + descriptions + notes + (speciality.title). We include spec title if table/cols exist.
        $sql .= " AND (LOWER(d.title) LIKE :s_deals
                    OR LOWER(d.descriptions) LIKE :s_deals
                    OR LOWER(d.notes) LIKE :s_deals";

        $hasSpecTable = table_exists($conn, 'specialities') && column_exists($conn, 'specialities', 'title') &&
                        column_exists($conn, 'deals', 'speciality');
        if ($hasSpecTable) {
            $sql .= " OR EXISTS (
                        SELECT 1 FROM specialities s
                        WHERE s.id = d.speciality
                          AND LOWER(s.title) LIKE :s_deals
                    )";
        }
        $sql .= ")";
        $params[':s_deals'] = "%{$searchValue}%";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dealScoreFields = [
        'title','descriptions','notes','tags','photos','amount',
        'firm','company_name','status','client','city','state'
    ];

    foreach ($deals as &$r) {
        $r['accuracy_score'] = $hasSearch ? score_like_controller($r, $dealScoreFields, $searchValue) : 0;
        $r['type'] = 'deal';
    }
    unset($r);

    if ($hasSearch) {
        $deals = array_values(array_filter($deals, fn($x) => ($x['accuracy_score'] ?? 0) > 0));
        usort($deals, fn($a,$b) => ($b['accuracy_score'] <=> $a['accuracy_score']));
    } else {
        if (!empty($deals) && array_key_exists('created_at', $deals[0])) {
            usort($deals, fn($a,$b) => strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
        } else {
            usort($deals, fn($a,$b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
        }
    }
}
$deals_total = count($deals);
$deals_page_items = paginate_array($deals, $deals_page, $deals_per_page);

// ---------- MERGED (top 5 of each, then created_at desc) ----------
$merged_take = 5;
$merged = array_merge(
    array_map(function($x){ $x['type'] = 'post';     return $x; }, array_slice($feeds,     0, $merged_take)),
    array_map(function($x){ $x['type'] = 'person';   return $x; }, array_slice($peoples,   0, $merged_take)),
    array_map(function($x){ $x['type'] = 'referral'; return $x; }, array_slice($referrals, 0, $merged_take)),
    array_map(function($x){ $x['type'] = 'job';      return $x; }, array_slice($jobs,      0, $merged_take)),
    array_map(function($x){ $x['type'] = 'deal';     return $x; }, array_slice($deals,     0, $merged_take))
);
// Sort merged by created_at desc (fallback id desc)
usort($merged, function($a,$b){
    $a_ts = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $b_ts = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    if ($a_ts !== $b_ts) return $b_ts <=> $a_ts;
    return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
});
$merged_total = count($merged);
$merged_page_items = paginate_array($merged, $merged_page, $all_per_page);

// ---------- Response ----------
$total_number_of_result = $feeds_total + $peoples_total + $referrals_total + $jobs_total + $deals_total;

$response = [
    'status' => true,

    // keep order: posts, peoples, referrals, jobs, deals (plus merged)
    'posts' => [
        'page' => $posts_page,
        'per_page' => $post_per_page,
        'total' => $feeds_total,
        'data' => array_values($feeds_page_items),
    ],
    'peoples' => [
        'page' => $peoples_page,
        'per_page' => $people_per_page,
        'total' => $peoples_total,
        'data' => array_values($peoples_page_items),
    ],
    'referrals' => [
        'page' => $referrals_page,
        'per_page' => $referrals_per_page,
        'total' => $referrals_total,
        'data' => array_values($referrals_page_items),
    ],
    'jobs' => [
        'page' => $jobs_page,
        'per_page' => $jobs_per_page,
        'total' => $jobs_total,
        'data' => array_values($jobs_page_items),
    ],
    'deals' => [
        'page' => $deals_page,
        'per_page' => $deals_per_page,
        'total' => $deals_total,
        'data' => array_values($deals_page_items),
    ],

    // merged top section (optional but useful to mirror your view)
    'merged' => [
        'page' => $merged_page,
        'per_page' => $all_per_page,
        'total' => $merged_total,
        'data' => array_values($merged_page_items),
    ],

    'total_number_of_result' => $total_number_of_result . ' RESULTS',
    'search_value' => $searchValue,
];

echo json_encode($response);
