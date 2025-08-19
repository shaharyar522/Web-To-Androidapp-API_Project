<?php
// view_all.php
// Single-file custom PHP API that mirrors your SearchController behavior.
// Usage: POST only. Request params (all optional):
//   search_value
//   all_par_page (for merged items per page)
//   post_par_page, posts_page
//   people_par_page, peoples_page
//   referrals_par_page, referrals_page
//   job_par_page, jobs_page
//   deal_par_page, deals_page
function appendWithType($rows, $type, $limit = 5) {
    $result = [];
    $count = 0;
    foreach ($rows as $row) {
        if ($count >= $limit) break;
        $row['type'] = $type;
        $result[] = $row;
        $count++;
    }
    return $result;
}

header("Content-Type: application/json; charset=utf-8");

// ----- DB connection (edit if needed) -----
include('connection.php');

// ----- Only POST -----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit();
}

// ----- Read inputs -----
$searchValue = strtolower(trim($_POST['search_value'] ?? ''));
$hasSearch = $searchValue !== '';

$perPage = isset($_POST['all_par_page']) ? (int)$_POST['all_par_page'] : 25;
$post_par_page = isset($_POST['post_par_page']) ? (int)$_POST['post_par_page'] : 9;
$people_par_page = isset($_POST['people_par_page']) ? (int)$_POST['people_par_page'] : 9;
$referrals_par_page = isset($_POST['referrals_par_page']) ? (int)$_POST['referrals_par_page'] : 9;
$job_par_page = isset($_POST['job_par_page']) ? (int)$_POST['job_par_page'] : 9;
$deal_par_page = isset($_POST['deal_par_page']) ? (int)$_POST['deal_par_page'] : 9;

$pages = [
    'posts_page' => max(1, (int)($_POST['posts_page'] ?? 1)),
    'peoples_page' => max(1, (int)($_POST['peoples_page'] ?? 1)),
    'referrals_page' => max(1, (int)($_POST['referrals_page'] ?? 1)),
    'jobs_page' => max(1, (int)($_POST['jobs_page'] ?? 1)),
    'deals_page' => max(1, (int)($_POST['deals_page'] ?? 1)),
];

// ----- Helper: safe table names for some relations (adjust if your DB differs) -----
$cityTable = 'citys';               // from your City model
$industryTable = 'industrys';       // from your Industry model
$practiceAreaTable = 'practice_areas'; // assumed; adjust if different
$specialityTable = 'specialities';  // assumed; adjust if different
$boostsTable = 'boosts';
$usersTable = 'users';

// ----- Accuracy scoring function that mirrors controller.calculateMatchScore -----
function calculateMatchScoreCombined(array $item, array $fields, string $searchValue): int {
    $searchValue = strtolower(trim($searchValue));
    $searchWords = preg_split('/\s+/', $searchValue, -1, PREG_SPLIT_NO_EMPTY);
    $combinedValue = '';
    foreach ($fields as $field) {
        // field may be dot notation like ownerInfo.first_name
        $value = $item;
        foreach (explode('.', $field) as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                $value = null;
                break;
            }
        }
        if ($value !== null && $value !== '') {
            $combinedValue .= ' ' . strtolower((string)$value);
        }
    }
    $combinedValue = trim($combinedValue);
    if ($combinedValue === '') return 0;

    if (strpos($combinedValue, $searchValue) !== false) {
        return 100;
    }

    $foundWords = 0;
    foreach ($searchWords as $word) {
        if ($word !== '' && strpos($combinedValue, $word) !== false) {
            $foundWords++;
        }
    }
    $totalWords = max(1, count($searchWords));
    if ($foundWords === $totalWords && $totalWords > 1) {
        return 100;
    }
    if ($foundWords > 0) {
        return intval(50 * ($foundWords / $totalWords));
    }
    return 0;
}

// ----- Helper: fetch user row by id and attach ownerInfo.usercity if possible -----
function attachUserRelations(PDO $conn, array $row, $userIdField = 'owner', $usersTable = 'users', $cityTable = 'citys') {
    if (empty($row[$userIdField])) {
        $row['ownerInfo'] = null;
        return $row;
    }
    $ownerId = $row[$userIdField];
    // fetch user basic
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, city FROM {$usersTable} WHERE id = ? LIMIT 1");
    $stmt->execute([$ownerId]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$owner) {
        $row['ownerInfo'] = null;
        return $row;
    }
    // attach city if present
    if (!empty($owner['city'])) {
        $stmt = $conn->prepare("SELECT id, name FROM {$cityTable} WHERE id = ? LIMIT 1");
        try {
            $stmt->execute([$owner['city']]);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $city = null;
        }
        $owner['usercity'] = $city ?: null;
    } else {
        $owner['usercity'] = null;
    }
    $row['ownerInfo'] = $owner;

    // attach boosts for user (if any). Controller sometimes expects boost at post-level for owner -> so attach any boost records for user
    $bstmt = $conn->prepare("SELECT id, user_id, keywords FROM {$GLOBALS['boostsTable']} WHERE user_id = ? LIMIT 1");
    try {
        $bstmt->execute([$ownerId]);
        $boost = $bstmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $boost = null;
    }
    $row['boost'] = $boost ?: null;

    return $row;
}

// ----- Helper: attach morph boost for a product (Feed/EQJobs) if exists -----
// Try to find a boost where product_id = item id and model = 'Feed' or 'EQJobs' OR where type like 'Job' etc.
// This mimics Eloquent morphOne(...)->where('type','Job') style but is permissive.
function attachProductBoost(PDO $conn, array $row, $modelName, $productField = 'id') {
    $id = $row[$productField] ?? null;
    if (empty($id)) {
        $row['boost'] = null;
        return $row;
    }
    $sql = "SELECT id, product_id, model, keywords, type, user_id, start_date, end_date, status FROM {$GLOBALS['boostsTable']} 
            WHERE product_id = ? AND LOWER(model) = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    try {
        $stmt->execute([$id, strtolower($modelName)]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $b = false;
    }
    $row['boost'] = $b ?: null;
    return $row;
}

// ----- Generic fetch & attach function for a SELECT SQL (no LIMIT) -----
// $columnsFields: array of search fields names used for scoring (dot notation allowed)
function fetchAndAttach(PDO $conn, string $selectSql, array $params, array $attachInstructions, array $searchFields, string $searchValue, bool $hasSearch) {
    // execute main select
    $stmt = $conn->prepare($selectSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // attach relations based on $attachInstructions
    foreach ($rows as &$r) {
        // ownerInfo by default
        if (!empty($attachInstructions['owner']) && isset($r[$attachInstructions['owner']])) {
            $r = attachUserRelations($conn, $r, $attachInstructions['owner'], $GLOBALS['usersTable'], $GLOBALS['cityTable']);
        } elseif (!empty($attachInstructions['owner_none'])) {
            // no owner column; skip
        } else {
            // ensure consistent shape
            $r['ownerInfo'] = null;
        }

        // If need to attach product-level boost (for feed/job)
        if (!empty($attachInstructions['product_boost_model'])) {
            $r = attachProductBoost($conn, $r, $attachInstructions['product_boost_model'], $attachInstructions['product_id_field'] ?? 'id');
        }

        // attach other relations: city (for eq_jobs job_city), industry (by id), referred (user), practicearea, speciality
        if (!empty($attachInstructions['job_city_field']) && !empty($r[$attachInstructions['job_city_field']])) {
            $jc = $r[$attachInstructions['job_city_field']];
            $s = $conn->prepare("SELECT id, name FROM {$GLOBALS['cityTable']} WHERE id = ? LIMIT 1");
            try { $s->execute([$jc]); $r['city'] = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $r['city'] = null; }
        }

        if (!empty($attachInstructions['industry_field']) && !empty($r[$attachInstructions['industry_field']])) {
            $ind = $r[$attachInstructions['industry_field']];
            $s = $conn->prepare("SELECT id, title FROM {$GLOBALS['industryTable']} WHERE id = ? LIMIT 1");
            try { $s->execute([$ind]); $r['industryInfo'] = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $r['industryInfo'] = null; }
        }

        if (!empty($attachInstructions['referred_field']) && !empty($r[$attachInstructions['referred_field']])) {
            $refId = $r[$attachInstructions['referred_field']];
            $s = $conn->prepare("SELECT id, first_name, last_name, email FROM {$GLOBALS['usersTable']} WHERE id = ? LIMIT 1");
            try { $s->execute([$refId]); $r['referred'] = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $r['referred'] = null; }
        }

        if (!empty($attachInstructions['practicearea_field']) && !empty($r[$attachInstructions['practicearea_field']])) {
            $pa = $r[$attachInstructions['practicearea_field']];
            $s = $conn->prepare("SELECT id, title FROM {$GLOBALS['practiceAreaTable']} WHERE id = ? LIMIT 1");
            try { $s->execute([$pa]); $r['practicearea'] = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $r['practicearea'] = null; }
        }

        if (!empty($attachInstructions['speciality_field']) && !empty($r[$attachInstructions['speciality_field']])) {
            $sp = $r[$attachInstructions['speciality_field']];
            $s = $conn->prepare("SELECT id, title FROM {$GLOBALS['specialityTable']} WHERE id = ? LIMIT 1");
            try { $s->execute([$sp]); $r['specialityinfo'] = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $r['specialityinfo'] = null; }
        }

        // attach ownerInfo.usercity already handled by attachUserRelations above
    }
    unset($r);

    // compute accuracy score for each row
    foreach ($rows as &$r2) {
        $r2['accuracy_score'] = $hasSearch ? calculateMatchScoreCombined($r2, $searchFields, $searchValue) : 0;
    }
    unset($r2);

    return $rows;
}

// ----- Section: POSTS (feeds) -----
// SELECT feeds (controller used Feed::with(['ownerInfo','ownerInfo.usercity','boost']))
try {
    $postsSql = "SELECT id, title, descriptions, tags, photos, owner, created_at FROM feeds WHERE deleted_at IS NULL";
    $postsParams = [];
    if ($hasSearch) { 
        $postsSql .= " AND (
            LOWER(title) LIKE :search OR
            LOWER(descriptions) LIKE :search OR
            LOWER(tags) LIKE :search OR
            LOWER(photos) LIKE :search
        )";
        $postsParams[':search'] = "%{$searchValue}%";
    }
    $posts = fetchAndAttach($conn, $postsSql, $postsParams, [
        'owner' => 'owner',
        'product_boost_model' => 'Feed', // also attach product-level boost if present (morph)
        'product_id_field' => 'id'
    ], [
        'title','descriptions','tags','photos','ownerInfo.first_name','ownerInfo.last_name','ownerInfo.usercity.name'
    ], $searchValue, $hasSearch);

    // filter & sort
    if ($hasSearch) {
        $posts = array_filter($posts, fn($p) => ($p['accuracy_score'] ?? 0) > 0);
        usort($posts, fn($a,$b) => ($b['accuracy_score'] ?? 0) <=> ($a['accuracy_score'] ?? 0));
    } else {
        usort($posts, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    }


// ===== Process photos =====
foreach ($posts as &$post) {
    if (!empty($post['photos'])) {
        $photos = json_decode($post['photos'], true); // decode JSON
        if (is_array($photos) && count($photos) > 0) {
            // Ensure each photo file exists, else fallback to default.jpg
            $photos = array_map(function($p) {
                $fullPath = __DIR__ . "/profile/" . $p;
                if(file_exists($fullPath) && !empty($p)) {
                    return $GLOBALS['jobimagepath'] . $p;
                } else {
                    return $GLOBALS['jobimagepath'] . 'default.jpg';
                }
            }, $photos);
        } else {
            $photos = [$GLOBALS['jobimagepath'] . 'default.jpg'];
        }
        $post['photos'] = $photos;
    } else {
        $post['photos'] = [$GLOBALS['jobimagepath'] . 'default.jpg'];
    }
}
unset($post);


    // paginate
    $total_posts = count($posts);
    $posts_offset = ($pages['posts_page'] - 1) * $post_par_page;
    $posts_slice = array_slice($posts, $posts_offset, $post_par_page);

    $posts_response = [
        "total_results" => $total_posts,
        "per_page" => $post_par_page,
        "current_page" => $pages['posts_page'],
        "last_page" => (int)ceil($total_posts / max(1, $post_par_page)),
        "from" => $total_posts > 0 ? $posts_offset + 1 : null,
        "to" => $posts_offset + count($posts_slice),
        "data" => array_values($posts_slice)
    ];
} catch (Exception $e) {
    echo json_encode(["status"=>false, "message"=>"Posts fetch error: ".$e->getMessage()]);
    exit();
}

// ----- Section: PEOPLES (users role_id = 3) -----
try {
    $peoplesSql = "SELECT id, first_name, last_name, email, city, industry, law_firm, image, address, intro, bio, created_at
               FROM users WHERE deleted_at IS NULL AND role_id = 3";
    $peoplesParams = [];
    if ($hasSearch) {
        $peoplesSql .= " AND (
            LOWER(law_firm) LIKE :ps OR LOWER(first_name) LIKE :ps OR LOWER(last_name) LIKE :ps
        )";
        $peoplesParams[':ps'] = "%{$searchValue}%";
    }
    $peoplesRaw = fetchAndAttach($conn, $peoplesSql, $peoplesParams, [
        // owner not used here; but attach usercity via attachUserRelations expects 'owner' key; we'll handle differently
        'owner_none' => true
    ], [
        'first_name','last_name','law_firm','image','address','intro','bio','usercity.name','industryinfo.title','boost.keywords'
    ], $searchValue, $hasSearch);

    // attach usercity / boost / industryinfo for each person properly (since fetchAndAttach didn't for users)
    foreach ($peoplesRaw as &$p) {
        // attach usercity (users.city -> citys)
        if (!empty($p['city'])) {
            $s = $conn->prepare("SELECT id, name FROM {$cityTable} WHERE id = ? LIMIT 1");
            try { $s->execute([$p['city']]); $p['usercity'] = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $p['usercity'] = null; }
        } else {
            $p['usercity'] = null;
        }
        // boost for user
        $b = $conn->prepare("SELECT id, user_id, keywords FROM {$boostsTable} WHERE user_id = ? LIMIT 1");
        try { $b->execute([$p['id']]); $p['boost'] = $b->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $p['boost'] = null; }

        // industryinfo if industry column exists
        if (isset($p['industry']) && $p['industry']) {
            $s = $conn->prepare("SELECT id, title FROM {$industryTable} WHERE id = ? LIMIT 1");
            try { $s->execute([$p['industry']]); $p['industryinfo'] = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (PDOException $e) { $p['industryinfo'] = null; }
        } else {
            $p['industryinfo'] = null;
        }
        // compute accuracy
        $p['accuracy_score'] = $hasSearch ? calculateMatchScoreCombined($p, [
            'first_name','last_name','law_firm','image','address','intro','bio','usercity.name','industryinfo.title','boost.keywords'
        ], $searchValue) : 0;
    }
    unset($p);

    if ($hasSearch) {
        $peoples = array_filter($peoplesRaw, fn($x) => ($x['accuracy_score'] ?? 0) > 0);
        usort($peoples, fn($a,$b) => ($b['accuracy_score'] ?? 0) <=> ($a['accuracy_score'] ?? 0));
    } else {
        usort($peoplesRaw, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
        $peoples = $peoplesRaw;
    }

    $total_peoples = count($peoples);
    $peoples_offset = ($pages['peoples_page'] - 1) * $people_par_page;
    $peoples_slice = array_slice($peoples, $peoples_offset, $people_par_page);

    $peoples_response = [
        "total_results" => $total_peoples,
        "per_page" => $people_par_page,
        "current_page" => $pages['peoples_page'],
        "last_page" => (int)ceil($total_peoples / max(1, $people_par_page)),
        "from" => $total_peoples > 0 ? $peoples_offset + 1 : null,
        "to" => $peoples_offset + count($peoples_slice),
        "data" => array_values($peoples_slice)
    ];
} catch (Exception $e) {
    echo json_encode(["status"=>false, "message"=>"Peoples fetch error: ".$e->getMessage()]);
    exit();
}

// ----- Section: JOBS (eq_jobs job_type = 'regular') -----
try {
    // include is_active = 1 to mimic EQJobs global scope
    $jobsSql = "SELECT id, title, descriptions, notes, firm, position, representative, job_city, job_state, owner, industry, speciality, practice_area, created_at
                FROM eq_jobs WHERE deleted_at IS NULL AND job_type = 'regular' AND is_active = 1";
    $jobsParams = [];
    if ($hasSearch) {
        $jobsSql .= " AND (
            LOWER(title) LIKE :js OR
            LOWER(descriptions) LIKE :js
        )";
        $jobsParams[':js'] = "%{$searchValue}%";
    }

    $jobs = fetchAndAttach($conn, $jobsSql, $jobsParams, [
        'owner' => 'owner',
        'job_city_field' => 'job_city',
        'industry_field' => 'industry',
        'practicearea_field' => 'practice_area',
        'speciality_field' => 'speciality',
        'product_boost_model' => 'EQJobs', // try to attach product boost by model name
        'product_id_field' => 'id',
        'referred_field' => 'referred_by'
    ], [
        'title','descriptions','notes','firm','position','representative','city.name','industryInfo.title','ownerInfo.first_name','ownerInfo.last_name','boost.keywords'
    ], $searchValue, $hasSearch);

    if ($hasSearch) {
        $jobs = array_filter($jobs, fn($j) => ($j['accuracy_score'] ?? 0) > 0);
        usort($jobs, fn($a,$b) => ($b['accuracy_score'] ?? 0) <=> ($a['accuracy_score'] ?? 0));
    } else {
        usort($jobs, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    }

    $total_jobs = count($jobs);
    $jobs_offset = ($pages['jobs_page'] - 1) * $job_par_page;
    $jobs_slice = array_slice($jobs, $jobs_offset, $job_par_page);

    $jobs_response = [
        "total_results" => $total_jobs,
        "per_page" => $job_par_page,
        "current_page" => $pages['jobs_page'],
        "last_page" => (int)ceil($total_jobs / max(1, $job_par_page)),
        "from" => $total_jobs > 0 ? $jobs_offset + 1 : null,
        "to" => $jobs_offset + count($jobs_slice),
        "data" => array_values($jobs_slice)
    ];
} catch (Exception $e) {
    echo json_encode(["status"=>false, "message"=>"Jobs fetch error: ".$e->getMessage()]);
    exit();
}

// ----- Section: REFERRALS (eq_jobs job_type = 'referral') -----
try {
    $refSql = "SELECT id, title, descriptions, notes, firm, position, representative, job_city, job_state, owner, industry, speciality, practice_area, referred_by, created_at
                FROM eq_jobs WHERE deleted_at IS NULL AND job_type = 'referral' AND is_active = 1";
    $refParams = [];
    if ($hasSearch) {
        $refSql .= " AND (
            LOWER(title) LIKE :rs OR
            LOWER(descriptions) LIKE :rs
        )";
        $refParams[':rs'] = "%{$searchValue}%";
    }

    $referrals = fetchAndAttach($conn, $refSql, $refParams, [
        'owner' => 'owner',
        'job_city_field' => 'job_city',
        'industry_field' => 'industry',
        'practicearea_field' => 'practice_area',
        'speciality_field' => 'speciality',
        'referred_field' => 'referred_by',
        'product_boost_model' => 'EQJobs',
        'product_id_field' => 'id'
    ], [
        'title','descriptions','notes','firm','position','representative','city.name','industryInfo.title','ownerInfo.first_name','ownerInfo.last_name','referred.first_name','referred.last_name','boost.keywords'
    ], $searchValue, $hasSearch);

    if ($hasSearch) {
        $referrals = array_filter($referrals, fn($r) => ($r['accuracy_score'] ?? 0) > 0);
        usort($referrals, fn($a,$b) => ($b['accuracy_score'] ?? 0) <=> ($a['accuracy_score'] ?? 0));
    } else {
        usort($referrals, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    }

    $total_ref = count($referrals);
    $ref_offset = ($pages['referrals_page'] - 1) * $referrals_par_page;
    $ref_slice = array_slice($referrals, $ref_offset, $referrals_par_page);

    $referrals_response = [
        "total_results" => $total_ref,
        "per_page" => $referrals_par_page,
        "current_page" => $pages['referrals_page'],
        "last_page" => (int)ceil($total_ref / max(1, $referrals_par_page)),
        "from" => $total_ref > 0 ? $ref_offset + 1 : null,
        "to" => $ref_offset + count($ref_slice),
        "data" => array_values($ref_slice)
    ];
} catch (Exception $e) {
    echo json_encode(["status"=>false, "message"=>"Referrals fetch error: ".$e->getMessage()]);
    exit();
}

// ----- Section: DEALS -----


try {
    $dealSql = "SELECT id, title, descriptions, notes, press_release_link, tags, photos, amount, owner, firm, posted_date, other_attorneys, client, industry, company_name, state, city, practice_area, speciality, status, created_at
                FROM deals WHERE deleted_at IS NULL";
    $dealParams = [];
    if ($hasSearch) {
        $dealSql .= " AND (
            LOWER(title) LIKE :ds OR
            LOWER(descriptions) LIKE :ds OR
            LOWER(notes) LIKE :ds
        )";
        $dealParams[':ds'] = "%{$searchValue}%";
    }

    $deals = fetchAndAttach($conn, $dealSql, $dealParams, [
        'owner' => 'owner',
        'industry_field' => 'industry',
        'practicearea_field' => 'practice_area',
        'speciality_field' => 'speciality',
        'product_boost_model' => null // controller didn't include boost for deals
    ], [
        'title','descriptions','notes','tags','photos','amount','firm','company_name','status','ownerInfo.first_name','ownerInfo.last_name','practicearea.title','specialityinfo.title','industryinfo.title'
    ], $searchValue, $hasSearch);

    if ($hasSearch) {
        $deals = array_filter($deals, fn($d) => ($d['accuracy_score'] ?? 0) > 0);
        usort($deals, fn($a,$b) => ($b['accuracy_score'] ?? 0) <=> ($a['accuracy_score'] ?? 0));
    } else {
        usort($deals, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    }

// ======= ADD GLOBAL IMAGE PATH HERE =======
foreach ($deals as &$deal) {
    if (!empty($deal['photos'])) {
        $deal['photos'] = $GLOBALS['jobimagepath'] . $deal['photos'];
    } else {
        $deal['photos'] = $GLOBALS['jobimagepath'] . 'default.jpg'; // optional fallback
    }
}
unset($deal);
// ==========================================

    $total_deals = count($deals);
    $deal_offset = ($pages['deals_page'] - 1) * $deal_par_page;
    $deal_slice = array_slice($deals, $deal_offset, $deal_par_page);

    $deals_response = [
        "total_results" => $total_deals,
        "per_page" => $deal_par_page,
        "current_page" => $pages['deals_page'],
        "last_page" => (int)ceil($total_deals / max(1, $deal_par_page)),
        "from" => $total_deals > 0 ? $deal_offset + 1 : null,
        "to" => $deal_offset + count($deal_slice),
        "data" => array_values($deal_slice)
    ];
} catch (Exception $e) {
    echo json_encode(["status"=>false, "message"=>"Deals fetch error: ".$e->getMessage()]);
    exit();
}

// ----- MERGED PAGINATION (take top 5 from each, then merge & paginate) -----
try {
    // Take up to first 5 entries of each collection (sorted already)
    $mergedData = [];
    $appendWithType = function($arr, $type) use (&$mergedData) {
        $take = array_slice($arr, 0, 5);
        foreach ($take as $item) {
            // preserve object as array; add type
            $item['type'] = $type;
            $mergedData[] = $item;
        }
    };

    appendWithType($posts, 'post');
    appendWithType($peoples, 'person');
    appendWithType($referrals, 'referral');
    appendWithType($jobs, 'job');
    appendWithType($deals, 'deal');

    // sort merged by created_at desc (if exists)
    usort($mergedData, function($a, $b) {
        $aTime = strtotime($a['created_at'] ?? '1970-01-01');
        $bTime = strtotime($b['created_at'] ?? '1970-01-01');
        return $bTime <=> $aTime;
    });

    $merged_total = count($mergedData);
    $merged_page = max(1, (int)($_POST['page'] ?? 1));
    $merged_offset = ($merged_page - 1) * max(1, $perPage);
    $merged_slice = array_slice($mergedData, $merged_offset, max(1, $perPage));

    $merged_response = [
        "total_results" => $merged_total,
        "per_page" => $perPage,
        "current_page" => $merged_page,
        "last_page" => (int)ceil($merged_total / max(1, $perPage)),
        "from" => $merged_total > 0 ? $merged_offset + 1 : null,
        "to" => $merged_offset + count($merged_slice),
        "data" => array_values($merged_slice)
    ];
} catch (Exception $e) {
    echo json_encode(["status"=>false, "message"=>"Merged fetch error: ".$e->getMessage()]);
    exit();
}

// ----- Final response structure (mirrors the controller flow) -----
$response = [
    "status" => true,
    "searchValue" => $searchValue,
    "merged" => $merged_response,
    "posts" => $posts_response,
    "peoples" => $peoples_response,
    "referrals" => $referrals_response,
    "jobs" => $jobs_response,
    "deals" => $deals_response,
    // total number of results across categories (controller used this string)
    "total_number_of_result" => (
        ($posts_response['total_results'] ?? 0) +
        ($peoples_response['total_results'] ?? 0) +
        ($referrals_response['total_results'] ?? 0) +
        ($jobs_response['total_results'] ?? 0) +
        ($deals_response['total_results'] ?? 0)
    ) . " RESULTS",
];

// Output JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
