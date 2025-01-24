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

    if ($method === 'GET' && preg_match('/\/api\/spot-prices\/metadata/', $requestUri)) {
        try {
            // Fetch unique regions
            $regionQuery = "SELECT DISTINCT region FROM spot_prices ORDER BY region";
            $regionStmt = $pdo->query($regionQuery);
            $regions = $regionStmt->fetchAll(PDO::FETCH_COLUMN);
    
            // Fetch unique product descriptions
            $productQuery = "SELECT DISTINCT product_description FROM spot_prices ORDER BY product_description";
            $productStmt = $pdo->query($productQuery);
            $productDescriptions = $productStmt->fetchAll(PDO::FETCH_COLUMN);
    
            // Fetch price range
            $priceQuery = "SELECT MIN(spot_price) as min_price, MAX(spot_price) as max_price FROM spot_prices";
            $priceStmt = $pdo->query($priceQuery);
            $priceRange = $priceStmt->fetch(PDO::FETCH_ASSOC);
    
            $metadata = [
                'regions' => $regions,
                'product_descriptions' => $productDescriptions,
                'price_range' => $priceRange
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
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = ($page - 1) * $limit;
    
        $sortColumn = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'timestamp';
        $sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';
        
        $filterConditions = [];
        $params = [];
    
        // Add region filter
        if (isset($_GET['region'])) {
            $filterConditions[] = "region = :region";
            $params[':region'] = $_GET['region'];
        }
    
        // Add product description filter
        if (isset($_GET['product_description'])) {
            $filterConditions[] = "product_description = :product_description";
            $params[':product_description'] = $_GET['product_description'];
        }
    
        // Add price range filter
        $filterConditions[] = "spot_price BETWEEN :min_price AND :max_price";
        $params[':min_price'] = $_GET['min_price'];
        $params[':max_price'] = $_GET['max_price'];
    
        // Construct base query
        $query = "SELECT region, instance_type, product_description, spot_price, timestamp FROM spot_prices";
        
        // Add WHERE clause if filters exist
        if (!empty($filterConditions)) {
            $query .= " WHERE " . implode(' AND ', $filterConditions);
        }
    
        // Add sorting
        $query .= " ORDER BY $sortColumn $sortOrder";
    
        // Add pagination
        $query .= " LIMIT $limit OFFSET $offset";
    
        // Total count query
        $countQuery = "SELECT COUNT(*) FROM spot_prices" . 
                      (!empty($filterConditions) ? " WHERE " . implode(' AND ', $filterConditions) : '');
    
        try {
            // Execute main query
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Execute count query
            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();
    
            // Prepare response
            $response = [
                'data' => $rows,
                'pagination' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ];
    
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
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