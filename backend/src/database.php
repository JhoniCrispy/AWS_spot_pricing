<?php
function getPDOConnection($config)
{
    try {
        // Connect to MySQL without specifying the database to check its existence
        $dsnWithoutDb = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
        $pdo = new PDO($dsnWithoutDb, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Check if the database exists
        $dbName = $config['database'];
        $stmt = $pdo->query("SHOW DATABASES LIKE '$dbName'");
        $databaseExists = $stmt->fetch();

        // If the database does not exist, create it
        if (!$databaseExists) {
            $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET {$config['charset']} COLLATE {$config['collation']}");
            echo "Database '$dbName' created successfully.\n";
        }


        // Now connect to the newly created or existing database
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }

}
function getRegions($pdo)
{
    $query = "SELECT DISTINCT region FROM spot_prices ORDER BY region";
    $stmt = $pdo->query($query);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getProductDescriptions($pdo)
{
    $query = "SELECT DISTINCT product_description FROM spot_prices ORDER BY product_description";
    $stmt = $pdo->query($query);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getPriceRange($pdo)
{
    $query = "SELECT MIN(spot_price) as min_price, MAX(spot_price) as max_price FROM spot_prices";
    $stmt = $pdo->query($query);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function getSpotPrices($pdo, $params, $sortColumn, $sortOrder)
{
    $allowedSortColumns = ['region', 'instance_type', 'product_description', 'spot_price', 'timestamp'];
    $allowedSortOrder = ['ASC', 'DESC'];

    // Prevent SQL injection by whitelisting allowed sort columns and order
    $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'timestamp';
    $sortOrder = in_array(strtoupper($sortOrder), $allowedSortOrder) ? strtoupper($sortOrder) : 'DESC';

    $filterConditions = [];

    if (isset($params['region'])) {
        $filterConditions[] = "region = :region";
    }

    if (isset($params['product_description'])) {
        $filterConditions[] = "product_description = :product_description";
    }

    $filterConditions[] = "spot_price BETWEEN :min_price AND :max_price";

    // Updated query without pagination
    $query = "
        SELECT region, instance_type, product_description, spot_price, timestamp 
        FROM latest_spot_prices
    ";

    if (!empty($filterConditions)) {
        $query .= " WHERE " . implode(' AND ', $filterConditions);
    }

    $query .= " ORDER BY $sortColumn $sortOrder";

    $stmt = $pdo->prepare($query);

    // Bind parameters safely
    if (isset($params['region'])) {
        $stmt->bindValue(':region', $params['region'], PDO::PARAM_STR);
    }

    if (isset($params['product_description'])) {
        $stmt->bindValue(':product_description', $params['product_description'], PDO::PARAM_STR);
    }

    $stmt->bindValue(':min_price', $params['min_price'], PDO::PARAM_STR);
    $stmt->bindValue(':max_price', $params['max_price'], PDO::PARAM_STR);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalSpotPricesCount($pdo, $params)
{
    $filterConditions = [];

    if (isset($params['region'])) {
        $filterConditions[] = "region = :region";
    }

    if (isset($params['product_description'])) {
        $filterConditions[] = "product_description = :product_description";
    }

    $filterConditions[] = "spot_price BETWEEN :min_price AND :max_price";

    // Updated query to use the latest_spot_prices table
    $query = "SELECT COUNT(*) FROM latest_spot_prices";

    if (!empty($filterConditions)) {
        $query .= " WHERE " . implode(' AND ', $filterConditions);
    }

    $stmt = $pdo->prepare($query);

    // Bind parameters
    if (isset($params['region'])) {
        $stmt->bindValue(':region', $params['region'], PDO::PARAM_STR);
    }

    if (isset($params['product_description'])) {
        $stmt->bindValue(':product_description', $params['product_description'], PDO::PARAM_STR);
    }

    $stmt->bindValue(':min_price', $params['min_price'], PDO::PARAM_STR);
    $stmt->bindValue(':max_price', $params['max_price'], PDO::PARAM_STR);

    $stmt->execute();
    return $stmt->fetchColumn();
}



function getStealsMetaData($pdo)
{
    $query = "
        SELECT 
            DISTINCT region 
        FROM steal_spot_pricing
        ORDER BY region ASC;
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $query = "
        SELECT 
            DISTINCT product_description 
        FROM steal_spot_pricing
        ORDER BY product_description ASC;
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $productDescriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $query = "
        SELECT 
            DISTINCT steal_type 
        FROM steal_spot_pricing
        ORDER BY steal_type ASC;
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stealTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return [
        'regions' => $regions,
        'product_descriptions' => $productDescriptions,
        'steal_types' => $stealTypes
    ];
}
function getSteals($pdo, $filters)
{
    $query = "
        SELECT 
            region,
            instance_type,
            product_description,
            spot_price,
            timestamp,
            steal_type
        FROM steal_spot_pricing
        WHERE 1=1
    ";

    $params = [];

    if (!empty($filters['region'])) {
        $query .= " AND region = :region";
        $params[':region'] = $filters['region'];
    }

    if (!empty($filters['product_description'])) {
        $query .= " AND product_description = :product_description";
        $params[':product_description'] = $filters['product_description'];
    }

    if (!empty($filters['steal_type'])) {
        $query .= " AND steal_type = :steal_type";
        $params[':steal_type'] = $filters['steal_type'];
    }

    $query .= " ORDER BY spot_price ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
