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

$defaultChannel = 'Website';

function writeline3($text)
{
    echo $text . (php_sapi_name() === 'cli' ? "\n" : "<br>");
}

function normalizeStatus($status)
{
    $status = trim((string)$status);
    $status = preg_replace('/^wc-/', '', $status);
    $status = str_replace('-', ' ', $status);
    return ucwords($status);
}

$tgt = new mysqli($mysqlHost, $targetDbUser, $targetDbPass, $targetDbName);
if ($tgt->connect_error) {
    die("Target DB connection failed: " . $tgt->connect_error);
}
$tgt->set_charset("utf8mb4");

writeline3("Connected to target DB.");

if ($requestedSite !== '') {
    writeline3("Requested site filter: " . strtoupper($requestedSite));
} else {
    writeline3("No site filter provided. Syncing all sites.");
}

/*
IMPORTANT:
Add a unique key first so ON DUPLICATE KEY works properly.

ALTER TABLE analytics_orders
ADD UNIQUE KEY uniq_site_order_number (site, order_number);
*/

$upsertSql = "
INSERT INTO analytics_orders
(
    order_number,
    customer_name,
    customer_email,
    site,
    channel,
    total,
    status,
    payment_method,
    order_date,
    created_at
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
)
ON DUPLICATE KEY UPDATE
    customer_name   = VALUES(customer_name),
    customer_email  = VALUES(customer_email),
    channel         = VALUES(channel),
    total           = VALUES(total),
    status          = VALUES(status),
    payment_method  = VALUES(payment_method),
    order_date      = VALUES(order_date),
    created_at      = NOW()
";

$stmt = $tgt->prepare($upsertSql);
if (!$stmt) {
    die("Prepare failed: " . $tgt->error);
}

foreach ($sources as $srcCfg) {

    if ($requestedSite !== '' && $requestedSite !== $srcCfg['site_key']) {
        continue;
    }

    writeline3("Processing site: " . $srcCfg['site']);

    $src = new mysqli(
        $mysqlHost,
        $srcCfg['user'],
        $srcCfg['pass'],
        $srcCfg['dbname']
    );

    if ($src->connect_error) {
        writeline3("Source DB connection failed for " . $srcCfg['site'] . ": " . $src->connect_error);
        continue;
    }

    $src->set_charset("utf8mb4");
    $prefix = $srcCfg['prefix'];

    if ($srcCfg['mode'] === 'mp') {
        $sql = "
        SELECT
            CONCAT('#', os.order_id) AS order_number,
            TRIM(
                CASE
                    WHEN TRIM(CONCAT(
                        COALESCE(NULLIF(bfn.meta_value, ''), ''),
                        ' ',
                        COALESCE(NULLIF(bln.meta_value, ''), '')
                    )) <> ''
                    THEN TRIM(CONCAT(
                        COALESCE(NULLIF(bfn.meta_value, ''), ''),
                        ' ',
                        COALESCE(NULLIF(bln.meta_value, ''), '')
                    ))
                    ELSE TRIM(COALESCE(NULLIF(u.user_email, ''), NULLIF(bem.meta_value, '')))
                END
            ) AS customer_name,
            TRIM(COALESCE(NULLIF(bem.meta_value, ''), u.user_email)) AS customer_email,
            os.total_sales AS total,
            os.status AS status,
            TRIM(COALESCE(NULLIF(pmt.meta_value, ''), pm.meta_value)) AS payment_method,
            DATE(os.date_created) AS order_date
        FROM {$prefix}wc_order_stats os
        LEFT JOIN {$prefix}postmeta pm_customer
            ON pm_customer.post_id = os.order_id
           AND pm_customer.meta_key = '_customer_user'
        LEFT JOIN {$prefix}users u
            ON u.ID = CAST(pm_customer.meta_value AS UNSIGNED)
        LEFT JOIN {$prefix}postmeta bfn
            ON bfn.post_id = os.order_id
           AND bfn.meta_key = '_billing_first_name'
        LEFT JOIN {$prefix}postmeta bln
            ON bln.post_id = os.order_id
           AND bln.meta_key = '_billing_last_name'
        LEFT JOIN {$prefix}postmeta bem
            ON bem.post_id = os.order_id
           AND bem.meta_key = '_billing_email'
        LEFT JOIN {$prefix}postmeta pmt
            ON pmt.post_id = os.order_id
           AND pmt.meta_key = '_payment_method_title'
        LEFT JOIN {$prefix}postmeta pm
            ON pm.post_id = os.order_id
           AND pm.meta_key = '_payment_method'
        WHERE os.status IN ('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-cancelled', 'wc-pending', 'wc-refunded', 'wc-failed')
        ORDER BY os.order_id DESC
        ";
    } else {
        $sql = "
        SELECT
            CONCAT('#', p.ID) AS order_number,
            TRIM(
                CASE
                    WHEN TRIM(CONCAT(
                        COALESCE(NULLIF(pm_fn.meta_value, ''), ''),
                        ' ',
                        COALESCE(NULLIF(pm_ln.meta_value, ''), '')
                    )) <> ''
                    THEN TRIM(CONCAT(
                        COALESCE(NULLIF(pm_fn.meta_value, ''), ''),
                        ' ',
                        COALESCE(NULLIF(pm_ln.meta_value, ''), '')
                    ))
                    ELSE TRIM(COALESCE(NULLIF(pm_email.meta_value, ''), u.user_email))
                END
            ) AS customer_name,
            TRIM(COALESCE(NULLIF(pm_email.meta_value, ''), u.user_email)) AS customer_email,
            CASE
                WHEN pm_total.meta_value IS NULL OR pm_total.meta_value = '' THEN 0
                ELSE CAST(pm_total.meta_value AS DECIMAL(18,2))
            END AS total,
            p.post_status AS status,
            TRIM(COALESCE(NULLIF(pm_payment_title.meta_value, ''), pm_payment.meta_value)) AS payment_method,
            DATE(p.post_date) AS order_date
        FROM {$prefix}posts p
        LEFT JOIN {$prefix}postmeta pm_customer
            ON pm_customer.post_id = p.ID
           AND pm_customer.meta_key = '_customer_user'
        LEFT JOIN {$prefix}users u
            ON u.ID = CAST(pm_customer.meta_value AS UNSIGNED)
        LEFT JOIN {$prefix}postmeta pm_fn
            ON pm_fn.post_id = p.ID
           AND pm_fn.meta_key = '_billing_first_name'
        LEFT JOIN {$prefix}postmeta pm_ln
            ON pm_ln.post_id = p.ID
           AND pm_ln.meta_key = '_billing_last_name'
        LEFT JOIN {$prefix}postmeta pm_email
            ON pm_email.post_id = p.ID
           AND pm_email.meta_key = '_billing_email'
        LEFT JOIN {$prefix}postmeta pm_total
            ON pm_total.post_id = p.ID
           AND pm_total.meta_key = '_order_total'
        LEFT JOIN {$prefix}postmeta pm_payment_title
            ON pm_payment_title.post_id = p.ID
           AND pm_payment_title.meta_key = '_payment_method_title'
        LEFT JOIN {$prefix}postmeta pm_payment
            ON pm_payment.post_id = p.ID
           AND pm_payment.meta_key = '_payment_method'
        WHERE p.post_type = 'shop_order'
          AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-cancelled', 'wc-pending', 'wc-refunded', 'wc-failed')
        ORDER BY p.ID DESC
        ";
    }

    $result = $src->query($sql);

    if (!$result) {
        writeline3("Query failed for " . $srcCfg['site'] . ": " . $src->error);
        $src->close();
        continue;
    }

    writeline3("Rows fetched (" . $srcCfg['site'] . "): " . $result->num_rows);

    $synced = 0;
    $skipped = 0;

    while ($row = $result->fetch_assoc()) {
        $orderNumber = trim((string)$row['order_number']);

        if ($orderNumber === '') {
            $skipped++;
            continue;
        }

        $customerEmail = trim((string)$row['customer_email']);
        $customerName = trim((string)$row['customer_name']);
        if ($customerName === '') {
            $customerName = $customerEmail !== '' ? $customerEmail : 'Guest';
        }

        $total = (float)$row['total'];
        $status = normalizeStatus($row['status']);
        $paymentMethod = trim((string)$row['payment_method']);
        $orderDate = !empty($row['order_date']) ? $row['order_date'] : null;

        $stmt->bind_param(
            "sssssdsss",
            $orderNumber,
            $customerName,
            $customerEmail,
            $srcCfg['site'],
            $defaultChannel,
            $total,
            $status,
            $paymentMethod,
            $orderDate
        );

        if (!$stmt->execute()) {
            writeline3("Upsert failed for {$srcCfg['site']} / {$orderNumber}: " . $stmt->error);
            continue;
        }

        $synced++;
    }

    writeline3("Synced (" . $srcCfg['site'] . "): " . $synced);
    writeline3("Skipped (" . $srcCfg['site'] . "): " . $skipped);
    writeline3("");

    $result->free();
    $src->close();
}

$stmt->close();
$tgt->close();

writeline3("ALL DONE");