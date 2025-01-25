<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/database.php';



use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;
use Dotenv\Dotenv;
use Aws\Credentials\CredentialProvider;
$config = require __DIR__ . '/config.php';



function awsspotpricing()
{

    try {
        $config = require __DIR__ . '/config.php';

        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $awsCredentialsPath = $_ENV['AWS_CREDENTIALS_PATH'];
        $awsProfile = $_ENV['AWS_PROFILE'];
        $default_region = $_ENV['AWS_DEFAULT_REGION'];


        $provider = CredentialProvider::ini($awsProfile, $awsCredentialsPath);

        $startTime = microtime(true);

        $ec2 = new Ec2Client([
            'version' => 'latest',
            'region' => $default_region,
            'credentials' => $provider,

            'http' => ['verify' => false],
        ]);

        // 5. Describe all available regions
        $regionsResult = $ec2->describeRegions();
        $regions = $regionsResult['Regions'] ?? [];

        // 7. Prepare DB connection
        $pdo = getPDOConnection($config['db']);


        // Create table if it doesn't exist
        $createTableSQL = "
                DROP TABLE IF EXISTS spot_prices;
    
                CREATE TABLE spot_prices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    region VARCHAR(50) NOT NULL,
                    instance_type VARCHAR(50) NOT NULL,
                    product_description VARCHAR(100),
                    spot_price DECIMAL(10,2) NOT NULL,
                    availability_zone VARCHAR(50),
                    timestamp DATETIME NOT NULL,
                    INDEX idx_unique_combination (region, instance_type, product_description, timestamp)
                );
            ";
        $pdo->exec($createTableSQL);


        // Prepare insert statement once
        $insertSQL = "
        INSERT INTO spot_prices(
            region, instance_type, product_description, spot_price, availability_zone, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?)
    ";
        $stmt = $pdo->prepare($insertSQL);
        echo "Query: " . $insertSQL . PHP_EOL;

        $totalRecords = 0;

        $startTimeToFetch = isset($config['fetch-spot-data']['StartTime']) && $config['fetch-spot-data']['StartTime'] !== null ? new \DateTime($config['fetch-spot-data']['StartTime']) : null;
        $endTimeToFetch = isset($config['fetch-spot-data']['EndTime']) && $config['fetch-spot-data']['EndTime'] !== null ? new \DateTime($config['fetch-spot-data']['EndTime']) : null;

        foreach ($regions as $regionObj) {
            $regionName = $regionObj['RegionName'] ?? null;
            if (!$regionName) {
                continue;
            }

            echo "Fetching spot prices for region: {$regionName}\n";

            // Create region-specific EC2 client
            $ec2Client = new Ec2Client([
                'version' => 'latest',
                'region' => $regionName,
                'credentials' => $provider,
                'http' => ['verify' => false],
            ]);

            // Manual pagination
            $nextToken = null;

            do {
                try {
                    $params = [];

                    if ($startTimeToFetch) {
                        $params['StartTime'] = $startTimeToFetch;
                    }

                    if ($endTimeToFetch) {
                        $params['EndTime'] = $endTimeToFetch;
                    }

                    if ($nextToken) {
                        $params['NextToken'] = $nextToken;
                    }

                    $response = $ec2Client->describeSpotPriceHistory($params);

                    $spotPriceHistory = $response['SpotPriceHistory'] ?? [];
                    if (count($spotPriceHistory) === 0) {
                        // No more data, break out
                        $nextToken = null;
                    } else {
                        // Insert in smaller batches to reduce memory usage
                        $pdo->beginTransaction();

                        $batchSize = 1000;
                        $batch = [];

                        foreach ($spotPriceHistory as $spotPrice) {
                            $batch[] = [
                                $regionName,
                                $spotPrice['InstanceType'] ?? null,
                                $spotPrice['ProductDescription'] ?? null,
                                (float) $spotPrice['SpotPrice'],
                                $spotPrice['AvailabilityZone'] ?? null,
                                $spotPrice['Timestamp']->format('Y-m-d H:i:s')
                            ];

                            if (count($batch) >= $batchSize) {
                                $placeholders = implode(',', array_fill(0, count($batch[0]), '?'));
                                $batchInsertSQL = "INSERT INTO spot_prices (region, instance_type, product_description, spot_price, availability_zone, timestamp) VALUES "
                                    . implode(',', array_fill(0, count($batch), "($placeholders)"));

                                $flattenedBatch = array_merge(...$batch);
                                $stmt = $pdo->prepare($batchInsertSQL);
                                $stmt->execute($flattenedBatch);

                                $batch = [];
                            }


                            $totalRecords++;
                        }

                        $pdo->commit();

                        // Set nextToken for next page
                        $nextToken = $response['NextToken'] ?? null;
                    }
                } catch (AwsException $e) {
                    echo "AWS Error fetching spot prices for region {$regionName}: " . $e->getMessage() . "\n";
                    break;
                }
            } while ($nextToken);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        echo "Total execution time: " . round($executionTime, 2) . " seconds\n";
        echo "Memory peak usage: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";
        echo "\n=== Spot Price Data Fetched Successfully ===\n";
        echo "Total records inserted: {$totalRecords}\n";
        echo "Data saved to database table 'spot_prices'.\n";

    } catch (AwsException $e) {
        echo "AWS Error: " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "General Error: " . $e->getMessage() . "\n";
    }
}


function createLatestPricesTable()
{
    try {

        $startTime = microtime(true);
        $config = require __DIR__ . '/config.php';

        $pdo = getPDOConnection($config['db']);

        // Drop table if exists before creating
        $dropTableSQL = "DROP TABLE IF EXISTS latest_spot_prices";
        $pdo->exec($dropTableSQL);

        $createLatestPricesTableSQL = "
    CREATE TABLE latest_spot_prices AS
    SELECT *
    FROM spot_prices
    WHERE (region, instance_type, product_description, timestamp) IN (
        SELECT 
            region, 
            instance_type, 
            product_description, 
            MAX(timestamp) AS max_timestamp
        FROM spot_prices
        GROUP BY region, instance_type, product_description
    );
    ";
        $pdo->exec($createLatestPricesTableSQL);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        echo "Total execution time: " . round($executionTime, 2) . " seconds\n";
        echo "Memory peak usage: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";

    } catch (AwsException $e) {
        echo "" . $e->getMessage() . "";
    }
}


function createStealSpotPricingTable()
{
    try {

        $startTime = microtime(true);

        $config = require __DIR__ . '/config.php';
        $pdo = getPDOConnection($config['db']);

        // Drop table if exists before creating
        $dropTableSQL = "DROP TABLE IF EXISTS steal_spot_pricing";
        $pdo->exec($dropTableSQL);


        $createStealsSQL = "
            CREATE TABLE IF NOT EXISTS steal_spot_pricing (
                id INT AUTO_INCREMENT PRIMARY KEY,
                spot_price_id INT NOT NULL,
                region VARCHAR(50) NOT NULL,
                instance_type VARCHAR(50) NOT NULL,
                product_description VARCHAR(100),
                spot_price DECIMAL(10,4) NOT NULL,
                timestamp DATETIME NOT NULL,
                steal_type VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_region_instance (region, instance_type, product_description)
            )
        ";
        $pdo->exec($createStealsSQL);

        function calculate_over_time_steals($pdo)
        {
            // 3. Retrieve all rows from latest_spot_prices (the table holding the newest prices for each instance)
            $sqlLatest = "
            SELECT *
            FROM latest_spot_prices
        ";
            $stmtLatest = $pdo->query($sqlLatest);

            while ($latestRow = $stmtLatest->fetch(PDO::FETCH_ASSOC)) {

                // Extract fields from the latest_spot_prices row
                $spotPriceId = $latestRow['id']; // or link to spot_prices.id if you maintain that differently
                $region = $latestRow['region'];
                $instanceType = $latestRow['instance_type'];
                $productDesc = $latestRow['product_description'];
                $currentPrice = $latestRow['spot_price'];
                $currentTimestamp = $latestRow['timestamp'];

                $sqlAvg = "
                SELECT AVG(spot_price) AS avg_price
                FROM spot_prices
                WHERE region = :region
                  AND instance_type = :instance_type
                  AND product_description = :product_description
            ";

                $stmtAvg = $pdo->prepare($sqlAvg);
                $stmtAvg->execute([
                    ':region' => $region,
                    ':instance_type' => $instanceType,
                    ':product_description' => $productDesc
                ]);

                $avgRow = $stmtAvg->fetch(PDO::FETCH_ASSOC);
                $avgPrice = $avgRow['avg_price'] ?? null;

                if ($avgPrice !== null) {
                    $threshold = 0.8 * $avgPrice;

                    if ($currentPrice <= $threshold) {
                        $sqlInsertSteal = "
                        INSERT INTO steal_spot_pricing (
                            spot_price_id,
                            region,
                            instance_type,
                            product_description,
                            spot_price,
                            timestamp,
                            steal_type
                        )
                        SELECT
                            :spot_price_id,
                            :region,
                            :instance_type,
                            :product_description,
                            :spot_price,
                            :timestamp,
                            'below_80pct_average'
                        FROM DUAL
                        WHERE NOT EXISTS (
                            SELECT 1
                            FROM steal_spot_pricing
                            WHERE spot_price_id = :spot_price_id
                              AND steal_type = 'below_80pct_average'
                        )
                    ";


                        $stmtSteal = $pdo->prepare($sqlInsertSteal);
                        $stmtSteal->execute([
                            ':spot_price_id' => $spotPriceId,
                            ':region' => $region,
                            ':instance_type' => $instanceType,
                            ':product_description' => $productDesc,
                            ':spot_price' => $currentPrice,
                            ':timestamp' => $currentTimestamp
                        ]);
                    }
                }
            }

            echo "Steals calculation (80% average) complete.\n";
        }
        function calculateLowInRegion($pdo)
        {
            try {
                // 1. Select all distinct regions
                $query = "SELECT DISTINCT region FROM spot_prices ORDER BY region";
                $stmt = $pdo->query($query);
                $regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($regions as $region) {

                    $sqlTop5Region = "
                        SELECT 
                            id,
                            region,
                            instance_type,
                            product_description,
                            spot_price,
                            availability_zone,
                            timestamp
                        FROM latest_spot_prices
                        WHERE region = :region
                        ORDER BY spot_price ASC
                        LIMIT 5
                    ";

                    $stmtTop5 = $pdo->prepare($sqlTop5Region);
                    $stmtTop5->execute([':region' => $region]);

                    $top5 = $stmtTop5->fetchAll();

                    foreach ($top5 as $record) {
                        $spotPriceId = $record['id'];
                        $currentPrice = $record['spot_price'];
                        $currentTimestamp = $record['timestamp'];


                        $sqlInsertSteal = "
                            INSERT INTO steal_spot_pricing (
                                spot_price_id,
                                region,
                                instance_type,
                                product_description,
                                spot_price,
                                timestamp,
                                steal_type
                            )
                            SELECT
                                :spot_price_id,
                                :region,
                                :instance_type,
                                :product_description,
                                :spot_price,
                                :timestamp,
                                'low_in_region'
                            FROM DUAL
                            WHERE NOT EXISTS (
                                SELECT 1
                                FROM steal_spot_pricing
                                WHERE spot_price_id = :spot_price_id
                                  AND steal_type = 'low_in_region'
                            )
                        ";

                        $stmtSteal = $pdo->prepare($sqlInsertSteal);
                        $stmtSteal->execute([
                            ':spot_price_id' => $spotPriceId,
                            ':region' => $record['region'],
                            ':instance_type' => $record['instance_type'],
                            ':product_description' => $record['product_description'],
                            ':spot_price' => $currentPrice,
                            ':timestamp' => $currentTimestamp
                        ]);
                    }

                    echo "Top 5 cheapest for region '{$region}' calculated and stored as 'low_in_region'.\n";
                }

            } catch (Exception $e) {
                echo "Error in calculateLowInRegion(): " . $e->getMessage() . "\n";
            }
        }
        function calculateLowInInstanceType($pdo)
        {
            try {
                // 1. Select all distinct product descriptions
                $query = "SELECT DISTINCT product_description FROM spot_prices ORDER BY product_description";
                $stmt = $pdo->query($query);
                $productDescs = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($productDescs as $productDesc) {
                    $sqlTop5Product = "
                        SELECT 
                            id,
                            region,
                            instance_type,
                            product_description,
                            spot_price,
                            availability_zone,
                            timestamp
                        FROM latest_spot_prices
                        WHERE product_description = :product_description
                        ORDER BY spot_price ASC
                        LIMIT 5
                    ";

                    $stmtTop5 = $pdo->prepare($sqlTop5Product);
                    $stmtTop5->execute([':product_description' => $productDesc]);

                    $top5 = $stmtTop5->fetchAll();

                    foreach ($top5 as $record) {
                        $spotPriceId = $record['id'];
                        $currentPrice = $record['spot_price'];
                        $currentTimestamp = $record['timestamp'];

                        $sqlInsertSteal = "
                            INSERT INTO steal_spot_pricing (
                                spot_price_id,
                                region,
                                instance_type,
                                product_description,
                                spot_price,
                                timestamp,
                                steal_type
                            )
                            SELECT
                                :spot_price_id,
                                :region,
                                :instance_type,
                                :product_description,
                                :spot_price,
                                :timestamp,
                                'low_in_instance_type'
                            FROM DUAL
                            WHERE NOT EXISTS (
                                SELECT 1
                                FROM steal_spot_pricing
                                WHERE spot_price_id = :spot_price_id
                                  AND steal_type = 'low_in_instance_type'
                            )
                        ";

                        $stmtSteal = $pdo->prepare($sqlInsertSteal);
                        $stmtSteal->execute([
                            ':spot_price_id' => $spotPriceId,
                            ':region' => $record['region'],
                            ':instance_type' => $record['instance_type'],
                            ':product_description' => $record['product_description'],
                            ':spot_price' => $currentPrice,
                            ':timestamp' => $currentTimestamp
                        ]);
                    }

                    echo "Top 5 cheapest for product description '{$productDesc}' calculated and stored as 'low_in_instance_type'.\n";
                }

            } catch (Exception $e) {
                echo "Error in calculateLowInInstanceType(): " . $e->getMessage() . "\n";
            }
        }

        calculate_over_time_steals($pdo);
        calculateLowInInstanceType($pdo);
        calculateLowInRegion($pdo);
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        echo "Total execution time: " . round($executionTime, 2) . " seconds\n";
        echo "Memory peak usage: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";
        echo "Steals calculation complete.\n";
    } catch (Exception $e) {
        echo "Error calculating steals: " . $e->getMessage() . "\n";
    }
}



// Runing the functions based on config file.
// Retrieve fetch-spot-data settings
$fetchSpotData = $config['fetch-spot-data'] ?? [];
if (isset($fetchSpotData['run_awsspotpricing']) && $fetchSpotData['run_awsspotpricing'] === true) {
    awsspotpricing();
}

if (isset($fetchSpotData['run_createLatestPricesTable']) && $fetchSpotData['run_createLatestPricesTable'] === true) {
    createLatestPricesTable();
}

if (isset($fetchSpotData['run_createStealSpotPricingTable']) && $fetchSpotData['run_createStealSpotPricingTable'] === true) {
    createStealSpotPricingTable();
}