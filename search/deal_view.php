<?php
// api.php

header('Content-Type: application/json');
include("connection.php");


// Paginate an array
function paginateArray($data, $page, $perPage) {
    $total = count($data);
    $offset = ($page - 1) * $perPage;
    $paginated = array_slice($data, $offset, $perPage);

    return [
        'data' => $paginated,
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'last_page' => ceil($total / $perPage),
    ];
}

// ======== GET POST DATA ONLY ========
$searchValue = isset($_POST['searchValue']) ? trim($_POST['searchValue']) : '';
$dealsPage   = isset($_POST['deals_page']) ? max(1, intval($_POST['deals_page'])) : 1;
$perPage     = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 10; // default 10
$hasSearch   = !empty($searchValue);

// ======== HELPER FUNCTIONS ========

// Calculate match score based on fields and search words
function calculateMatchScore($item, $fields, $searchValue) {
    $searchValue = strtolower(trim($searchValue));
    $searchWords = preg_split('/\s+/', $searchValue);
    $combinedValue = '';

    foreach ($fields as $field) {
        if (isset($item[$field])) {
            $combinedValue .= ' ' . strtolower($item[$field]);
        }
    }

    $combinedValue = trim($combinedValue);

    if (strpos($combinedValue, $searchValue) !== false) return 100;

    $foundWords = 0;
    foreach ($searchWords as $word) {
        if (strpos($combinedValue, $word) !== false) $foundWords++;
    }

    $totalWords = count($searchWords);

    if ($foundWords === $totalWords && $totalWords > 1) return 100;
    if ($foundWords > 0) return intval(50 * ($foundWords / $totalWords));

    return 0;
}



// ======== FETCH DEALS ========
try {
    $stmt = $conn->prepare("SELECT * FROM deals");
    $stmt->execute();
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate accuracy score for search
    foreach ($deals as &$deal) {
        $fields = ['title', 'descriptions', 'notes', 'tags', 'firm', 'company_name', 'status'];
        $deal['accuracy_score'] = $hasSearch ? calculateMatchScore($deal, $fields, $searchValue) : 0;
    }
    unset($deal);

    // Filter and sort
    if ($hasSearch) {
        $deals = array_filter($deals, fn($d) => $d['accuracy_score'] > 0);
        usort($deals, fn($a, $b) => $b['accuracy_score'] <=> $a['accuracy_score']);
    } else {
        usort($deals, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    }

    // Paginate
    $dealsPaginated = paginateArray($deals, $dealsPage, $perPage);

    // ======== RESPONSE ========
    $response = [
        'status' => true,
        'searchValue' => $searchValue,
        'total_results' => count($deals),
        'deals_paginated' => $dealsPaginated
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
