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

    /*
    |--------------------------------------------------------------------------
    | Allow Only GET
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

        http_response_code(405);

        echo json_encode([
            'success' => false,
            'message' => 'Method Not Allowed'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate item_id
    |--------------------------------------------------------------------------
    */

    $user_id = (int) $auth['sub'];

    if (!$user_id || $user_id <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid user_id'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Service
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            id,
            current_user_id,
            product_id,
            order_id,
            current_hawkins_value,
            my_timezone,
            hawkins_booster,
            booster_duration,
            booster_time,
            booster_value,
            day_time_start,
            day_time_end,
            day_time_value,
            night_time_start,
            night_time_end,
            night_time_value,
            special_frequency,
            frequency_type,
            frequency_day,
            frequency_night,
            user_data_key,
            user_data_value,
            current_value_min,
            current_value_max,
            booster_value_min,
            booster_value_max,
            status,
            service_name,
            item_id,
            night_status,
            share_with,
            service_start,
            is_gift,
            is_gift_1
        FROM tqdXh_quantum_upgrade
        WHERE current_user_id = ?
        AND is_gift = 0
        AND is_gift_1 = 0
        ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        throw new Exception('Database prepare failed');
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception('Database execute failed');
    }

    $result = $stmt->get_result();

    $services = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    if (empty($services)) {

        echo json_encode([
            'success' => false,
            'code' => 602,
            'message' => 'No field found'
        ]);

        exit;
    }

    foreach ($services as &$service) {

        $service['user_keys'] = !empty($service['user_data_key'])
            ? explode('~', $service['user_data_key'])
            : [];

        $service['user_values'] = !empty($service['user_data_value'])
            ? explode('~', $service['user_data_value'])
            : [];
    }

    unset($service);

    http_response_code(200);


    $stmt = $con->prepare("
        SELECT *
        FROM user_active_pending_subscription
        WHERE user_id = ?
        ORDER BY id DESC
    ");

    if (!$stmt) {
        throw new Exception($con->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $userData = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();



    // subscription_ids from userData
    $subscriptionIds = array_unique(array_column($userData, 'subscription_id'));

    //  order_id matches a subscription_id
    $filteredServices = array_values(array_filter($services, function ($service) use ($subscriptionIds) {
        return in_array($service['order_id'], $subscriptionIds);
    }));

    echo json_encode([
        'success' => true,
        'message' => 'Services fetched successfully',
        'userData' => $userData,
        'data' => $filteredServices,
    ]);


} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'error' => $e->getMessage()
    ]);
}

