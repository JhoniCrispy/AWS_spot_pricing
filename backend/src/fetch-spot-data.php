<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/database.php';
echo "Path to database.php: " . __DIR__ . '/database.php' . PHP_EOL;
echo "Path to autoload.php: " . __DIR__ . '/../vendor/autoload.php' . PHP_EOL;

use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;


function fetchAWSPricing()
{
    try {

        $config = require __DIR__ . '/config.php';
        $pdo = getPDOConnection($config['db']);

        // List of regions you want to fetch
        $regions = [
            'us-east-1',
            'us-east-2',
            'us-west-1',
            'us-west-2',
            // add more if needed
        ];

        foreach ($regions as $region) {
            $ec2 = new Ec2Client([
                'region' => $region,
                'version' => $config['aws']['version'],
                // Include credentials if not using instance roles or environment variables
                // 'credentials' => [ ... ],
            ]);

            // Pagination / multiple calls might be required for large data
            $result = $ec2->describeSpotPriceHistory([
                'StartTime' => new \DateTime('-1 day'),
                'EndTime' => new \DateTime('now'),
                // 'InstanceTypes' => ['m5.large','t3.micro'], // optionally limit
                // 'MaxResults' => 1000,
                // 'ProductDescriptions' => ['Linux/UNIX'], // optionally limit
            ]);

            $priceHistory = $result->get('SpotPriceHistory') ?? [];

            // Prepare insert statement
            $stmt = $pdo->prepare("
                INSERT INTO spot_prices (region, instance_type, product_description, spot_price, timestamp)
                VALUES (:region, :instance_type, :product_description, :spot_price, :timestamp)
            ");

            foreach ($priceHistory as $record) {
                // Insert data
                $stmt->execute([
                    ':region' => $region,
                    ':instance_type' => $record['InstanceType'],
                    ':product_description' => $record['ProductDescription'],
                    ':spot_price' => $record['SpotPrice'],
                    ':timestamp' => date('Y-m-d H:i:s', strtotime($record['Timestamp']))
                ]);
            }

            echo "Fetched and inserted for region: {$region}\n";
        }

    } catch (Exception $e) {
        echo "" . $e->getMessage() . PHP_EOL;
    }
}

function getAWSPricingNoCredantials()
{
    function getAwsPricing($serviceCode, $region = 'us-east-1')
    {
        $url = sprintf(
            'https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/%s/current/%s-pricing.json',
            $serviceCode,
            $region
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    // Example usage for EC2 pricing
    try {
        $ec2Pricing = getAwsPricing('AmazonEC2');
        print_r($ec2Pricing);
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
}



function fetchAwsPricingData()
{
    $apiUrl = "https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/AmazonEC2/current/us-east-1/index.json";
    // Open a file for writing
    $filePath = __DIR__ . '/aws_pricing_data.json';
    $fileHandle = fopen($filePath, 'w');

    if (!$fileHandle) {
        die("Failed to open file for writing.");
    }
    // Initialize cURL session
    $ch = curl_init($apiUrl);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    // Stream response directly to the file
    curl_setopt($ch, CURLOPT_FILE, $fileHandle);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);
    fclose($fileHandle);

}

function awsspotpricing()
{

    // AWS Spot Pricing data URL
    $apiUrl = "https://spot-price.s3.amazonaws.com/spot.js";

    // Define the local file path to save data
    $filePath = __DIR__ . '/aws_spot_pricing.json';

    echo "Fetching AWS EC2 spot pricing data...\n";

    // Use cURL to fetch and save the data
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // Fetch the data
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "Error downloading data: " . curl_error($ch) . "\n";
        curl_close($ch);
        exit;
    }

    curl_close($ch);

    // The response from the API is in JSONP format, need to clean it up
    $cleanedJson = preg_replace('/^callback\((.*)\);$/', '$1', $response);

    // Save cleaned JSON data to file
    file_put_contents($filePath, $cleanedJson);

    echo "AWS Spot pricing data saved to: $filePath\n";
}

awsspotpricing();