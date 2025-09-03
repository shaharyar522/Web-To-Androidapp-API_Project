<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/PHP-Esqify-Project/config.php';

include 'connection.php';
header('Content-Type: application/json');


$parPage = isset($_GET['par_page']) ? intval($_GET['par_page']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $parPage;


function getPaginationData($conn, $countSql, $countParams = [], $countTypes = "", $parPage) {
    $countStmt = mysqli_prepare($conn, $countSql);
    if (!$countStmt) {
        return ['total' => 0, 'error' => 'Prepare failed (count): ' . mysqli_error($conn)];
    }

    if (!empty($countParams)) {
        $bind_names = [];
        $bind_names[] = $countTypes;
        for ($i = 0; $i < count($countParams); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $countParams[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$countStmt, 'bind_param'], $bind_names);
    }

    mysqli_stmt_execute($countStmt);
    mysqli_stmt_bind_result($countStmt, $total);
    mysqli_stmt_fetch($countStmt);
    mysqli_stmt_close($countStmt);

    $totalPages = $parPage > 0 ? ceil($total / $parPage) : 1;
    $noOfPages = range(1, $totalPages);

    return ['total' => $total, 'totalPages' => $totalPages, 'noOfPages' => $noOfPages];
}


$stateId = isset($_GET['state_for_search']) ? intval($_GET['state_for_search']) : 0;

if ($stateId > 0) {
    $countSql = "SELECT COUNT(*) FROM eq_jobs WHERE job_state = ?";
    $pagination = getPaginationData($conn, $countSql, [$stateId], "i", $parPage);
    if (isset($pagination['error'])) {
        echo json_encode(['success' => false, 'message' => $pagination['error'], 'data' => []]);
        exit;
    }

    $sql = "
        SELECT eq_jobs.*, states.name AS state_name
        FROM eq_jobs
        INNER JOIN states ON eq_jobs.job_state = states.id
        WHERE eq_jobs.job_state = ?
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (state): ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "iii", $stateId, $parPage, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $jobs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobs[] = $row;
    }

    echo json_encode([
        'success' => count($jobs) > 0,
        'filter' => 'state_for_search',
        'state_id' => $stateId,
        'total_jobs' => (int)$pagination['total'],
        'current_page' => $page,
        'par_page' => $parPage,
        'total_pages' => $pagination['totalPages'],
        'no_of_pages' => $pagination['noOfPages'],
        'data' => $jobs
    ]);
    mysqli_stmt_close($stmt);
    exit;
}


$industryId = isset($_GET['industry_for_search']) ? intval($_GET['industry_for_search']) : 0;

if ($industryId > 0) {
    $countSql = "SELECT COUNT(*) FROM eq_jobs WHERE industry = ?";
    $pagination = getPaginationData($conn, $countSql, [$industryId], "i", $parPage);
    if (isset($pagination['error'])) {
        echo json_encode(['success' => false, 'message' => $pagination['error'], 'data' => []]);
        exit;
    }

    $sql = "
        SELECT eq_jobs.*, industrys.title AS industry_name
        FROM eq_jobs
        INNER JOIN industrys ON eq_jobs.industry = industrys.id
        WHERE eq_jobs.industry = ?
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (industry): ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "iii", $industryId, $parPage, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $jobs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobs[] = $row;
    }

    echo json_encode([
        'success' => count($jobs) > 0,
        'filter' => 'industry_for_search',
        'industry_id' => $industryId,
        'total_jobs' => (int)$pagination['total'],
        'current_page' => $page,
        'par_page' => $parPage,
        'total_pages' => $pagination['totalPages'],
        'no_of_pages' => $pagination['noOfPages'],
        'data' => $jobs
    ]);
    mysqli_stmt_close($stmt);
    exit;
}


$bar = isset($_GET['bar_for_search']) ? trim($_GET['bar_for_search']) : '';

if (!empty($bar)) {
    $escapedBar = mysqli_real_escape_string($conn, $bar);

    $countSql = "SELECT COUNT(*) FROM eq_jobs INNER JOIN users ON users.id = eq_jobs.owner WHERE JSON_CONTAINS(users.bar, '\"$escapedBar\"')";
    $pagination = getPaginationData($conn, $countSql, [], "", $parPage);
    if (isset($pagination['error'])) {
        echo json_encode(['success' => false, 'message' => $pagination['error'], 'data' => []]);
        exit;
    }

    $sql = "
        SELECT eq_jobs.*
        FROM eq_jobs
        INNER JOIN users ON users.id = eq_jobs.owner
        WHERE JSON_CONTAINS(users.bar, '\"$escapedBar\"')
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (bar): ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ii", $parPage, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $jobs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobs[] = $row;
    }

    echo json_encode([
        'success' => count($jobs) > 0,
        'filter' => 'bar_for_search',
        'bar' => $bar,
        'total_jobs' => (int)$pagination['total'],
        'current_page' => $page,
        'par_page' => $parPage,
        'total_pages' => $pagination['totalPages'],
        'no_of_pages' => $pagination['noOfPages'],
        'data' => $jobs
    ]);
    mysqli_stmt_close($stmt);
    exit;
}


$practiceArea = isset($_GET['practice_area_for_search']) ? trim($_GET['practice_area_for_search']) : '';

if (!empty($practiceArea)) {
    $countSql = "SELECT COUNT(*) FROM eq_jobs WHERE practice_area = ?";
    $pagination = getPaginationData($conn, $countSql, [$practiceArea], "s", $parPage);
    if (isset($pagination['error'])) {
        echo json_encode(['success' => false, 'message' => $pagination['error'], 'data' => []]);
        exit;
    }

    $sql = "
        SELECT eq_jobs.*
        FROM eq_jobs
        WHERE practice_area = ?
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (practice area): ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "sii", $practiceArea, $parPage, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $jobs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobs[] = $row;
    }

    echo json_encode([
        'success' => count($jobs) > 0,
        'filter' => 'practice_area_for_search',
        'practice_area' => $practiceArea,
        'total_jobs' => (int)$pagination['total'],
        'current_page' => $page,
        'par_page' => $parPage,
        'total_pages' => $pagination['totalPages'],
        'no_of_pages' => $pagination['noOfPages'],
        'data' => $jobs
    ]);
    mysqli_stmt_close($stmt);
    exit;
}


$speciality = isset($_GET['speciality_for_search']) ? trim($_GET['speciality_for_search']) : '';

if (!empty($speciality)) {
    $countSql = "SELECT COUNT(*) FROM eq_jobs WHERE speciality = ?";
    $pagination = getPaginationData($conn, $countSql, [$speciality], "s", $parPage);
    if (isset($pagination['error'])) {
        echo json_encode(['success' => false, 'message' => $pagination['error'], 'data' => []]);
        exit;
    }

    $sql = "
        SELECT eq_jobs.*
        FROM eq_jobs
        WHERE speciality = ?
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (speciality): ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "sii", $speciality, $parPage, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $jobs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobs[] = $row;
    }

    echo json_encode([
        'success' => count($jobs) > 0,
        'filter' => 'speciality_for_search',
        'speciality' => $speciality,
        'total_jobs' => (int)$pagination['total'],
        'current_page' => $page,
        'par_page' => $parPage,
        'total_pages' => $pagination['totalPages'],
        'no_of_pages' => $pagination['noOfPages'],
        'data' => $jobs
    ]);
    mysqli_stmt_close($stmt);
    exit;
}


if (
    $stateId === 0 &&
    $industryId === 0 &&
    empty($bar) &&
    empty($practiceArea) &&
    empty($speciality)
) {
    $countSql = "SELECT COUNT(*) FROM eq_jobs";
    $pagination = getPaginationData($conn, $countSql, [], "", $parPage);
    if (isset($pagination['error'])) {
        echo json_encode(['success' => false, 'message' => $pagination['error'], 'data' => []]);
        exit;
    }

    $sql = "
        SELECT eq_jobs.*, states.name AS state_name, industrys.title AS industry_name
        FROM eq_jobs
        LEFT JOIN states ON eq_jobs.job_state = states.id
        LEFT JOIN industrys ON eq_jobs.industry = industrys.id
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed (fetch all): ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ii", $parPage, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $jobs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobs[] = $row;
    }

    echo json_encode([
        'success' => count($jobs) > 0,
        'filter' => 'none',
        'total_jobs' => (int)$pagination['total'],
        'current_page' => $page,
        'par_page' => $parPage,
        'total_pages' => $pagination['totalPages'],
        'no_of_pages' => $pagination['noOfPages'],
        'data' => $jobs
    ]);
    mysqli_stmt_close($stmt);
    exit;
}


echo json_encode([
    'success' => false,
    'message' => 'No valid filter provided.',
    'data' => []
]);
exit;
