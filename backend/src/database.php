<?php
function getPDOConnection($config) {
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
