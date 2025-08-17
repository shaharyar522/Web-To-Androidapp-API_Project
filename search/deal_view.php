<?php
// deal_view.php  — custom PHP version of your controller's Deal flow

header('Content-Type: application/json');
include("connection.php"); // must provide $conn = new PDO(...)

// -------------------- Inputs --------------------
$searchValue = isset($_POST['searchValue']) ? trim($_POST['searchValue']) : '';
$dealsPage   = isset($_POST['deals_page']) ? max(1, intval($_POST['deals_page'])) : 1;
$perPage     = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 10;
$hasSearch   = $searchValue !== '';
$searchValueLower = strtolower($searchValue);

// -------------------- Helpers --------------------
/** Laravel-like data_get for arrays with dot notation */
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

/** Same accuracy score logic as your controller */
function calculateMatchScoreArray(array $item, array $fields, string $searchValue): int {
    $searchValue = strtolower(trim($searchValue));
    $searchWords = preg_split('/\s+/', $searchValue);

    $combinedValue = '';
    foreach ($fields as $field) {
        $value = data_get_dot($item, $field);
        if ($value !== null && $value !== '') {
            $combinedValue .= ' ' . strtolower($value);
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

    $totalWords = count(array_filter($searchWords, fn($w) => $w !== ''));
    if ($totalWords > 1 && $foundWords === $totalWords) return 100;
    if ($foundWords > 0 && $totalWords > 0) return intval(50 * ($foundWords / $totalWords));

    return 0;
}

/** Paginate an array after sorting (same overall flow as controller) */
function paginateArray(array $data, int $page, int $perPage): array {
    $total   = count($data);
    $offset  = ($page - 1) * $perPage;
    $slice   = array_slice($data, $offset, $perPage);
    return [
        'data'         => array_values($slice),
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $page,
        'last_page'    => (int) ceil($total / $perPage),
    ];
}

try {
    // -------------------- Build base SQL like your Eloquent with() + whereNull --------------------
    // NOTE: change FK names if needed (practicearea_id, speciality_id, industry_id, user_id)
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
        LEFT JOIN practiceareas pa ON pa.id = d.practicearea_id
        LEFT JOIN specialities sp  ON sp.id  = d.speciality_id
        LEFT JOIN industries ind   ON ind.id = d.industry_id
        LEFT JOIN users u          ON u.id   = d.user_id
        WHERE d.deleted_at IS NULL
    ";

    $params = [];
    if ($hasSearch) {
        // mirror controller search:
        // where title/descriptions/notes OR specialityinfo.title matches (lower)
        $sql .= "
            AND (
                LOWER(d.title)        LIKE :q
                OR LOWER(d.descriptions) LIKE :q
                OR LOWER(d.notes)        LIKE :q
                OR LOWER(sp.title)       LIKE :q
            )
        ";
        $params[':q'] = '%' . $searchValueLower . '%';
    }

    // Fetch ALL matching deals first (controller fetches then sorts/paginates in PHP)
    // No ORDER BY here; we'll sort in PHP like your controller
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------- Transform rows to match controller shape (nested relations) --------------------
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

            // relations as nested arrays to mirror Eloquent access (practicearea.title etc.)
            'ownerInfo'     => [
                'first_name' => $r['owner_first_name'],
                'last_name'  => $r['owner_last_name'],
            ],
            'practicearea'  => [
                'title' => $r['practicearea_title'],
            ],
            'specialityinfo'=> [
                'title' => $r['speciality_title'],
            ],
            'industryinfo'  => [
                'title' => $r['industry_title'],
            ],
        ];

        // accuracy_score like controller (fields list below is identical)
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

    // -------------------- Filter & Sort (exactly like your controller) --------------------
    if ($hasSearch) {
        // keep only > 0
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

    // -------------------- Paginate AFTER sorting (same as LengthAwarePaginator over a collection) --------------------
    $dealsPaginated = paginateArray($deals, $dealsPage, $perPage);

    // -------------------- Response --------------------
    echo json_encode([
        'status'         => true,
        'searchValue'    => $searchValue,
        'total_results'  => count($deals),         // total after filtering (same as your controller's count())
        'deals_paginated'=> $dealsPaginated,       // contains data, total, per_page, current_page, last_page
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
