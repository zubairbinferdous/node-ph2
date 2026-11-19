<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {

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
            'message' => 'Method Not Allowed'
        ]);
        exit;
    }

    // Authorization: identity comes from the verified token, never from the request.
    $user_id = (int) $auth['sub'];
    if (!$user_id || $user_id <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid user_id'
        ]);

        exit;
    }

    // old gift data — requires subscription check before returning
    $stmt = $con->prepare("
        SELECT *
        FROM tqdXh_quantum_upgrade
        WHERE current_user_id = ?
        AND (is_gift = 1 OR is_gift_1 = 1)
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $oldGifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // new gift data — returned directly, no subscription check needed
    $stmt = $con->prepare("
        SELECT *
        FROM tqdXh_quantum_upgrade
        WHERE current_user_id = ?
        AND is_gift = 2
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $newGifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


    // Subscription data — used only to validate old gifts
    $stmt = $con->prepare("
        SELECT subscription_id
        FROM user_active_pending_subscription
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $userSubscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt->close();


    // Filter old gifts against subscription records
    $matchedOldGifts = [];

    foreach ($oldGifts as $oldGift) {
        foreach ($userSubscriptions as $subscription) {
            if ($oldGift['order_id'] == $subscription['subscription_id']) {
                $matchedOldGifts[] = $oldGift;
                break;
            }
        }
    }

    // New gifts go directly; old gifts only if they passed the check
    $result = array_merge($newGifts, $matchedOldGifts);


    echo json_encode([
        'success' => true,
        'new-gift' => count($newGifts),
        'data' => $result
    ], JSON_PRETTY_PRINT);


} catch (Throwable $e) {

    error_log('giftdata.php: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error'
    ]);

    exit;
}