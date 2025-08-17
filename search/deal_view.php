<?php
// deal_view.php — POST-only, mirrors your Laravel SearchController -> Deal section exactly
header('Content-Type: application/json');
include("connection.php"); // must provide $conn = new PDO(...)

// -------------------- Only allow POST --------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => false,
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

// -------------------- Read POST params --------------------
function post_param(array $keys, $default = null) {
    foreach ($keys as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') {
            return trim($_POST[$k]);
        }
    }
    return $default;
}

$searchValue = post_param(['search_value', 'searchValue', 'q'], '');
$searchValueLower = strtolower($searchValue);
$hasSearch = $searchValue !== '';

$current_page = max(1, intval(post_param(['current_page', 'deals_page', 'page'], 1)));
$per_page = max(1, intval(post_param(['per_page', 'deal_par_page', 'perPage'], 10)));

// -------------------- Helpers --------------------
/** dot-notated getter for arrays (ownerInfo.first_name style) */
function data_get_dot(array $arr, string $path) {
    $segments = explode('.', $path);
    $cur = $arr;
    foreach ($segments as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) {
            $cur = $cur[$seg];
        } else {
            return null;
        }
    }
    return $cur;
}

/** calculateMatchScore as in your controller */
function calculateMatchScoreArray(array $item, array $fields, string $searchValue): int {
    $searchValue = strtolower(trim($searchValue));
    $searchWords = preg_split('/\s+/', $searchValue);

    $combinedValue = '';
    foreach ($fields as $field) {
        $value = data_get_dot($item, $field);
        if ($value !== null && $value !== '') {
            $combinedValue .= ' ' . strtolower((string)$value);
        }
    }
    $combinedValue = trim($combinedValue);

    if ($searchValue !== '' && strpos($combinedValue, $searchValue) !== false) {
        return 100;
    }

    $foundWords = 0;
    foreach ($searchWords as $word) {
        $word = trim($word);
        if ($word === '') continue;
        if (strpos($combinedValue, $word) !== false) {
            $foundWords++;
        }
    }

    $totalWords = count(array_filter($searchWords, fn($w) => trim($w) !== ''));
    if ($totalWords > 1 && $foundWords === $totalWords) return 100;
    if ($foundWords > 0 && $totalWords > 0) return intval(50 * ($foundWords / $totalWords));

    return 0;
}

/** paginate like LengthAwarePaginator over an array (controller does this after sorting) */
function paginateArray(array $data, int $page, int $perPage): array {
    $total   = count($data);
    $offset  = ($page - 1) * $perPage;
    $slice   = array_slice($data, $offset, $perPage);
    return [
        'data'         => array_values($slice),
        'total'        => $total,
        'per_page'     => $perPage,
         'page'     => $page,
        'last_page'    => (int) ceil($total / $perPage),
    ];
}

// -------------------- Main flow --------------------
try {
    // Build SQL using your exact table & column names
    $sql = "
        SELECT
            d.id,
            d.title,
            d.descriptions,
            d.notes,
            d.tags,
            d.photos,
            d.amount,
            d.firm,
            d.company_name,
            d.status,
            d.created_at,
            pa.title  AS practicearea_title,
            sp.title  AS speciality_title,
            ind.title AS industry_title,
            u.first_name AS owner_first_name,
            u.last_name  AS owner_last_name
        FROM deals d
        LEFT JOIN practice_areas pa ON pa.id = d.practice_area
        LEFT JOIN specialties sp    ON sp.id  = d.speciality
        LEFT JOIN industrys ind     ON ind.id = d.industry
        LEFT JOIN users u           ON u.id   = d.owner
        WHERE d.deleted_at IS NULL
    ";

    $params = [];
    if ($hasSearch) {
        // match controller's active where clauses
        $sql .= "
            AND (
                LOWER(d.title)          LIKE :q
                OR LOWER(d.descriptions) LIKE :q
                OR LOWER(d.notes)        LIKE :q
                OR LOWER(sp.title)       LIKE :q
            )
        ";
        $params[':q'] = '%' . $searchValueLower . '%';
    }

    // fetch all matching rows (controller fetches the collection and then scores/sorts in PHP)
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // map rows into the same shape controller expects (with nested relations)
    $deals = [];
    foreach ($rows as $r) {
        $deal = [
            'id'            => $r['id'],
            'title'         => $r['title'],
            'descriptions'  => $r['descriptions'],
            'notes'         => $r['notes'],
            'tags'          => $r['tags'],
            'photos'        => $r['photos'],
            'amount'        => $r['amount'],
            'firm'          => $r['firm'],
            'company_name'  => $r['company_name'],
            'status'        => $r['status'],
            'created_at'    => $r['created_at'],
            'ownerInfo'     => [
                'first_name' => $r['owner_first_name'],
                'last_name'  => $r['owner_last_name'],
            ],
            'practicearea'  => ['title' => $r['practicearea_title']],
            'specialityinfo'=> ['title' => $r['speciality_title']],
            'industryinfo'  => ['title' => $r['industry_title']],
        ];

        // compute accuracy_score using the exact fields list your controller uses
        $scoreFields = [
            'title',
            'descriptions',
            'notes',
            'tags',
            'photos',
            'amount',
            'firm',
            'company_name',
            'status',
            'ownerInfo.first_name',
            'ownerInfo.last_name',
            'practicearea.title',
            'specialityinfo.title',
            'industryinfo.title',
        ];
        $deal['accuracy_score'] = $hasSearch
            ? calculateMatchScoreArray($deal, $scoreFields, $searchValue)
            : 0;

        $deals[] = $deal;
    }

    // Filter & Sort exactly like controller
    if ($hasSearch) {
        // keep only accuracy_score > 0
        $deals = array_values(array_filter($deals, fn($d) => (int)$d['accuracy_score'] > 0));
        // sort by accuracy_score desc
        usort($deals, fn($a, $b) => $b['accuracy_score'] <=> $a['accuracy_score']);
    } else {
        // sort by created_at desc
        usort($deals, function ($a, $b) {
            $ta = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
            $tb = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
            return $tb <=> $ta;
        });
    }

    // Paginate AFTER sorting (just like LengthAwarePaginator on the collection)
    $dealsPaginated = paginateArray($deals, $current_page, $per_page);

    // Response: mirrors your controller's collection pagination shape
    echo json_encode([
        'status'         => true,
        'searchValue'    => $searchValue,
        'total_results'  => count($deals),
        'deals_paginated'=> $dealsPaginated,
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    exit;
}
