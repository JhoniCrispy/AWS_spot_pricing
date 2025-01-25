<?php
try {
    require __DIR__ . '/../../vendor/autoload.php';
    require __DIR__ . '/../database.php';

    $config = require __DIR__ . '/../config.php';

    $pdo = getPDOConnection($config['db']);

    $requestUri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");

    if ($method === 'GET' && preg_match('/\/api\/spot-prices\/metadata/', $requestUri)) {
        try {
            $metadata = [
                'regions' => getRegions($pdo),
                'product_descriptions' => getProductDescriptions($pdo),
                'price_range' => getPriceRange($pdo)
            ];

            echo json_encode($metadata);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($method === 'GET' && preg_match('/\/api\/spot-prices/', $requestUri)) {
        $sortColumn = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'timestamp';
        $sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';
    
        $params = [
            'min_price' => isset($_GET['min_price']) ? $_GET['min_price'] : 0, 
            'max_price' => isset($_GET['max_price']) ? $_GET['max_price'] : PHP_INT_MAX
        ];
        if (isset($_GET['region'])) {
            $params['region'] = $_GET['region'];
        }
        if (isset($_GET['product_description'])) {
            $params['product_description'] = $_GET['product_description'];
        }
    
        try {
            $rows = getSpotPrices($pdo, $params, null, null, $sortColumn, $sortOrder);
            
            $response = [
                'data' => $rows
            ];
    
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($method === 'GET' && preg_match('/\/api\/steals\/meta/', $requestUri)) {
        try {
            $metaData = getStealsMetaData($pdo);
            echo json_encode($metaData);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($method === 'GET' && preg_match('/\/api\/steals/', $requestUri)) {
        try {
            // Extract optional filters from query parameters
            $filters = [];
            if (isset($_GET['region'])) {
                $filters['region'] = $_GET['region'];
            }
            if (isset($_GET['product_description'])) {
                $filters['product_description'] = $_GET['product_description'];
            }
            if (isset($_GET['steal_type'])) {
                $filters['steal_type'] = $_GET['steal_type'];
            }

            $steals = getSteals($pdo, $filters);
            echo json_encode($steals);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(["message" => "Not found"]);

} catch (Exception $e) {
    echo $e;
}
