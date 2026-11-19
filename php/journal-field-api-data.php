<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/jwt-auth.php';

$token = QU_Jwt_Auth::bearer_token();
$auth = $token === null ? null : QU_Jwt_Auth::verify($token);

if ($auth === null) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Only GET requests are allowed'
    ]);
    exit;
}

if (!$con) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Authorization: identity comes from the verified token, never from the request.
$user_Id = (int) $auth['sub'];

if ($user_Id <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid userId'
    ]);
    exit;
}

try {

    $today = date('Y-m-d');


    $stmt = $con->prepare("
        SELECT *
        FROM user_active_pending_subscription
        WHERE user_id = ?
        ORDER BY id DESC
    ");

    if (!$stmt) {
        throw new Exception($con->error);
    }

    $stmt->bind_param("i", $user_Id);
    $stmt->execute();

    $result = $stmt->get_result();

    $userData = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();



    $sql = "
        SELECT
            id,
            current_user_id,
            product_id,
            order_id,
            user_data_value,
            service_name,
            item_id
        FROM tqdXh_quantum_upgrade
        WHERE current_user_id = ?
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        throw new Exception('Database prepare failed');
    }

    $stmt->bind_param("i", $user_Id);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Database execute failed');
    }

    $result = $stmt->get_result();
    $services_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


    // subscription_ids from userData
    $subscriptionIds = array_unique(array_column($userData, 'subscription_id'));

    //  order_id matches a subscription_id
    $services = array_values(array_filter($services_data, function ($services_data) use ($subscriptionIds) {
        return in_array($services_data['order_id'], $subscriptionIds);
    }));




    if (empty($services)) {
        http_response_code(200);

        echo json_encode([
            'success' => false,
            'code' => 602,
            'message' => 'No field found'
        ]);

        exit;
    }

    $journalStatusStmt = $con->prepare("
        SELECT
            COUNT(*) AS total_records,
            COALESCE(
                SUM(DATE(date_submit) = ?),
                0
            ) AS today_records
        FROM journal_answers
        WHERE user_id = ?
          AND item_id = ?
    ");

    if (!$journalStatusStmt) {
        throw new Exception('Journal status query prepare failed.');
    }

    $followedFields = [];
    $pendingFields = [];

    foreach ($services as &$service) {
        $values = !empty($service['user_data_value'])
            ? explode('~', $service['user_data_value'])
            : [];

        $fieldName = trim($values[0] ?? '');
        $service['user_values'] = $fieldName;

        $currentUserId = (int) $service['current_user_id'];
        $itemId = $service['item_id'];

        $journalStatusStmt->bind_param(
            'sis',
            $today,
            $currentUserId,
            $itemId
        );

        $totalRecords = 0;
        $todayRecords = 0;

        if ($journalStatusStmt->execute()) {
            $journalResult = $journalStatusStmt->get_result();
            if ($journalRow = $journalResult->fetch_assoc()) {
                $totalRecords = (int) ($journalRow['total_records'] ?? 0);
                $todayRecords = (int) ($journalRow['today_records'] ?? 0);
            }
            $journalResult->free();
        }



        if ($todayRecords > 0) {
            $status = 'Logged today';
            $statusKey = 'logged';
            $isLoggedToday = true;
        } elseif ($totalRecords > 0) {
            $status = 'Suggested';
            $statusKey = 'suggested';
            $isLoggedToday = false;
        } else {
            $status = 'Look back pending';
            $statusKey = 'pending';
            $isLoggedToday = false;
        }

        $service['id'] = (int) $service['id'];
        $service['current_user_id'] = $currentUserId;
        $service['product_id'] = (int) $service['product_id'];
        $service['order_id'] = (int) $service['order_id'];
        $service['item_id'] = $itemId;

        $service['status'] = $status;
        $service['status_key'] = $statusKey;
        $service['is_logged_today'] = $isLoggedToday;
        $service['total_records'] = $totalRecords;
        $service['today_records'] = $todayRecords;



        if ($statusKey === 'logged' || $statusKey === 'suggested') {
            $service['section'] = 'FIELDS YOU FOLLOW';
            $followedFields[] = $service;
        } else {
            $service['section'] = 'OTHER ACTIVE FIELDS';
            $pendingFields[] = $service;
        }
    }
    unset($service);

    $journalStatusStmt->close();

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Services fetched successfully',
        'today' => $today,
        'count' => count($services),
        'followed_count' => count($followedFields),
        'pending_count' => count($pendingFields),
        'data' => $services,
        'fields_you_follow' => $followedFields,
        'other_active_fields' => $pendingFields
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('journal-field-api-data.php: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error'
    ]);
}

$con->close();