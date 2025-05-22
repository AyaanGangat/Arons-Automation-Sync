# IQ Retail to WooCommerce Stock Synchronization Solution

## 1. Overview

This solution automates the synchronization of product stock levels from an IQ Retail Point of Sale (POS) system to a WooCommerce website. It reads a CSV export from IQ Retail, compares it with WooCommerce product data via the WooCommerce REST API, and updates stock quantities accordingly. A styled HTML report is generated after each sync attempt.

**Direction of Sync:** One-way (IQ Retail -> WooCommerce) for stock quantities.

## 2. System Components & Setup

### 2.1. Software & Versions (as of setup)

*   **PHP:** Version 8.4.7 (NTS, x64, VS17 build from php.net)
    *   Installed at: `C:\PHP\`
    *   **Required Extensions (enabled in `C:\PHP\php.ini`):**
        *   `curl` (for API communication)
        *   `openssl` (often a dependency for cURL, especially for HTTPS)
*   **WooCommerce:** (Specify version if known) on a WordPress site.
    *   Local Test Site URL: `http://arons-test-site.local`
    *   Live Site URL: (To be configured when deploying to live)
*   **IQ Retail:** Version 2018.1.0.4 (or as confirmed)
    *   Assumed to be locally hosted on a Windows server/PC.
*   **Windows Task Scheduler:** Used for automating the sync script execution.

### 2.2. File Structure (on the machine running the sync)

*   **`C:\PHP\`**: Contains the PHP installation.
    *   **`C:\PHP\php.ini`**: PHP configuration file. Critical settings:
        *   `extension_dir = "C:\PHP\ext"` (Absolute path)
        *   `extension=curl` (Uncommented)
        *   `extension=openssl` (Uncommented)
    *   **`C:\PHP\ext\`**: Contains PHP extension DLLs (e.g., `php_curl.dll`, `php_openssl.dll`).
*   **`C:\IQSync\`**: Main directory for the synchronization solution.
    *   **`stock.csv`**: The CSV file exported from IQ Retail. This file is read by the sync script.
        *   **Expected Columns (must be present in CSV header):** `CODE` (for SKU), `ONHAND` (for stock quantity).
    *   **`sync_stock.php`**: The core PHP script that performs the synchronization and generates the HTML report.
    *   **`run_sync.bat`**: Batch file used by Windows Task Scheduler to execute `sync_stock.php` with the correct `php.ini`.
    *   **`sync_report_last.html`**: The HTML report generated after each sync, showing successes, failures, and skipped items. This file is overwritten on each run.
    *   **(Optional) `C:\IQSync\reports\`**: If historical reports are enabled, timestamped HTML reports will be saved here.
    *   **(Optional) `C:\IQSync\php_errors.log`**: If `error_log` in `sync_stock.php` is configured to point here, PHP errors from the script will be logged here.

### 2.3. WooCommerce Setup

*   **REST API Key:**
    *   Generated from: WooCommerce > Settings > Advanced > REST API.
    *   **User:** Must be an **Administrator**.
    *   **Permissions:** Must be **Read/Write**.
    *   The Consumer Key (`ck_...`) and Consumer Secret (`cs_...`) are hardcoded in `sync_stock.php`. **These should be treated as sensitive credentials.**
*   **Product SKUs:** Products in WooCommerce **must** have SKUs that exactly match the `CODE` field from the IQ Retail CSV export for the sync to work.
*   **Stock Management:** "Manage stock?" should ideally be enabled for products in WooCommerce. The script will attempt to enable it if updating stock.
*   **Permalinks:** WordPress permalinks (Settings > Permalinks) should be set to "Post name" or any option other than "Plain" for the REST API (`/wp-json/...`) to function correctly.

### 2.4. IQ Retail Setup (Client's End)

*   **CSV Export:** IQ Retail must be configured to export a CSV file containing at least `CODE` and `ONHAND` columns.
    *   This export needs to be saved to a consistent location that the `sync_stock.php` script can access (currently configured as `C:\IQSync\stock.csv`).
    *   **Automation of this export:**
        *   If IQ Retail has a built-in scheduler module, it should be configured to overwrite `stock.csv` at regular intervals.
        *   If not, a separate Windows Task Scheduler job on the IQ Retail machine might be needed to trigger a command-line export or a macro to generate this file. (This part is external to the `C:\IQSync` setup but crucial for full automation).

## 3. How it Works (Sync Process)

1.  **IQ Retail Export:** The `stock.csv` file is generated/updated by IQ Retail (ideally automatically).
2.  **Scheduled Task:** Windows Task Scheduler on the sync machine runs `run_sync.bat` at configured intervals (e.g., every 15-30 minutes).
3.  **Batch File Execution:** `run_sync.bat` executes the `sync_stock.php` script using the specified PHP interpreter and `php.ini` file:
    `C:\PHP\php.exe -c C:\PHP\php.ini C:\IQSync\sync_stock.php`
4.  **PHP Script (`sync_stock.php`):**
    *   Reads `C:\IQSync\stock.csv`.
    *   For each row in the CSV:
        *   Extracts the `CODE` (SKU) and `ONHAND` (stock).
        *   Cleans the SKU.
        *   Makes a GET request to the WooCommerce REST API to find the product by its cleaned SKU.
        *   If the product is found and its stock level (or manage stock status) differs from the CSV:
            *   Makes a PUT request to the WooCommerce REST API to update the product's stock quantity and ensure `manage_stock` is true.
        *   Logs successful updates, skipped products (e.g., SKU not found, stock already matches), and failed API calls.
    *   Generates a styled HTML report (`sync_report_last.html`) summarizing the sync operation.
    *   Logs the report generation to the PHP error log.

## 4. Important Commands & Testing

### 4.1. Checking PHP Setup

*   **Verify PHP version and that it's in PATH:**
    Open Command Prompt: `php -v`
*   **Check loaded `php.ini` by default PHP:**
    Open Command Prompt: `php --ini`
    *(Ideally, this should show `Loaded Configuration File: C:\PHP\php.ini` if `PHPRC` is set correctly and system restarted. If not, the `.bat` file's `-c` flag handles it.)*
*   **Check loaded PHP modules (to confirm cURL is active):**
    Open Command Prompt: `php -m` (Look for `curl`)
*   **Force loading specific `php.ini` and check modules:**
    Open Command Prompt: `C:\PHP\php.exe -c C:\PHP\php.ini -m`
*   **Force loading `php.ini` and test cURL function existence:**
    Open Command Prompt: `C:\PHP\php.exe -c C:\PHP\php.ini -r "echo function_exists('curl_init') ? 'cURL is enabled!' : 'cURL IS NOT ENABLED!!!';"`

### 4.2. Running the Sync Manually

1.  Ensure `C:\IQSync\stock.csv` is present and up-to-date.
2.  Double-click `C:\IQSync\run_sync.bat`.
3.  A command window will appear, show script output, and then pause.
4.  Check `C:\IQSync\sync_report_last.html` in a web browser.

### 4.3. Windows Task Scheduler

*   **Task Name (Example):** "WooCommerce Stock Sync"
*   **Trigger:** Daily, repeating every X minutes.
*   **Action:** Start a program: `C:\IQSync\run_sync.bat`
*   **Run with highest privileges:** Checked.

## 5. Troubleshooting Common Issues

*   **`Fatal error: Call to undefined function curl_init()`:**
    *   cURL extension not enabled in `C:\PHP\php.ini` (ensure `extension=curl` is uncommented).
    *   `extension_dir` not correctly set to `C:\PHP\ext` in `php.ini`.
    *   `php_curl.dll` missing from `C:\PHP\ext\`.
    *   PHP not loading the correct `php.ini` (use `-c` flag in `.bat` file as a fix).
*   **WooCommerce API 401 Unauthorized:**
    *   Incorrect Consumer Key/Secret in `sync_stock.php`.
    *   API Key in WooCommerce does not have "Read/Write" permissions.
    *   API Key in WooCommerce is not associated with an Administrator user.
    *   `.htaccess` or server configuration issues on the WooCommerce site (especially local) blocking Authorization headers.
*   **SKUs Not Found in WooCommerce:**
    *   SKUs in `stock.csv` (CODE column) do not exactly match SKUs in WooCommerce (case-sensitive, check for extra spaces).
    *   Products in WooCommerce are not published or are in draft status.
*   **CSV File Not Found / Cannot Be Read:**
    *   Path in `$csv_path` variable in `sync_stock.php` is incorrect.
    *   Permissions issue on the `stock.csv` file or `C:\IQSync\` folder.
    *   CSV file is malformed.
*   **HTML Report Not Generating/Saving:**
    *   Permissions issue on the `C:\IQSync\` folder preventing `file_put_contents()`.

## 6. Maintenance & Future Considerations

*   **API Key Security:** Regularly review and consider rotating WooCommerce API keys. Avoid committing keys to version control if the script is shared.
*   **Error Log Monitoring:** Regularly check the PHP error log (defined in `php.ini` or script) and the `sync_report_last.html` for any persistent issues.
*   **PHP Updates:** When updating PHP, ensure cURL and any other necessary extensions are re-enabled in the new `php.ini` and that the `ext` folder is correctly populated.
*   **IQ Retail Updates:** Changes to IQ Retail's CSV export format could break the script.
*   **WooCommerce Updates:** Major WooCommerce updates could potentially affect REST API behavior, though this is less common for stable API versions.
*   **Historical Reports:** Modify `sync_stock.php` to save reports with timestamps if a history is needed, instead of overwriting `sync_report_last.html`.
*   **Two-Way Sync:** This solution is one-way. Two-way sync (e.g., sending WooCommerce orders back to IQ Retail) would require significant additional development and use of IQ Retail's import capabilities or API (if available).
*   **Performance for Very Large Catalogs:** For extremely large CSV files (tens of thousands of products), consider optimizing the PHP script further (e.g., processing in smaller batches, more advanced database comparisons if possible).

---