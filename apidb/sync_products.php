<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 0);
set_time_limit(0);

$mysqlHost = 'localhost';

$requestedSite = isset($_GET['site']) ? strtolower(trim($_GET['site'])) : '';

$sources = [
    [
        'dbname'   => 'masterplaques_db',
        'user'     => 'mp_user',
        'pass'     => 'Y!nJ(NjkN-f(',
        'site'     => 'MP',
        'site_key' => 'mp',
        'prefix'   => 'wp_',
        'mode'     => 'mp'
    ],
    [
        'dbname'   => 'plaquesand_db',
        'user'     => 'pnp_user',
        'pass'     => 'PO4aJB7f?yen',
        'site'     => 'PNP',
        'site_key' => 'pnp',
        'prefix'   => 'wp_fx44icf25r_',
        'mode'     => 'pnp'
    ],
    [
        'dbname'   => 'apcfront_db',
        'user'     => 'apcfront_user',
        'pass'     => 'D!;Mh-N2*f9f',
        'site'     => 'APC',
        'site_key' => 'apc',
        'prefix'   => 'wp_',
        'mode'     => 'mp'
    ]
];

$targetDbName = 'apc_pnp_inventory';
$targetDbUser = 'apc_inventory_user';
$targetDbPass = 'h9ocmqp{S6^y';

function writeline2($text)
{
    echo $text . (php_sapi_name() === 'cli' ? "\n" : "<br>");
}

function executeWithRetry(mysqli_stmt $stmt, int $maxRetries = 5, int $sleepMicroseconds = 300000): bool
{
    $attempt = 0;

    while (true) {
        try {
            return $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $attempt++;
            $isRetryable = in_array((int)$e->getCode(), [1205, 1213], true);

            if (!$isRetryable || $attempt > $maxRetries) {
                throw $e;
            }

            usleep($sleepMicroseconds);
        }
    }
}

$tgt = new mysqli($mysqlHost, $targetDbUser, $targetDbPass, $targetDbName);
if ($tgt->connect_error) {
    die("Target DB connection failed: " . $tgt->connect_error);
}
$tgt->set_charset("utf8mb4");

writeline2("Connected to target DB.");

if ($requestedSite !== '') {
    writeline2("Requested site filter: " . strtoupper($requestedSite));
} else {
    writeline2("No site filter provided. Syncing all sites.");
}

/*
IMPORTANT:
Make sure this unique key exists:

ALTER TABLE analytics_products
ADD UNIQUE KEY uniq_site_sku (site, sku);
*/

// prevent overlapping syncs
$lockName = 'sync_products_analytics_lock';
$lockSql = "SELECT GET_LOCK('" . $tgt->real_escape_string($lockName) . "', 10) AS got_lock";
$lockResult = $tgt->query($lockSql);

if (!$lockResult) {
    die("Failed to request lock: " . $tgt->error);
}

$lockRow = $lockResult->fetch_assoc();
$lockResult->free();

if (!isset($lockRow['got_lock']) || (int)$lockRow['got_lock'] !== 1) {
    die("Another product sync is already running.");
}

writeline2("Sync lock acquired.");

// Insert missing sold products only
$insertMissingSql = "
INSERT INTO analytics_products
(
    product_name,
    sku,
    site,
    revenue,
    units_sold,
    aov_contribution,
    date_created
)
SELECT ?, ?, ?, ?, ?, ?, ?
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM analytics_products
    WHERE site = ?
      AND sku = ?
)
";

$insertMissingStmt = $tgt->prepare($insertMissingSql);
if (!$insertMissingStmt) {
    $tgt->query("SELECT RELEASE_LOCK('" . $tgt->real_escape_string($lockName) . "')");
    die("Prepare insertMissingStmt failed: " . $tgt->error);
}

// Update existing sold products
$updateSalesSql = "
UPDATE analytics_products
SET
    product_name = ?,
    revenue = ?,
    units_sold = ?,
    aov_contribution = ?,
    date_created = ?
WHERE site = ?
  AND sku = ?
";

$updateSalesStmt = $tgt->prepare($updateSalesSql);
if (!$updateSalesStmt) {
    $insertMissingStmt->close();
    $tgt->query("SELECT RELEASE_LOCK('" . $tgt->real_escape_string($lockName) . "')");
    die("Prepare updateSalesStmt failed: " . $tgt->error);
}

try {
    foreach ($sources as $srcCfg) {

        if ($requestedSite !== '' && $requestedSite !== $srcCfg['site_key']) {
            continue;
        }

        writeline2("Processing site: " . $srcCfg['site']);

        $src = new mysqli(
            $mysqlHost,
            $srcCfg['user'],
            $srcCfg['pass'],
            $srcCfg['dbname']
        );

        if ($src->connect_error) {
            writeline2("Source DB connection failed for " . $srcCfg['site'] . ": " . $src->connect_error);
            continue;
        }

        $src->set_charset("utf8mb4");
        $prefix = $srcCfg['prefix'];

        writeline2($srcCfg['site'] . " syncing only sold/used products...");

        if ($srcCfg['mode'] === 'mp') {
            $sqlSales = "
            SELECT
                COALESCE(NULLIF(TRIM(vp.post_title), ''), NULLIF(TRIM(pp.post_title), '')) AS product_name,
                TRIM(COALESCE(NULLIF(vsku.meta_value, ''), NULLIF(psku.meta_value, ''))) AS sku,
                COALESCE(SUM(opl.product_net_revenue), 0) AS revenue,
                COALESCE(SUM(opl.product_qty), 0) AS units_sold,
                DATE(COALESCE(vp.post_date, pp.post_date)) AS date_created
            FROM {$prefix}wc_order_product_lookup opl
            LEFT JOIN {$prefix}posts pp
                ON pp.ID = opl.product_id
            LEFT JOIN {$prefix}posts vp
                ON vp.ID = opl.variation_id
            LEFT JOIN {$prefix}postmeta psku
                ON psku.post_id = opl.product_id
               AND psku.meta_key = '_sku'
            LEFT JOIN {$prefix}postmeta vsku
                ON vsku.post_id = opl.variation_id
               AND vsku.meta_key = '_sku'
            INNER JOIN {$prefix}wc_order_stats os
                ON os.order_id = opl.order_id
               AND os.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
            WHERE COALESCE(opl.variation_id, opl.product_id) IS NOT NULL
            GROUP BY
                product_name,
                sku,
                DATE(COALESCE(vp.post_date, pp.post_date))
            HAVING sku IS NOT NULL
               AND sku <> ''
               AND COALESCE(SUM(opl.product_qty), 0) > 0
            ORDER BY product_name ASC
            ";
        } else {
            $sqlSales = "
            SELECT
                COALESCE(NULLIF(TRIM(vp.post_title), ''), NULLIF(TRIM(pp.post_title), ''), oi.order_item_name) AS product_name,
                TRIM(COALESCE(NULLIF(vsku.meta_value, ''), NULLIF(psku.meta_value, ''))) AS sku,
                COALESCE(SUM(
                    CASE
                        WHEN oim_total.meta_value IS NULL OR oim_total.meta_value = '' THEN 0
                        ELSE CAST(oim_total.meta_value AS DECIMAL(18,2))
                    END
                ), 0) AS revenue,
                COALESCE(SUM(
                    CASE
                        WHEN oim_qty.meta_value IS NULL OR oim_qty.meta_value = '' THEN 0
                        ELSE CAST(oim_qty.meta_value AS DECIMAL(18,2))
                    END
                ), 0) AS units_sold,
                DATE(COALESCE(vp.post_date, pp.post_date)) AS date_created
            FROM {$prefix}woocommerce_order_items oi
            INNER JOIN {$prefix}posts p
                ON p.ID = oi.order_id
               AND p.post_type = 'shop_order'
               AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')
            LEFT JOIN {$prefix}woocommerce_order_itemmeta oim_pid
                ON oim_pid.order_item_id = oi.order_item_id
               AND oim_pid.meta_key = '_product_id'
            LEFT JOIN {$prefix}woocommerce_order_itemmeta oim_vid
                ON oim_vid.order_item_id = oi.order_item_id
               AND oim_vid.meta_key = '_variation_id'
            LEFT JOIN {$prefix}woocommerce_order_itemmeta oim_qty
                ON oim_qty.order_item_id = oi.order_item_id
               AND oim_qty.meta_key = '_qty'
            LEFT JOIN {$prefix}woocommerce_order_itemmeta oim_total
                ON oim_total.order_item_id = oi.order_item_id
               AND oim_total.meta_key = '_line_total'
            LEFT JOIN {$prefix}posts pp
                ON pp.ID = CAST(oim_pid.meta_value AS UNSIGNED)
            LEFT JOIN {$prefix}posts vp
                ON vp.ID = CAST(oim_vid.meta_value AS UNSIGNED)
            LEFT JOIN {$prefix}postmeta psku
                ON psku.post_id = pp.ID
               AND psku.meta_key = '_sku'
            LEFT JOIN {$prefix}postmeta vsku
                ON vsku.post_id = vp.ID
               AND vsku.meta_key = '_sku'
            WHERE oi.order_item_type = 'line_item'
            GROUP BY
                product_name,
                sku,
                DATE(COALESCE(vp.post_date, pp.post_date))
            HAVING sku IS NOT NULL
               AND sku <> ''
               AND COALESCE(SUM(
                    CASE
                        WHEN oim_qty.meta_value IS NULL OR oim_qty.meta_value = '' THEN 0
                        ELSE CAST(oim_qty.meta_value AS DECIMAL(18,2))
                    END
               ), 0) > 0
            ORDER BY product_name ASC
            ";
        }

        $resultSales = $src->query($sqlSales);

        if (!$resultSales) {
            writeline2("Sales query failed for " . $srcCfg['site'] . ": " . $src->error);
            $src->close();
            continue;
        }

        writeline2("Rows fetched (" . $srcCfg['site'] . "): " . $resultSales->num_rows);

        $updatedCount = 0;
        $insertedCount = 0;
        $skippedCount = 0;

        while ($row = $resultSales->fetch_assoc()) {
            $productName = trim((string)$row['product_name']);
            $sku = trim((string)$row['sku']);

            if ($sku === '') {
                $skippedCount++;
                continue;
            }

            if ($productName === '') {
                $productName = $sku;
            }

            $revenue = (float)$row['revenue'];
            $unitsSold = (float)$row['units_sold'];
            $aovContribution = ($unitsSold > 0) ? round($revenue / $unitsSold, 2) : 0.00;
            $dateCreated = !empty($row['date_created']) ? $row['date_created'] : null;

            $updateSalesStmt->bind_param(
                "sdddsss",
                $productName,
                $revenue,
                $unitsSold,
                $aovContribution,
                $dateCreated,
                $srcCfg['site'],
                $sku
            );

            try {
                executeWithRetry($updateSalesStmt);
            } catch (mysqli_sql_exception $e) {
                writeline2("Update failed for {$srcCfg['site']} / {$sku}: " . $e->getMessage());
                continue;
            }

            if ($updateSalesStmt->affected_rows > 0) {
                $updatedCount++;
                continue;
            }

            $insertMissingStmt->bind_param(
                "sssdddsss",
                $productName,
                $sku,
                $srcCfg['site'],
                $revenue,
                $unitsSold,
                $aovContribution,
                $dateCreated,
                $srcCfg['site'],
                $sku
            );

            try {
                executeWithRetry($insertMissingStmt);
            } catch (mysqli_sql_exception $e) {
                writeline2("Insert failed for {$srcCfg['site']} / {$sku}: " . $e->getMessage());
                continue;
            }

            if ($insertMissingStmt->affected_rows > 0) {
                $insertedCount++;
            } else {
                $skippedCount++;
            }
        }

        $resultSales->free();

        writeline2("Updated (" . $srcCfg['site'] . "): " . $updatedCount);
        writeline2("Inserted (" . $srcCfg['site'] . "): " . $insertedCount);
        writeline2("Skipped (" . $srcCfg['site'] . "): " . $skippedCount);
        writeline2("");

        $src->close();
    }

    writeline2("ALL DONE");

} finally {
    if (isset($insertMissingStmt) && $insertMissingStmt instanceof mysqli_stmt) {
        $insertMissingStmt->close();
    }

    if (isset($updateSalesStmt) && $updateSalesStmt instanceof mysqli_stmt) {
        $updateSalesStmt->close();
    }

    $tgt->query("SELECT RELEASE_LOCK('" . $tgt->real_escape_string($lockName) . "')");
    writeline2("Sync lock released.");

    $tgt->close();
}