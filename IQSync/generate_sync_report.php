<?php

/**
 * Generates and saves an HTML report for the stock sync process.
 *
 * @param array $report_data Associative array containing all data for the report.
 *                           Expected keys:
 *                           'start_time' (string)
 *                           'end_time' (string)
 *                           'total_processed_csv_rows' (int)
 *                           'successful_updates' (array of arrays, each with 'sku', 'product_name', 'old_stock', 'new_stock')
 *                           'skipped_products' (array of arrays, each with 'identifier', 'reason')
 *                           'failed_api_calls' (array of arrays, each with 'sku', 'action', 'http_code', 'error_message', 'response_body')
 *                           'script_errors' (array of strings)
 *                           'execution_log' (array of strings)
 * @param string $output_file_path Full path to save the HTML report file.
 * @return bool True on success, false on failure to write file.
 */
function generate_and_save_sync_report(array $report_data, string $output_file_path): bool {
    $html = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Stock Sync Report</title>";
    $html .= "<style>
        body { font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; color: #343a40; font-size: 14px; line-height: 1.6; }
        .container { max-width: 1000px; margin: 30px auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 15px; margin-top: 0; font-size: 26px; font-weight: 600; }
        h2 { color: #34495e; margin-top: 35px; border-bottom: 1px solid #e9ecef; padding-bottom: 10px; font-size: 20px; font-weight: 500;}
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; font-size: 0.9em; }
        th, td { border-bottom: 1px solid #dee2e6; padding: 12px 15px; text-align: left; vertical-align: top; }
        th { background-color: #3498db; color: white; font-weight: 600; border-top-left-radius: 5px; border-top-right-radius: 5px;}
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) td { background-color: #f8f9fa; } /* Subtle striping for data rows */
        .summary-section { margin-bottom: 30px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px; }
        .summary-item { background-color: #ecf0f1; padding: 15px; border-radius: 5px; text-align: center; }
        .summary-item strong { display: block; font-size: 24px; color: #2980b9; margin-bottom: 5px; }
        .summary-item span { font-size: 14px; color: #7f8c8d; }
        .details-table th { background-color: #5dade2; }
        .status-icon { margin-right: 8px; font-size: 1.1em; }
        .success .status-icon { color: #2ecc71; } /* Green */
        .skipped .status-icon { color: #f39c12; } /* Orange */
        .failed .status-icon { color: #e74c3c; }  /* Red */
        .error-message-box { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 25px; border-radius: 5px; }
        .execution-log-box { margin-top: 25px; padding:15px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 5px; max-height: 350px; overflow-y: auto; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 0.8em; line-height: 1.5;}
        .execution-log-box p { margin: 2px 0; word-break: break-all; }
        .response-body-snippet { font-family: monospace; font-size: 0.85em; white-space: pre-wrap; word-break: break-all; background-color: #f1f3f5; padding: 8px; border: 1px dashed #adb5bd; max-height: 120px; overflow-y: auto; display: block; margin-top: 6px; border-radius: 3px;}
        .footer { text-align: center; margin-top: 30px; font-size: 0.85em; color: #95a5a6; }
    </style>";
    $html .= "</head><body><div class='container'>";
    $html .= "<h1>Stock Synchronization Report</h1>";

    // Display general script errors first
    if (!empty($report_data['script_errors'])) {
        $html .= "<h2><span class='status-icon failed'>❌</span>Critical Script Errors</h2>"; // Red X
        foreach ($report_data['script_errors'] as $err) {
            $html .= "<p class='error-message-box'>" . htmlspecialchars($err) . "</p>";
        }
    }

    // Summary Section
    $html .= "<div class='summary-section'>";
    $html .= "<h2><span class='status-icon'></span>Sync Summary</h2>"; // Chart icon
    $html .= "<p><strong>Last Sync Run:</strong> " . htmlspecialchars($report_data['start_time']) . " to " . htmlspecialchars($report_data['end_time']) . "</p>";
    $html .= "<div class='summary-grid'>";
    $html .= "<div class='summary-item'><strong>" . htmlspecialchars($report_data['total_processed_csv_rows']) . "</strong><span>CSV Rows Processed</span></div>";
    $html .= "<div class='summary-item success'><strong>" . count($report_data['successful_updates']) . "</strong><span>Successful Updates</span></div>";
    $html .= "<div class='summary-item skipped'><strong>" . count($report_data['skipped_products']) . "</strong><span>Skipped Items</span></div>";
    $html .= "<div class='summary-item failed'><strong>" . count($report_data['failed_api_calls']) . "</strong><span>Failed API Calls</span></div>";
    $html .= "</div></div>";

    // Successful Updates
    if (!empty($report_data['successful_updates'])) {
        $html .= "<h2><span class='status-icon success'>✔</span>Successful Stock Updates</h2>"; // Checkmark
        $html .= "<table class='details-table'><thead><tr><th>SKU</th><th>Product Name</th><th>Old Stock (Woo)</th><th>New Stock (Synced)</th></tr></thead><tbody>";
        foreach ($report_data['successful_updates'] as $item) {
            $html .= "<tr>
                        <td>" . htmlspecialchars($item['sku']) . "</td>
                        <td>" . htmlspecialchars($item['product_name']) . "</td>
                        <td>" . htmlspecialchars($item['old_stock']) . "</td>
                        <td>" . htmlspecialchars($item['new_stock']) . "</td>
                      </tr>";
        }
        $html .= "</tbody></table>";
    }

    // Skipped Products
    if (!empty($report_data['skipped_products'])) {
        $html .= "<h2><span class='status-icon skipped'>⚠</span>Skipped Products/Operations</h2>"; // Warning sign
        $html .= "<table class='details-table'><thead><tr><th>Identifier/SKU</th><th>Reason for Skipping</th></tr></thead><tbody>";
        foreach ($report_data['skipped_products'] as $item) {
            $html .= "<tr>
                        <td>" . htmlspecialchars($item['identifier']) . "</td>
                        <td>" . htmlspecialchars($item['reason']) . "</td>
                      </tr>";
        }
        $html .= "</tbody></table>";
    }

    // Failed API Calls
    if (!empty($report_data['failed_api_calls'])) {
        $html .= "<h2><span class='status-icon failed'>❌</span>Failed API Calls</h2>"; // Red X
        $html .= "<table class='details-table'><thead><tr><th>SKU</th><th>Action Attempted</th><th>HTTP Code</th><th>Error Details</th><th>API Response (Partial)</th></tr></thead><tbody>";
        foreach ($report_data['failed_api_calls'] as $item) {
            $html .= "<tr>
                        <td>" . htmlspecialchars($item['sku']) . "</td>
                        <td>" . htmlspecialchars($item['action']) . "</td>
                        <td>" . htmlspecialchars($item['http_code']) . "</td>
                        <td>" . htmlspecialchars($item['error_message']) . "</td>
                        <td><div class='response-body-snippet'>" . $item['response_body'] . "</div></td>
                      </tr>";
        }
        $html .= "</tbody></table>";
    }
    
    // Detailed Execution Log (Optional - can be very long)
    if (!empty($report_data['execution_log'])) {
        $html .= "<h2><span class='status-icon'></span>Detailed Execution Log</h2><div class='execution-log-box'>"; // File icon
        foreach ($report_data['execution_log'] as $log_msg) {
            $html .= "<p>" . htmlspecialchars($log_msg) . "</p>";
        }
        $html .= "</div>";
    }

    $html .= "<div class='footer'><p>Report generated on " . date('Y-m-d H:i:s') . "</p></div>";
    $html .= "</div></body></html>";

    if (file_put_contents($output_file_path, $html) === false) {
        error_log("Stock Sync Report: CRITICAL ERROR - Failed to write HTML report to $output_file_path. Check permissions.");
        return false;
    } else {
        error_log("Stock Sync Report: HTML report generated successfully: " . $output_file_path);
        return true;
    }
}

// --- HOW TO USE THIS IN YOUR MAIN sync_stock.php SCRIPT ---
/*
At the beginning of your sync_stock.php:
require_once 'generate_sync_report.php'; // If you save this function in a separate file
$report_data = [
    'start_time' => date('Y-m-d H:i:s'),
    'end_time' => '',
    'total_processed_csv_rows' => 0,
    'successful_updates' => [],
    'skipped_products' => [],
    'failed_api_calls' => [],
    'script_errors' => [],
    'execution_log' => []
];
$report_data['execution_log'][] = "Script initiated.";


During your script's execution, populate $report_data:
Example:
if (/* successful update *///) {
//    $report_data['successful_updates'][] = ['sku' => $sku, ...];
//} elseif (/* skipped */) {
//    $report_data['skipped_products'][] = ['identifier' => $sku, 'reason' => ...];
//} elseif (/* api call failed */) {
//    $report_data['failed_api_calls'][] = ['sku' => $sku, 'action' => ..., 'http_code' => ...];
//}
//$report_data['execution_log'][] = "Processed SKU: $sku";


At the VERY END of your sync_stock.php:
$report_data['end_time'] = date('Y-m-d H:i:s');
$report_data['execution_log'][] = "Script finished generating report data.";

$report_file_path = 'C:\IQSync\sync_report_last.html'; // Or timestamped
// $timestamp = date('Ymd_His');
// $report_file_path = 'C:\IQSync\reports\sync_report_' . $timestamp . '.html';
// if (!is_dir(dirname($report_file_path))) { mkdir(dirname($report_file_path), 0755, true); }

generate_and_save_sync_report($report_data, $report_file_path);

*/

?>