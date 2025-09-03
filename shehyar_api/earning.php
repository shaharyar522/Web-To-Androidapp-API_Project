<?php
header('Content-Type: application/json');
require_once 'connection.php'; // Should set up $pdo

$response = [
    'status' => false,
    'message' => '',
    'table' => '',
    'pagination' => '',
    'data' => null
];

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Only POST requests allowed.';
    echo json_encode($response);
    exit;
}

// Simulate authenticated user (replace with actual session login system)
$user_id = 123;

// Get pagination and sorting params
$per_page = isset($_POST['par_page']) && is_numeric($_POST['par_page']) ? (int)$_POST['par_page'] : 10;
$page     = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
$offset   = ($page - 1) * $per_page;

$sort_data = $_POST['sort_data'] ?? 'latest';
$sortOptions = [
    'latest'     => ['column' => 'id', 'direction' => 'DESC'],
    'oldest'     => ['column' => 'id', 'direction' => 'ASC'],
    'ascending'  => ['column' => 'title', 'direction' => 'ASC'],
    'descending' => ['column' => 'title', 'direction' => 'DESC']
];
$sortColumn = $sortOptions[$sort_data]['column'] ?? 'id';
$sortDir    = $sortOptions[$sort_data]['direction'] ?? 'DESC';

try {
    // 1. Get total earnings
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM user_earnings WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $total_earnings = (float) $stmt->fetchColumn();

    // 2. Get total withdrawals
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $total_withdraw = (float) $stmt->fetchColumn();

    // 3. Get last completed withdrawal date
    $stmt = $pdo->prepare("
        SELECT created_at 
        FROM withdrawals 
        WHERE user_id = :user_id AND status = 'completed' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $user_id]);
    $last_withdraw = $stmt->fetchColumn();

    // 4. Get current user balance
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $current_balance = (float) ($stmt->fetchColumn() ?? 0);

    // 5. Get total records for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_earnings WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $total_records = (int) $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);

    // 6. Get paginated earnings data
    $sql = "SELECT * FROM user_earnings 
            WHERE user_id = :user_id 
            ORDER BY $sortColumn $sortDir 
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Create simple table HTML
    ob_start();
    if (count($earnings) > 0) {
        echo "<table border='1' cellpadding='6' cellspacing='0'>";
        echo "<tr><th>#</th><th>Amount</th><th>Description</th><th>Date</th></tr>";
        $index = $offset + 1;
        foreach ($earnings as $row) {
            echo "<tr>
                <td>{$index}</td>
                <td>\${$row['amount']}</td>
                <td>{$row['description']}</td>
                <td>{$row['created_at']}</td>
              </tr>";
            $index++;
        }
        echo "</table>";
    } else {
        echo "<p>No earnings found.</p>";
    }
    $response['table'] = ob_get_clean();

    // 8. Pagination HTML
    ob_start();
    if ($total_pages > 1) {
        echo "<div class='pagination'>";
        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($i === $page) ? "style='font-weight:bold'" : '';
            echo "<a href='#' class='page-link' data-page='{$i}' {$active}>{$i}</a> ";
        }
        echo "</div>";
    }
    $response['pagination'] = ob_get_clean();

    // 9. Summary data
    $response['data'] = [
        'total_earnings'   => $total_earnings,
        'total_withdraw'   => $total_withdraw,
        'last_withdraw_on' => $last_withdraw,
        'current_balance'  => $current_balance
    ];

    $response['status'] = true;
    $response['message'] = 'Earnings data fetched successfully.';

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
