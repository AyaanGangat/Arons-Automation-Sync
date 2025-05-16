<?php
error_reporting(E_ALL); // Good for debugging
ini_set('display_errors', 1); // Good for debugging

// 🔐 WooCommerce API credentials
$consumer_key = 'ck_470e44f9b713d1d78115a9d5f7a92c18dba3d6ab'; // Make sure these are your LATEST regenerated keys
$consumer_secret = 'cs_16dae744e9fb6a2dcbf9586f6c0f68f34fdf50db'; // Make sure these are your LATEST regenerated keys
$site_url = 'https://arons-test-site.local/wp-json/wc/v3/products'; // HTTPS is correct

// 📂 Path to the IQ CSV export
$csv_path = 'C:\IQSync\stock.csv'; // Adjust path as needed

// 🛑 Exit if file doesn't exist
if (!file_exists($csv_path)) {
    echo "❌ CSV file not found at $csv_path\n";
    exit;
}
echo "✅ CSV file found at $csv_path\n";

// 🧾 Read the CSV file into an array
$csv = array_map(function ($line) {
    return str_getcsv($line, ",", '"', "\\");
}, file($csv_path));
$header = array_map('trim', array_shift($csv));

echo "✅ CSV loaded. Header processed. Starting loop...\n";

// 🔁 Loop through CSV rows
foreach ($csv as $row) {
    if (count($header) !== count($row)) {
        echo "⚠️ Skipping row due to column count mismatch. Header count: " . count($header) . ", Row count: " . count($row) . "\n";
        print_r($row);
        continue;
    }
    $data = array_combine($header, $row);

    if (!isset($data['CODE']) || !isset($data['ONHAND'])) {
        echo "⚠️ Skipping row due to missing CODE or ONHAND. Data: \n";
        print_r($data);
        continue;
    }
    $raw_sku = $data['CODE'];
    $sku = preg_replace('/[^A-Za-z0-9_\-\/]/', '', trim($raw_sku));
    $stock = intval($data['ONHAND']);

    echo "Processing SKU - Raw: >$raw_sku< ➜ Cleaned: >$sku<, Stock: $stock\n";

    // 🔍 Use cURL to GET product by SKU
    $get_url = $site_url . "?sku=" . urlencode($sku);
    echo "🌐 Requesting (GET): $get_url\n";

    $ch_get = curl_init($get_url);
    curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_get, CURLOPT_USERPWD, "$consumer_key:$consumer_secret"); // Basic Auth
    curl_setopt($ch_get, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // Disable SSL verification for local self-signed certificate
    curl_setopt($ch_get, CURLOPT_SSL_VERIFYPEER, false); // <--- CORRECT PLACEMENT FOR GET
    curl_setopt($ch_get, CURLOPT_SSL_VERIFYHOST, false); // <--- CORRECT PLACEMENT FOR GET
    $response = curl_exec($ch_get);
    $http_code_get = curl_getinfo($ch_get, CURLINFO_HTTP_CODE);
    $curl_error_get = curl_error($ch_get);
    curl_close($ch_get);

    echo "🧾 API Response (GET) HTTP Code: $http_code_get | cURL Error: $curl_error_get | Body: $response\n";

    if ($response === false || $http_code_get >= 400) {
        echo "❌ Error fetching data for SKU $sku. HTTP Code: $http_code_get. cURL error: $curl_error_get\n";
        continue;
    }

    $products = json_decode($response, true);

    // ✅ If product exists, update its stock
    if (!empty($products) && isset($products[0]['id'])) {
        $product_id = $products[0]['id'];
        echo "Found product ID: $product_id for SKU: $sku. Preparing to update stock.\n";

        $update_data = [
            'stock_quantity' => $stock,
            'manage_stock' => true
        ];

        $put_url = $site_url . "/" . $product_id;
        echo "🌐 Requesting (PUT): $put_url with data: " . json_encode($update_data) . "\n";

        $ch_put = curl_init($put_url);
        curl_setopt($ch_put, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_put, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch_put, CURLOPT_POSTFIELDS, json_encode($update_data));
        curl_setopt($ch_put, CURLOPT_USERPWD, "$consumer_key:$consumer_secret"); // Basic Auth
        curl_setopt($ch_put, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        // Disable SSL verification for local self-signed certificate
        curl_setopt($ch_put, CURLOPT_SSL_VERIFYPEER, false); // <--- CORRECT PLACEMENT FOR PUT
        curl_setopt($ch_put, CURLOPT_SSL_VERIFYHOST, false); // <--- CORRECT PLACEMENT FOR PUT
        $result = curl_exec($ch_put);
        $http_code_put = curl_getinfo($ch_put, CURLINFO_HTTP_CODE);
        $curl_error_put = curl_error($ch_put);
        curl_close($ch_put);

        echo "🧾 API Response (PUT) HTTP Code: $http_code_put | cURL Error: $curl_error_put | Body: $result\n";

        if ($result !== false && $http_code_put < 400) {
            echo "✅ Updated SKU $sku to $stock units\n";
        } else {
            echo "❌ Failed to update SKU $sku. HTTP Code: $http_code_put. cURL error: $curl_error_put. Response: $result\n";
        }

    } else {
        echo "⚠️ SKU $sku not found in WooCommerce or API response was empty/invalid — skipped\n";
        if (is_array($products) && empty($products)) {
            echo "(API returned an empty array, meaning no product matched the SKU '$sku')\n";
        } elseif ($products === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "(API response was not valid JSON. Error: " . json_last_error_msg() . ")\n";
        }
    }
    echo "--------------------------------------------------\n";
}

echo "🔚 Script finished.\n";
?>