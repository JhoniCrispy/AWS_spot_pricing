<?php
try{

    require __DIR__ . '/../../vendor/autoload.php';
    require __DIR__ . '/../database.php'; 
    
    $config = require __DIR__ . '/../config.php';

    $pdo = getPDOConnection($config['db']);
    
    // Simple router-like behavior
    $requestUri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    // CORS headers (for local dev)
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    
    if ($method === 'GET' && preg_match('/\/api\/spot-prices/', $requestUri)) {
        $stmt = $pdo->query("SELECT region, instance_type, product_description, spot_price, timestamp FROM spot_prices LIMIT 1000");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }
    
    if ($method === 'GET' && preg_match('/\/api\/steals/', $requestUri)) {
        // Example logic for "steals": 
        //   - compare spot_price to the median price for the same instance_type across regions, 
        //     and pick ones that are significantly lower (e.g., 25% below).
        
        $subQuery = "
            SELECT instance_type, AVG(spot_price) as avg_price
            FROM spot_prices
            GROUP BY instance_type
        ";
    
        $query = "
            SELECT s.region, s.instance_type, s.spot_price, s.timestamp
            FROM spot_prices s
            JOIN ($subQuery) avg ON s.instance_type = avg.instance_type
            WHERE s.spot_price < avg.avg_price * 0.75
            ORDER BY s.spot_price ASC
            LIMIT 100
        ";
    
        $stmt = $pdo->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }
    
    // If no route matched
    http_response_code(404);
    echo json_encode(["message" => "Not found"]);
} catch (Exception $e){
    echo $e;
}