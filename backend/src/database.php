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
function getSpotPrices($pdo, $params, $limit, $offset, $sortColumn, $sortOrder)
{
    $allowedSortColumns = ['region', 'instance_type', 'product_description', 'spot_price', 'timestamp'];
    $allowedSortOrder = ['ASC', 'DESC'];

    // Prevent SQL injection by whitelisting allowed sort columns and order
    $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'timestamp';
    $sortOrder = in_array(strtoupper($sortOrder), $allowedSortOrder) ? strtoupper($sortOrder) : 'DESC';

    $filterConditions = [];

    if (isset($params['region'])) {
        $filterConditions[] = "sp.region = :region";
    }

    if (isset($params['product_description'])) {
        $filterConditions[] = "sp.product_description = :product_description";
    }

    $filterConditions[] = "sp.spot_price BETWEEN :min_price AND :max_price";

    $query = "
        SELECT sp.region, sp.instance_type, sp.product_description, sp.spot_price, sp.timestamp 
        FROM spot_prices sp
        INNER JOIN (
            SELECT region, instance_type, product_description, MAX(timestamp) AS latest_timestamp
            FROM spot_prices
            GROUP BY region, instance_type, product_description
        ) latest 
        ON sp.region = latest.region 
        AND sp.instance_type = latest.instance_type 
        AND sp.product_description = latest.product_description 
        AND sp.timestamp = latest.latest_timestamp
    ";

    if (!empty($filterConditions)) {
        $query .= " WHERE " . implode(' AND ', $filterConditions);
    }

    $query .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";

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
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getTotalSpotPricesCount($pdo, $params)
{
    $filterConditions = [];

    if (isset($params['region'])) {
        $filterConditions[] = "sp.region = :region";
    }

    if (isset($params['product_description'])) {
        $filterConditions[] = "sp.product_description = :product_description";
    }

    $filterConditions[] = "sp.spot_price BETWEEN :min_price AND :max_price";

    $query = "
        SELECT COUNT(*) 
        FROM (
            SELECT sp.region, sp.instance_type, sp.product_description
            FROM spot_prices sp
            INNER JOIN (
                SELECT region, instance_type, product_description, MAX(timestamp) AS latest_timestamp
                FROM spot_prices
                GROUP BY region, instance_type, product_description
            ) latest 
            ON sp.region = latest.region 
            AND sp.instance_type = latest.instance_type 
            AND sp.product_description = latest.product_description 
            AND sp.timestamp = latest.latest_timestamp
    ";

    if (!empty($filterConditions)) {
        $query .= " WHERE " . implode(' AND ', $filterConditions);
    }

    $query .= ") AS filtered_spot_prices";

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

function getSteals($pdo)
{
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
