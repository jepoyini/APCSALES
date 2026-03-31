<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 0);
set_time_limit(0);

$mysqlHost = 'localhost';

$requestedSite = isset($_GET['site']) ? strtolower(trim($_GET['site'])) : '';

$sources = [
    [
        'dbname' => 'masterplaques_db',
        'user'   => 'mp_user',
        'pass'   => 'Y!nJ(NjkN-f(',
        'site'   => 'MP',
        'site_key'=> 'mp',
        'prefix' => 'wp_',
        'mode'   => 'mp'
    ],
    [
        'dbname' => 'plaquesand_db',
        'user'   => 'pnp_user',
        'pass'   => 'PO4aJB7f?yen',
        'site'   => 'PNP',
        'site_key'=> 'pnp',
        'prefix' => 'wp_fx44icf25r_',
        'mode'   => 'pnp_2step'
    ],
    [
        'dbname' => 'apcfront_db',
        'user'   => 'apcfront_user',
        'pass'   => 'D!;Mh-N2*f9f',
        'site'   => 'APC',
        'site_key'=> 'apc',
        'prefix' => 'wp_',
        'mode'   => 'mp'
    ]
];

$targetDbName = 'apc_pnp_inventory';
$targetDbUser = 'apc_inventory_user';
$targetDbPass = 'h9ocmqp{S6^y';

$defaultChannel = 'Website';

function writeline1($text)
{
    echo $text . (php_sapi_name() === 'cli' ? "\n" : "<br>");
}

$tgt = new mysqli($mysqlHost, $targetDbUser, $targetDbPass, $targetDbName);
if ($tgt->connect_error) {
    die("Target DB connection failed: " . $tgt->connect_error);
}
$tgt->set_charset("utf8mb4");

writeline1("Connected to target DB.");

if ($requestedSite !== '') {
    writeline1("Requested site filter: " . strtoupper($requestedSite));
} else {
    writeline1("No site filter provided. Syncing all sites.");
}

$upsertSql = "
INSERT INTO analytics_customers
(
    user_id,
    customer_name,
    customer_email,
    site,
    channel,
    orders,
    lifetime_value,
    last_order,
    date_joined
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, ?
)
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    customer_name = VALUES(customer_name),
    customer_email = VALUES(customer_email),
    site = VALUES(site),
    channel = VALUES(channel),
    orders = VALUES(orders),
    lifetime_value = VALUES(lifetime_value),
    last_order = VALUES(last_order),
    date_joined = VALUES(date_joined)
";

$stmt = $tgt->prepare($upsertSql);
if (!$stmt) {
    die("Prepare failed: " . $tgt->error);
}

foreach ($sources as $srcCfg) {

    if ($requestedSite !== '' && $requestedSite !== $srcCfg['site_key']) {
        continue;
    }

    writeline1("Processing site: " . $srcCfg['site']);

    $src = new mysqli(
        $mysqlHost,
        $srcCfg['user'],
        $srcCfg['pass'],
        $srcCfg['dbname']
    );

    if ($src->connect_error) {
        writeline1("Source DB connection failed for " . $srcCfg['site'] . ": " . $src->connect_error);
        continue;
    }

    $src->set_charset("utf8mb4");
    $prefix = $srcCfg['prefix'];

    // =========================
    // MP / APC LOGIC
    // =========================
    if ($srcCfg['mode'] === 'mp') {
        $sql = "
        SELECT
            u.ID AS wp_user_id,
            TRIM(u.user_email) AS customer_email,
            DATE(u.user_registered) AS date_joined,
            TRIM(CONCAT(
                COALESCE(NULLIF(um_fn.meta_value, ''), ''),
                ' ',
                COALESCE(NULLIF(um_ln.meta_value, ''), '')
            )) AS full_name,
            COUNT(os.order_id) AS orders,
            COALESCE(SUM(os.total_sales), 0) AS lifetime_value,
            MAX(DATE(os.date_created)) AS last_order
        FROM {$prefix}users u

        LEFT JOIN {$prefix}usermeta um_fn
            ON um_fn.user_id = u.ID
           AND um_fn.meta_key = 'first_name'

        LEFT JOIN {$prefix}usermeta um_ln
            ON um_ln.user_id = u.ID
           AND um_ln.meta_key = 'last_name'

        LEFT JOIN {$prefix}wc_order_stats os
            ON os.customer_id = u.ID
           AND os.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')

        GROUP BY u.ID, u.user_email, DATE(u.user_registered), full_name
        ORDER BY u.ID ASC
        ";

        $result = $src->query($sql);

        if (!$result) {
            writeline1("Query failed for " . $srcCfg['site'] . ": " . $src->error);
            $src->close();
            continue;
        }

        writeline1("Rows fetched (" . $srcCfg['site'] . "): " . $result->num_rows);

        $synced = 0;
        $skipped = 0;

        while ($row = $result->fetch_assoc()) {
            $userId = !empty($row['wp_user_id']) ? (int)$row['wp_user_id'] : null;
            $email = trim((string)$row['customer_email']);

            if ($email === '') {
                $skipped++;
                continue;
            }

            $name = trim((string)$row['full_name']);
            if ($name === '') {
                $name = $email;
            }

            $orders = (int)$row['orders'];
            $lifetimeValue = (float)$row['lifetime_value'];
            $lastOrder = !empty($row['last_order']) ? $row['last_order'] : null;
            $dateJoined = !empty($row['date_joined']) ? $row['date_joined'] : null;

            $stmt->bind_param(
                "issssidss",
                $userId,
                $name,
                $email,
                $srcCfg['site'],
                $defaultChannel,
                $orders,
                $lifetimeValue,
                $lastOrder,
                $dateJoined
            );

            if (!$stmt->execute()) {
                writeline1("Upsert failed for {$srcCfg['site']} / {$email}: " . $stmt->error);
                continue;
            }

            $synced++;
        }

        writeline1("Synced (" . $srcCfg['site'] . "): " . $synced);
        writeline1("Skipped (" . $srcCfg['site'] . "): " . $skipped);
        writeline1("");

        $result->free();
        $src->close();
        continue;
    }

    // =========================
    // PNP 2-STEP LOGIC
    // =========================
    if ($srcCfg['mode'] === 'pnp_2step') {

        // ---------------------------------
        // STEP 1: ALL USERS FROM users TABLE
        // ---------------------------------
        writeline1("PNP Step 1: syncing all users from users table...");

        $sqlUsers = "
        SELECT
            u.ID AS wp_user_id,
            TRIM(u.user_email) AS customer_email,
            DATE(u.user_registered) AS date_joined,
            TRIM(CONCAT(
                COALESCE(NULLIF(um_fn.meta_value, ''), ''),
                ' ',
                COALESCE(NULLIF(um_ln.meta_value, ''), '')
            )) AS full_name
        FROM {$prefix}users u
        LEFT JOIN {$prefix}usermeta um_fn
            ON um_fn.user_id = u.ID
           AND um_fn.meta_key = 'first_name'
        LEFT JOIN {$prefix}usermeta um_ln
            ON um_ln.user_id = u.ID
           AND um_ln.meta_key = 'last_name'
        ORDER BY u.ID ASC
        ";

        $resultUsers = $src->query($sqlUsers);

        if (!$resultUsers) {
            writeline1("PNP Step 1 query failed: " . $src->error);
            $src->close();
            continue;
        }

        writeline1("PNP Step 1 rows fetched: " . $resultUsers->num_rows);

        $syncedStep1 = 0;
        $skippedStep1 = 0;

        while ($row = $resultUsers->fetch_assoc()) {
            $userId = !empty($row['wp_user_id']) ? (int)$row['wp_user_id'] : null;
            $email = trim((string)$row['customer_email']);

            if ($email === '') {
                $skippedStep1++;
                continue;
            }

            $name = trim((string)$row['full_name']);
            if ($name === '') {
                $name = $email;
            }

            $orders = 0;
            $lifetimeValue = 0.00;
            $lastOrder = null;
            $dateJoined = !empty($row['date_joined']) ? $row['date_joined'] : null;

            $stmt->bind_param(
                "issssidss",
                $userId,
                $name,
                $email,
                $srcCfg['site'],
                $defaultChannel,
                $orders,
                $lifetimeValue,
                $lastOrder,
                $dateJoined
            );

            if (!$stmt->execute()) {
                writeline1("PNP Step 1 upsert failed for {$email}: " . $stmt->error);
                continue;
            }

            $syncedStep1++;
        }

        $resultUsers->free();

        writeline1("PNP Step 1 synced: " . $syncedStep1);
        writeline1("PNP Step 1 skipped: " . $skippedStep1);
        writeline1("");

        // ---------------------------------
        // STEP 2: CURRENT PNP ORDER APPROACH
        // ---------------------------------
        writeline1("PNP Step 2: syncing order totals...");

        $sqlOrders = "
        SELECT
            u.ID AS wp_user_id,
            DATE(u.user_registered) AS date_joined,
            TRIM(
                CASE
                    WHEN TRIM(CONCAT(
                        COALESCE(NULLIF(um_fn.meta_value, ''), ''),
                        ' ',
                        COALESCE(NULLIF(um_ln.meta_value, ''), '')
                    )) <> ''
                    THEN TRIM(CONCAT(
                        COALESCE(NULLIF(um_fn.meta_value, ''), ''),
                        ' ',
                        COALESCE(NULLIF(um_ln.meta_value, ''), '')
                    ))
                    ELSE TRIM(COALESCE(NULLIF(MAX(pm_email.meta_value), ''), u.user_email))
                END
            ) AS full_name,
            TRIM(COALESCE(NULLIF(MAX(pm_email.meta_value), ''), u.user_email)) AS customer_email,
            COUNT(DISTINCT p.ID) AS orders,
            COALESCE(SUM(
                CASE
                    WHEN pm_total.meta_value IS NULL OR pm_total.meta_value = '' THEN 0
                    ELSE CAST(pm_total.meta_value AS DECIMAL(18,2))
                END
            ), 0) AS lifetime_value,
            MAX(DATE(p.post_date)) AS last_order
        FROM {$prefix}postmeta pm_customer

        INNER JOIN {$prefix}posts p
            ON p.ID = pm_customer.post_id
           AND p.post_type = 'shop_order'
           AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')

        INNER JOIN {$prefix}users u
            ON u.ID = CAST(pm_customer.meta_value AS UNSIGNED)

        LEFT JOIN {$prefix}usermeta um_fn
            ON um_fn.user_id = u.ID
           AND um_fn.meta_key = 'first_name'

        LEFT JOIN {$prefix}usermeta um_ln
            ON um_ln.user_id = u.ID
           AND um_ln.meta_key = 'last_name'

        LEFT JOIN {$prefix}postmeta pm_email
            ON pm_email.post_id = p.ID
           AND pm_email.meta_key = '_billing_email'

        LEFT JOIN {$prefix}postmeta pm_total
            ON pm_total.post_id = p.ID
           AND pm_total.meta_key = '_order_total'

        WHERE pm_customer.meta_key = '_customer_user'
          AND pm_customer.meta_value IS NOT NULL
          AND pm_customer.meta_value <> ''
          AND pm_customer.meta_value <> '0'

        GROUP BY
            u.ID,
            DATE(u.user_registered),
            u.user_email,
            um_fn.meta_value,
            um_ln.meta_value

        HAVING customer_email IS NOT NULL
           AND customer_email <> ''

        ORDER BY last_order DESC, u.ID ASC
        ";

        $resultOrders = $src->query($sqlOrders);

        if (!$resultOrders) {
            writeline1("PNP Step 2 query failed: " . $src->error);
            $src->close();
            continue;
        }

        writeline1("PNP Step 2 rows fetched: " . $resultOrders->num_rows);

        $syncedStep2 = 0;
        $skippedStep2 = 0;

        while ($row = $resultOrders->fetch_assoc()) {
            $userId = !empty($row['wp_user_id']) ? (int)$row['wp_user_id'] : null;
            $email = trim((string)$row['customer_email']);

            if ($email === '') {
                $skippedStep2++;
                continue;
            }

            $name = trim((string)$row['full_name']);
            if ($name === '') {
                $name = $email;
            }

            $orders = (int)$row['orders'];
            $lifetimeValue = (float)$row['lifetime_value'];
            $lastOrder = !empty($row['last_order']) ? $row['last_order'] : null;
            $dateJoined = !empty($row['date_joined']) ? $row['date_joined'] : null;

            $stmt->bind_param(
                "issssidss",
                $userId,
                $name,
                $email,
                $srcCfg['site'],
                $defaultChannel,
                $orders,
                $lifetimeValue,
                $lastOrder,
                $dateJoined
            );

            if (!$stmt->execute()) {
                writeline1("PNP Step 2 upsert failed for {$email}: " . $stmt->error);
                continue;
            }

            $syncedStep2++;
        }

        $resultOrders->free();

        writeline1("PNP Step 2 synced: " . $syncedStep2);
        writeline1("PNP Step 2 skipped: " . $skippedStep2);
        writeline1("");

        $src->close();
        continue;
    }

    $src->close();
}

$stmt->close();
$tgt->close();

writeline1("ALL DONE");