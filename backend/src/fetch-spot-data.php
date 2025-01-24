<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/database.php';



use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;
use Dotenv\Dotenv;
use Aws\Credentials\CredentialProvider;



function awsspotpricing()
{

    try {
        $config = require __DIR__ . '/config.php';
        // 1. Load environment variables from .env
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $awsCredentialsPath = $_ENV['AWS_CREDENTIALS_PATH'];
        $awsProfile = $_ENV['AWS_PROFILE'];
        $default_region = $_ENV['AWS_DEFAULT_REGION'];

        // Specify the 'yoni' profile
        $provider = CredentialProvider::ini($awsProfile, $awsCredentialsPath);

        // 4. Create an EC2 client (in the default region) to list all regions
        $ec2 = new Ec2Client([
            'version' => 'latest',
            'region' => $default_region,
            'credentials' => $provider,
            // *** Disables SSL verification (NOT recommended in production) ***
            'http' => ['verify' => false],
        ]);

        // 5. Describe all available regions
        $regionsResult = $ec2->describeRegions();
        $regions = $regionsResult['Regions'] ?? [];

        // 6. Open CSV file for writing
        $csvFileName = sprintf('/temp_files/spot_prices_%s.csv', date('Ymd_His'));
        $csvFilePath = __DIR__ . DIRECTORY_SEPARATOR . $csvFileName;
        $fp = fopen($csvFilePath, 'w');
        if (!$fp) {
            throw new Exception("Failed to create CSV file at: {$csvFilePath}");
        }

        // Write CSV header
        fputcsv($fp, [
            'region',
            'instance_type',
            'product_description',
            'spot_price',
            'availability_zone',
            'timestamp'
        ]);

        // 7. Prepare DB connection
        $pdo = getPDOConnection($config['db']);


        // Create table if it doesn't exist
        $createTableSQL = "
            DROP TABLE IF EXISTS spot_prices;
            
            CREATE TABLE IF NOT EXISTS spot_prices (
                id INT AUTO_INCREMENT PRIMARY KEY NOT NULL,
                region VARCHAR(50) NOT NULL,
                instance_type VARCHAR(50) NOT NULL,
                product_description VARCHAR(100) DEFAULT NULL,
                spot_price DECIMAL(10,2) NOT NULL,
                availability_zone VARCHAR(50) DEFAULT NULL,
                timestamp DATETIME NOT NULL
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
        // 8. Fetch Spot Price History for each region, page by page
        $totalRecords = 0;

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
                    $response = $ec2Client->describeSpotPriceHistory([
                        'StartTime' => new \DateTime(datetime: '-1 day'),
                        'EndTime' => new \DateTime('now'),
                        'MaxResults' => 1000,
                        'NextToken' => $nextToken,
                    ]);

                    $spotPriceHistory = $response['SpotPriceHistory'] ?? [];
                    if (count($spotPriceHistory) === 0) {
                        // No more data, break out
                        $nextToken = null;
                    } else {
                        // Insert in smaller batches to reduce memory usage
                        $pdo->beginTransaction();

                        foreach ($spotPriceHistory as $spotPrice) {
                            $record = [
                                'region' => $regionName,
                                'instance_type' => $spotPrice['InstanceType'] ?? null,
                                'product_description' => $spotPrice['ProductDescription'] ?? null,
                                'spot_price' => (float) $spotPrice['SpotPrice'], // Convert to float
                                'availability_zone' => $spotPrice['AvailabilityZone'] ?? null,
                                'timestamp' => $spotPrice['Timestamp']->format('Y-m-d H:i:s'),
                            ];

                            // Insert into DB
                            $stmt->execute([
                                $record['region'],
                                $record['instance_type'],
                                $record['product_description'],
                                $record['spot_price'],
                                $record['availability_zone'],
                                $record['timestamp']
                            ]);

                            // Write to CSV
                            fputcsv($fp, [
                                $record['region'],
                                $record['instance_type'],
                                $record['product_description'],
                                $record['spot_price'],
                                $record['availability_zone'],
                                $record['timestamp']
                            ]);

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

        // 9. Close the CSV file
        fclose($fp);

        echo "\n=== Spot Price Data Fetched Successfully ===\n";
        echo "Total records inserted: {$totalRecords}\n";
        echo "CSV file created: {$csvFileName}\n";
        echo "Data saved to database table 'spot_prices'.\n";

    } catch (AwsException $e) {
        echo "AWS Error: " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "General Error: " . $e->getMessage() . "\n";
    }
}

awsspotpricing();