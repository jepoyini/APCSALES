<?php
namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class P2P extends ResourceController
{
    protected $format = 'json';

    /* =========================
     * Helpers
     * ========================= */
    private function db() {
        return Database::connect();
    }

    private function userId(): ?int {
        // Prefer header, else session (authUser.id)
        $hdr = $this->request->getHeaderLine('X-User-Id');
        if ($hdr !== '') return (int)$hdr;
        $session = session();
        if ($session && $session->has('authUser')) {
            $obj = $session->get('authUser');
            if (is_array($obj) && isset($obj['id'])) return (int)$obj['id'];
            if (is_object($obj) && isset($obj->id))   return (int)$obj->id;
        }
        return null;
    }

    private function ok($data = [], int $code = 200) {
        return $this->respond($data, $code);
    }

    private function bad($message, int $code = 200) {
        return $this->respond(['success'=>false,'message'=>$message], $code);
    }

    private function ensureAuth(): ?ResponseInterface {
         return null;
        if (!$this->userId()) return $this->bad('Unauthorized', 401);
        return null;
    }

    // Unified POST input (JSON first, then form-encoded)
    private function in(): array {
        $json = $this->request->getJSON(true);
        if (is_array($json)) return $json;
        return (array) $this->request->getPost();
    }
    private function postInt(array $in, string $key, int $default = 0): int {
        return isset($in[$key]) ? (int)$in[$key] : $default;
    }
    private function postStr(array $in, string $key, string $default = ''): string {
        return isset($in[$key]) ? (string)$in[$key] : $default;
    }

    /* =========================
     * Catalogs (POST-only)
     * ========================= */
    public function assets() {
        $rows = $this->db()->query("SELECT id, code, name, precision_dp, is_active FROM p2p_assets WHERE is_active=1 ORDER BY code")->getResultArray();
        return $this->ok(['success'=>true,'assets'=>$rows]);
    }

    public function fiats() {
        $rows = $this->db()->query("SELECT id, code, name, precision_dp, is_active FROM p2p_fiat_currencies WHERE is_active=1 ORDER BY code")->getResultArray();
        return $this->ok(['success'=>true,'fiats'=>$rows]);
    }

    public function paymentMethods() {
        $rows = $this->db()->query("SELECT id, code, label FROM p2p_payment_methods WHERE is_active=1 ORDER BY label")->getResultArray();
        return $this->ok(['success'=>true,'payment_methods'=>$rows]);
    }

    /* =========================
     * User Payment Methods (POST-only)
     * ========================= */
    public function listUserPaymentMethods() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in  = $this->in();
        $uid = $this->postInt($in, 'uid', $this->userId() ?? 0);
        if (!$uid) return $this->bad('uid required');

        $rows = $this->db()->query("
            SELECT upm.id, upm.payment_method_id, pm.code, pm.label,
                   upm.account_label, upm.account_details, upm.is_verified, upm.is_active, upm.created_at
            FROM p2p_user_payment_methods upm
            JOIN p2p_payment_methods pm ON pm.id = upm.payment_method_id
            WHERE upm.user_id = ?
            ORDER BY upm.created_at DESC
        ", [$uid])->getResultArray();

        return $this->ok(['success'=>true,'items'=>$rows]);
    }

    public function createUserPaymentMethod() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();

        $uid   = $this->postInt($in, 'uid', $this->userId() ?? 0);
        $pmId  = $this->postInt($in, 'payment_method_id', 0);
        $label = trim($this->postStr($in, 'account_label', ''));
        $details = $in['account_details'] ?? null;

        if (!$pmId || !$uid || $label === '' || $details === null) {
            return $this->bad('payment_method_id, uid, account_label, account_details are required');
        }

        if (is_string($details)) {
            json_decode($details);
            if (json_last_error() !== JSON_ERROR_NONE) return $this->bad('account_details must be valid JSON');
            $json = $details;
        } else {
            $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            $this->db()->query("
                INSERT INTO p2p_user_payment_methods (user_id, payment_method_id, account_label, account_details)
                VALUES (?,?,?,?)
            ", [$uid, $pmId, $label, $json]);

            return $this->ok(['success'=>true,'id'=>$this->db()->insertID()], 201);
        } catch (\Throwable $e) {
            return $this->bad('Unable to save payment method: '.$e->getMessage());
        }
    }

    public function updateUserPaymentMethod() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in   = $this->in();
        $uid  = $this->postInt($in, 'uid', $this->userId() ?? 0);
        $id   = $this->postInt($in, 'id', 0);
        $label   = $in['account_label'] ?? null;
        $details = $in['account_details'] ?? null;
        $isActive = $in['is_active'] ?? null;

        if (!$uid || !$id) return $this->bad('uid and id are required');
        if ($label === null && $details === null && $isActive === null) return $this->bad('Nothing to update');

        $sets = []; $bind = [];
        if ($label !== null) { $sets[] = "account_label=?"; $bind[] = trim((string)$label); }
        if ($details !== null) {
            $json = is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if (is_string($details)) { json_decode($details); if (json_last_error() !== JSON_ERROR_NONE) return $this->bad('account_details must be valid JSON'); }
            $sets[] = "account_details=?"; $bind[] = $json;
        }
        if ($isActive !== null) { $sets[] = "is_active=?"; $bind[] = (int)$isActive ? 1 : 0; }

        $bind[] = $uid; $bind[] = $id;

        $sql = "UPDATE p2p_user_payment_methods SET ".implode(", ", $sets).", updated_at=NOW()
                WHERE user_id=? AND id=?";
        $this->db()->query($sql, $bind);

        return $this->ok(['success'=>true]);
    }

    public function deleteUserPaymentMethod() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in  = $this->in();
        $uid = $this->postInt($in, 'uid', $this->userId() ?? 0);
        $id  = $this->postInt($in, 'id', 0);
        if (!$uid || !$id) return $this->bad('uid and id required');

        $this->db()->query("DELETE FROM p2p_user_payment_methods WHERE user_id=? AND id=?", [$uid, $id]);
        return $this->ok(['success'=>true]);
    }

    /* =========================
     * Offers (POST-only)
     * ========================= */
    public function listOffers() {
        $in = $this->in();
        $side    = strtoupper($this->postStr($in, 'side', '')); // BUY/SELL
        $assetId = $this->postInt($in, 'asset_id', 0);
        $fiatId  = $this->postInt($in, 'fiat_id', 0);

        $where = ["o.status='ACTIVE'"]; $bind = [];
        if ($side === 'BUY' || $side === 'SELL') { $where[] = "o.side=?"; $bind[] = $side; }
        if ($assetId) { $where[] = "o.asset_id=?"; $bind[] = $assetId; }
        if ($fiatId)  { $where[] = "o.fiat_id=?";  $bind[] = $fiatId; }

        $sql = "
            SELECT o.id, o.user_id, u.username, o.side, o.asset_id, a.code AS asset,
                   o.fiat_id, f.code AS fiat, o.price_type, o.price, o.min_amount_asset,
                   o.max_amount_asset, o.payment_window_minutes, o.terms_text,
                   o.success_count, o.cancel_count, o.created_at, o.updated_at
            FROM p2p_offers o
            JOIN users u ON u.id=o.user_id
            JOIN p2p_assets a ON a.id=o.asset_id
            JOIN p2p_fiat_currencies f ON f.id=o.fiat_id
            WHERE ".implode(" AND ", $where)."
            ORDER BY o.updated_at DESC
            LIMIT 200
        ";
        $rows = $this->db()->query($sql, $bind)->getResultArray();

        // Attach payment methods
        $ids = array_column($rows,'id'); $pmMap = [];
        if ($ids) {
            $inMarks = implode(',', array_fill(0, count($ids), '?'));
            $pmRows = $this->db()->query("
                SELECT opm.offer_id, pm.id AS payment_method_id, pm.code, pm.label
                FROM p2p_offer_payment_methods opm
                JOIN p2p_payment_methods pm ON pm.id = opm.payment_method_id
                WHERE opm.offer_id IN ($inMarks)
            ", $ids)->getResultArray();
            foreach ($pmRows as $r) {
                $pmMap[$r['offer_id']][] = [
                    'payment_method_id'=>$r['payment_method_id'],
                    'code'=>$r['code'],
                    'label'=>$r['label'],
                ];
            }
        }
        foreach ($rows as &$r) $r['payment_methods'] = $pmMap[$r['id']] ?? [];

        return $this->ok(['success'=>true,'offers'=>$rows]);
    }

    public function getOffer() {
        $in = $this->in();
        $id = $this->postInt($in, 'id', 0);
        if (!$id) return $this->bad('id required');

        $db = $this->db();
        $row = $db->query("
            SELECT o.*, a.code AS asset_code, f.code AS fiat_code, u.username
            FROM p2p_offers o
            JOIN p2p_assets a ON a.id=o.asset_id
            JOIN p2p_fiat_currencies f ON f.id=o.fiat_id
            JOIN users u ON u.id=o.user_id
            WHERE o.id=?
        ", [$id])->getRowArray();
        if (!$row) return $this->bad('Offer not found', 404);

        $pms = $db->query("
            SELECT pm.id, pm.code, pm.label
            FROM p2p_offer_payment_methods opm
            JOIN p2p_payment_methods pm ON pm.id=opm.payment_method_id
            WHERE opm.offer_id=?
        ", [$id])->getResultArray();

        $row['payment_methods'] = $pms;
        return $this->ok(['success'=>true,'offer'=>$row]);
    }

    public function createOffer()
    {
        if ($resp = $this->ensureAuth()) return $resp;
       
        $in  = $this->in();
        $uid = (int)($in['uid'] ?? 0);
        $side      = strtoupper((string)($in['side'] ?? ''));                 
        $assetId   = (int)($in['asset_id'] ?? 0);
        $fiatId    = (int)($in['fiat_id'] ?? 0);
        $priceType = strtoupper((string)($in['price_type'] ?? 'FIXED'));      
        $price     = trim((string)($in['price'] ?? ''));                      
        $floatBps  = isset($in['float_bps']) ? (int)$in['float_bps'] : null;  
        $minAmt    = trim((string)($in['min_amount_asset'] ?? ''));
        $maxAmt    = trim((string)($in['max_amount_asset'] ?? ''));
        $window    = (int)($in['payment_window_minutes'] ?? 15);
        $terms     = (string)($in['terms_text'] ?? '');
        $pmIds     = $in['payment_method_ids'] ?? null;                       

        $cmp = function(string $a, string $b, int $scale = 18): int {
            if (function_exists('bccomp')) return bccomp($a, $b, $scale);
            $fa = (float)$a; $fb = (float)$b;
            return ($fa < $fb) ? -1 : (($fa > $fb) ? 1 : 0);
        };

        if (!in_array($side, ['BUY','SELL'], true))            return $this->bad('Invalid side');
        if (!$assetId || !$fiatId)                             return $this->bad('asset_id and fiat_id are required');
        if (!in_array($priceType, ['FIXED','FLOATING'], true)) return $this->bad('Invalid price_type');
        if ($priceType === 'FIXED' && $price === '')           return $this->bad('price is required for FIXED');
        if ($minAmt === '' || $maxAmt === '')                  return $this->bad('min_amount_asset and max_amount_asset are required');
        if (!is_array($pmIds) || count($pmIds) === 0)          return $this->bad('payment_method_ids is required');

        if ($cmp($minAmt, '0', 18) <= 0)                       return $this->bad('min_amount_asset must be > 0');
        if ($cmp($maxAmt, $minAmt, 18) < 0)                    return $this->bad('max_amount_asset must be >= min_amount_asset');

        $db = $this->db();

        $assetOk = $db->query("SELECT 1 FROM p2p_assets WHERE id=? AND is_active=1", [$assetId])->getRowArray();
        $fiatOk  = $db->query("SELECT 1 FROM p2p_fiat_currencies WHERE id=? AND is_active=1", [$fiatId])->getRowArray();
        if (!$assetOk || !$fiatOk) return $this->bad('Invalid asset or fiat');

        $pmIds = array_values(array_unique(array_map('intval', $pmIds)));
        if (!count($pmIds)) return $this->bad('payment_method_ids is required');

        $marks = implode(',', array_fill(0, count($pmIds), '?'));
        $pmCountRow = $db->query(
            "SELECT COUNT(*) AS c FROM p2p_payment_methods WHERE is_active=1 AND id IN ($marks)",
            $pmIds
        )->getRowArray();
        $pmCount = (int)($pmCountRow['c'] ?? 0);
        if ($pmCount !== count($pmIds)) return $this->bad('Some payment methods are invalid/inactive');

        $priceToStore    = $priceType === 'FIXED'    ? $price           : null;
        $floatBpsToStore = $priceType === 'FLOATING' ? ($floatBps ?? 0) : null;
        $windowClamped   = max(5, min(180, $window));

        try {
            // Insert offer
            $db->query("
                INSERT INTO p2p_offers
                    (user_id, side, asset_id, fiat_id, price_type, price, float_bps,
                     min_amount_asset, max_amount_asset, payment_window_minutes, terms_text, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?, 'ACTIVE')
            ", [
                $uid, $side, $assetId, $fiatId, $priceType, $priceToStore, $floatBpsToStore,
                $minAmt, $maxAmt, $windowClamped, $terms
            ]);
            $offerId = (int)$db->insertID();

            // Insert PM links
            foreach ($pmIds as $pmId) {
                $db->query("
                    INSERT INTO p2p_offer_payment_methods(offer_id, payment_method_id)
                    VALUES(?,?)
                    ON DUPLICATE KEY UPDATE offer_id = offer_id
                ", [$offerId, $pmId]);
            }

            return $this->ok(['success'=>true,'offer_id'=>$offerId], 201);

        } catch (\Throwable $e) {
            return $this->bad('Failed to create offer: '.$e->getMessage());
        }
    }


    public function setOfferStatus() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->postInt($in, 'uid', 0);
        $id  = $this->postInt($in, 'id', 0);

        $status = strtoupper($this->postStr($in, 'status', ''));
        if (!$id || !in_array($status, ['ACTIVE','PAUSED','HIDDEN','ARCHIVED'], true)) return $this->bad('Invalid id/status');

        $this->db()->query("UPDATE p2p_offers SET status=?, updated_at=NOW() WHERE id=? AND user_id=?", [$status, $id, $uid]);
        return $this->ok(['success'=>true]);
    }


    public function modifyOffer()
    {
        if ($resp = $this->ensureAuth()) return $resp;
        $uid = $this->userId();
        $in  = $this->in();

        // required
        $offerId = (int)($in['offer_id'] ?? 0);
        if (!$offerId) return $this->bad('offer_id is required');

        // Load & ownership check
        $db  = $this->db();
        $row = $db->query("SELECT * FROM p2p_offers WHERE id=?", [$offerId])->getRowArray();
        if (!$row) return $this->bad('Offer not found', 404);
        if ((int)$row['user_id'] !== (int)$uid) return $this->bad('Not your offer', 403);

        // Collect updatable fields (all optional)
        $priceType = isset($in['price_type']) ? strtoupper((string)$in['price_type']) : null; // FIXED/FLOATING
        $price     = array_key_exists('price', $in) ? (string)$in['price'] : null;            // decimal
        $floatBps  = array_key_exists('float_bps', $in) ? (int)$in['float_bps'] : null;       // int
        $minAmt    = array_key_exists('min_amount_asset', $in) ? (string)$in['min_amount_asset'] : null;
        $maxAmt    = array_key_exists('max_amount_asset', $in) ? (string)$in['max_amount_asset'] : null;
        $window    = array_key_exists('payment_window_minutes', $in) ? (int)$in['payment_window_minutes'] : null;
        $terms     = array_key_exists('terms_text', $in) ? (string)$in['terms_text'] : null;

        // Optional: replace allowed payment methods
        $pmIds     = $in['payment_method_ids'] ?? null; // array<int>

        // ---- Validate fields coherently ----
        // price_type
        if ($priceType !== null && !in_array($priceType, ['FIXED','FLOATING'], true)) {
            return $this->bad('Invalid price_type');
        }

        // If price_type is sent or price/float_bps is sent, enforce combos
        $effectivePriceType = $priceType ?: $row['price_type'];

        if ($effectivePriceType === 'FIXED') {
            // If client is switching to FIXED or sending price under FIXED, require a price value
            if ($priceType === 'FIXED' && ($price === null || $price === '')) {
                return $this->bad('price is required for FIXED');
            }
            // When FIXED, float_bps is not used; we will null it if switched
        } else { // FLOATING
            // For FLOATING, we can ignore 'price' and allow float_bps (default 0)
            if ($priceType === 'FLOATING' && $floatBps === null) {
                $floatBps = 0;
            }
        }

        // Limits validation (only if provided)
        if ($minAmt !== null) {
            if (bccomp($minAmt, '0', 18) <= 0) return $this->bad('min_amount_asset must be > 0');
        }
        if ($maxAmt !== null) {
            $minRef = $minAmt !== null ? $minAmt : $row['min_amount_asset'];
            if (bccomp($maxAmt, $minRef, 18) < 0) return $this->bad('max_amount_asset must be >= min_amount_asset');
        }

        // Window clamp (your UI uses 15/30/45/60/180/360; DB allows 5..360)
        if ($window !== null) {
            $window = max(5, min(360, (int)$window));
        }

        // ---- Build UPDATE dynamically ----
        $sets = [];
        $bind = [];

        if ($priceType !== null) {
            $sets[] = "price_type = ?";
            $bind[] = $priceType;
            if ($priceType === 'FIXED') {
                // moving to FIXED: set price; null float_bps
                $sets[] = "price = ?";
                $bind[] = ($price !== null && $price !== '') ? $price : $row['price']; // fallback to existing if client sent FIXED but omitted price (safety)
                $sets[] = "float_bps = NULL";
            } else { // FLOATING
                $sets[] = "price = NULL";
                $sets[] = "float_bps = ?";
                $bind[] = ($floatBps !== null) ? $floatBps : (int)$row['float_bps'];
            }
        } else {
            // price/float_bps independent changes (keeping same price_type)
            if ($price !== null && $effectivePriceType === 'FIXED') {
                $sets[] = "price = ?";
                $bind[] = $price;
            }
            if ($floatBps !== null && $effectivePriceType === 'FLOATING') {
                $sets[] = "float_bps = ?";
                $bind[] = (int)$floatBps;
            }
        }

        if ($minAmt !== null) { $sets[] = "min_amount_asset = ?"; $bind[] = $minAmt; }
        if ($maxAmt !== null) { $sets[] = "max_amount_asset = ?"; $bind[] = $maxAmt; }
        if ($window !== null) { $sets[] = "payment_window_minutes = ?"; $bind[] = $window; }
        if ($terms !== null)  { $sets[] = "terms_text = ?"; $bind[] = $terms; }

        if ($sets) {
            $sets[] = "updated_at = NOW()";
            $bind[] = $offerId;
            $db->query("UPDATE p2p_offers SET ".implode(', ', $sets)." WHERE id = ?", $bind);
        }

        // ---- Replace payment methods if provided ----
        if (is_array($pmIds)) {
            // Clean & validate pmIds
            $pmIds = array_values(array_unique(array_map('intval', $pmIds)));
            if (!empty($pmIds)) {
                $inMarks = implode(',', array_fill(0, count($pmIds), '?'));
                $count = $db->query("
                    SELECT COUNT(*) c FROM p2p_payment_methods
                     WHERE is_active=1 AND id IN ($inMarks)
                ", $pmIds)->getRow('c');
                if ((int)$count !== count($pmIds)) {
                    return $this->bad('Some payment methods are invalid/inactive');
                }
            }
            // Replace set: delete all then insert provided
            $db->query("DELETE FROM p2p_offer_payment_methods WHERE offer_id = ?", [$offerId]);
            foreach ($pmIds as $pid) {
                $db->query("
                    INSERT INTO p2p_offer_payment_methods(offer_id, payment_method_id)
                    VALUES(?, ?)
                    ON DUPLICATE KEY UPDATE offer_id = offer_id
                ", [$offerId, (int)$pid]);
            }
        }

        return $this->ok(['success' => true, 'offer_id' => $offerId]);
    }

    public function cancelOffer()
    {
        if ($resp = $this->ensureAuth()) return $resp;

        $in  = $this->in();                           // reads JSON/POST as assoc
        $uid = (int)($in['uid']);
        $offerId = (int)($in['offer_id'] ?? 0);

        if (!$offerId) return $this->bad('offer_id is required');

        $db = $this->db();

        // Load the offer & check ownership
        $offer = $db->query("SELECT id, user_id, status FROM p2p_offers WHERE id=?", [$offerId])->getRowArray();
        if (!$offer) return $this->bad('Offer not found', 404);

     

        if ((int)$offer['user_id'] !== (int)$uid) return $this->bad('Not your offer', 403);

        // Disallow cancel if there are open orders on this offer
        // Open = PENDING_PAYMENT, PAID, or DISPUTED
        $open = $db->query("
            SELECT COUNT(*) AS c
              FROM p2p_orders
             WHERE offer_id = ?
               AND status IN ('PENDING_PAYMENT','PAID','DISPUTED')
        ", [$offerId])->getRow('c');

        if ((int)$open > 0) {
            return $this->bad('Cannot cancel: there are open orders on this offer.');
        }

        // Set status to CANCELED (even if it was ACTIVE/PAUSED/HIDDEN/ARCHIVED)
        $db->query("UPDATE p2p_offers SET status='CANCELED', updated_at=NOW() WHERE id=?", [$offerId]);

        return $this->ok(['success' => true]);
    }

    /* =========================
     * Orders & Escrow (POST-only)
     * ========================= */
    public function placeOrder() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->postInt($in, 'uid', 0);
        $offerId = $this->postInt($in, 'offer_id', 0);
        $amountAsset = $this->postStr($in, 'amount_asset', '');
        $paymentMethodId = $this->postInt($in, 'payment_method_id', 0);

        if (!$offerId || !$amountAsset || !$paymentMethodId) {
            return $this->bad('offer_id, amount_asset, payment_method_id are required');
        }

        $db = $this->db();
        $db->transStart();

        // Lock the offer row for consistency
        $offer = $db->query("SELECT * FROM p2p_offers WHERE id=? AND status='ACTIVE' FOR UPDATE", [$offerId])->getRowArray();
        if (!$offer) { $db->transRollback(); return $this->bad('Offer not available', 404); }

        // Validate payment method allowed
        $allowed = $db->query("SELECT 1 FROM p2p_offer_payment_methods WHERE offer_id=? AND payment_method_id=?", [$offerId, $paymentMethodId])->getRowArray();
        if (!$allowed) { $db->transRollback(); return $this->bad('Payment method not allowed for this offer'); }

        // Range check
        if (bccomp($amountAsset, $offer['min_amount_asset'], 18) < 0 || bccomp($amountAsset, $offer['max_amount_asset'], 18) > 0) {
            $db->transRollback(); return $this->bad('Amount out of offer limits');
        }

        // Compute price snapshot & fiat
        $priceSnapshot = (string)$offer['price']; // for FLOATING, compute here if you add oracle
        $fiatAmount = bcmul($amountAsset, $priceSnapshot, 8);

        // Determine buyer/seller based on offer.side
        $buyerId  = $offer['side'] === 'SELL' ? $uid : (int)$offer['user_id'];
        $sellerId = $offer['side'] === 'SELL' ? (int)$offer['user_id'] : $uid;

        // Payment deadline
        $deadline = (new \DateTimeImmutable('+'.$offer['payment_window_minutes'].' minutes'))->format('Y-m-d H:i:s');

        // Create order
        $db->query("
            INSERT INTO p2p_orders(offer_id, asset_id, fiat_id, buyer_id, seller_id, side,
                                   price_snapshot, amount_asset, amount_fiat, status, payment_deadline_at, created_at)
            VALUES(?,?,?,?,?,?,?,?,?,'PENDING_PAYMENT',?, NOW())
        ", [
            $offerId, (int)$offer['asset_id'], (int)$offer['fiat_id'], $buyerId, $sellerId, $offer['side'],
            $priceSnapshot, $amountAsset, $fiatAmount, $deadline
        ]);
        $orderId = (int)$db->insertID();

        // Fund escrow (lock seller funds)
        $db->query("CALL sp_p2p_fund_escrow(?,?,?,?)", [$orderId, $sellerId, (int)$offer['asset_id'], $amountAsset]);

        // Link escrow id to order
        $escrowId = $db->query("SELECT id FROM p2p_escrows WHERE order_id=?", [$orderId])->getRow('id');
        $db->query("UPDATE p2p_orders SET escrow_id=? WHERE id=?", [$escrowId, $orderId]);

        $db->transComplete();
        if (!$db->transStatus()) return $this->bad('Unable to place order (escrow funding failed)');

        return $this->ok(['success'=>true,'order_id'=>$orderId,'payment_deadline_at'=>$deadline], 201);
    }

    public function listOrders() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->userId();
        $role = strtolower($this->postStr($in, 'role', 'all')); // buyer/seller/all
        $status = strtoupper($this->postStr($in, 'status', '')); // optional

        $where = []; $bind = [];
        if ($role === 'buyer') { $where[] = "o.buyer_id=?";  $bind[] = $uid; }
        elseif ($role === 'seller') { $where[] = "o.seller_id=?"; $bind[] = $uid; }
        else { $where[] = "(o.buyer_id=? OR o.seller_id=?)"; $bind[] = $uid; $bind[] = $uid; }

        if ($status !== '') { $where[] = "o.status=?"; $bind[] = $status; }

        $sql = "
            SELECT o.*, a.code AS asset_code, f.code AS fiat_code,
                   ub.username AS buyer_username, us.username AS seller_username
            FROM p2p_orders o
            JOIN p2p_assets a ON a.id=o.asset_id
            JOIN p2p_fiat_currencies f ON f.id=o.fiat_id
            JOIN users ub ON ub.id=o.buyer_id
            JOIN users us ON us.id=o.seller_id
            WHERE ".implode(" AND ", $where)."
            ORDER BY o.created_at DESC
            LIMIT 200
        ";
        $rows = $this->db()->query($sql, $bind)->getResultArray();
        return $this->ok(['success'=>true,'orders'=>$rows]);
    }

    public function getOrder() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $id = $this->postInt($in, 'id', 0);
        $uid = $this->postInt($in, 'uid', 0);

        if (!$id) return $this->bad('id required');

        $row = $this->db()->query("
            SELECT o.*, a.code AS asset_code, f.code AS fiat_code,
                   ub.username AS buyer_username, us.username AS seller_username
            FROM p2p_orders o
            JOIN p2p_assets a ON a.id=o.asset_id
            JOIN p2p_fiat_currencies f ON f.id=o.fiat_id
            JOIN users ub ON ub.id=o.buyer_id
            JOIN users us ON us.id=o.seller_id
            WHERE o.id=? AND (o.buyer_id=? OR o.seller_id=?)
        ", [$id, $uid, $uid])->getRowArray();
        if (!$row) return $this->bad('Order not found', 404);
        return $this->ok(['success'=>true,'order'=>$row]);
    }

    public function markPaid() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid  = $this->postInt($in, 'uid', 0);
        $id  = $this->postInt($in, 'id', 0);
        if (!$id) return $this->bad('id required');

        $db = $this->db();
        $db->transStart();

        $order = $db->query("SELECT * FROM p2p_orders WHERE id=? AND buyer_id=? FOR UPDATE", [$id, $uid])->getRowArray();
        if (!$order) { $db->transRollback(); return $this->bad('Order not found or not buyer', 404); }
        if ($order['status'] !== 'PENDING_PAYMENT') { $db->transRollback(); return $this->bad('Order is not awaiting payment'); }

        $paymentMethodId = $this->postInt($in, 'payment_method_id', 0);
        $userPaymentMethodId = isset($in['user_payment_method_id']) ? (int)$in['user_payment_method_id'] : null;
        $amountPaidFiat = $this->postStr($in, 'amount_paid_fiat', $order['amount_fiat']);
        $reference = $this->postStr($in, 'reference_text', '');
        $note      = $this->postStr($in, 'note', '');

        if ($paymentMethodId) {
            $db->query("
                INSERT INTO p2p_order_payments(order_id, buyer_id, payment_method_id, user_payment_method_id, amount_paid_fiat, reference_text, note)
                VALUES(?,?,?,?,?,?,?)
            ", [$id, $uid, $paymentMethodId, $userPaymentMethodId, $amountPaidFiat, $reference, $note]);
        }
        $db->query("UPDATE p2p_orders SET status='PAID', paid_at=NOW() WHERE id=?", [$id]);

        $db->transComplete();
        if (!$db->transStatus()) return $this->bad('Failed to mark as paid');
        return $this->ok(['success'=>true]);
    }

    public function release() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->postInt($in, 'uid', 0);
        $id  = $this->postInt($in, 'id', 0);
        if (!$id) return $this->bad('id required');

        $db = $this->db();
        $db->transStart();

        $order = $db->query("SELECT * FROM p2p_orders WHERE id=? AND seller_id=? FOR UPDATE", [$id, $uid])->getRowArray();
        if (!$order) { $db->transRollback(); return $this->bad('Order not found or not seller', 404); }
        if (!in_array($order['status'], ['PAID','DISPUTED'], true)) { $db->transRollback(); return $this->bad('Order not in releasable state'); }

        $buyerId = (int)$order['buyer_id'];
        $sellerId = (int)$order['seller_id'];
        $amountAsset = $order['amount_asset'];

        // Release escrow to buyer
        $db->query("CALL sp_p2p_release_escrow(?,?,?,?)", [$id, $buyerId, $sellerId, $amountAsset]);
        $db->query("UPDATE p2p_orders SET status='RELEASED', released_at=NOW() WHERE id=?", [$id]);

        // Transactions (optional)
        $db->query("
            INSERT INTO transactions(user_id, amount, type, reference, meta_json, created_at)
            VALUES(?, ?, 'P2P_RECEIVE', CONCAT('ORDER#', ?), JSON_OBJECT('order_id', ?, 'direction','credit'), NOW())
        ", [$buyerId, $amountAsset, $id, $id]);
        $db->query("
            INSERT INTO transactions(user_id, amount, type, reference, meta_json, created_at)
            VALUES(?, ?, 'P2P_RELEASE', CONCAT('ORDER#', ?), JSON_OBJECT('order_id', ?, 'direction','release'), NOW())
        ", [$sellerId, $amountAsset, $id, $id]);

        $db->transComplete();
        if (!$db->transStatus()) return $this->bad('Release failed');
        return $this->ok(['success'=>true]);
    }

    public function cancel() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->postInt($in, 'uid', 0);
        $id  = $this->postInt($in, 'id', 0);
        if (!$id) return $this->bad('id required');

        $db = $this->db();
        $db->transStart();

        $order = $db->query("SELECT * FROM p2p_orders WHERE id=? AND (buyer_id=? OR seller_id=?) FOR UPDATE", [$id, $uid, $uid])->getRowArray();
        if (!$order) { $db->transRollback(); return $this->bad('Order not found', 404); }
        if ($order['status'] !== 'PENDING_PAYMENT') { $db->transRollback(); return $this->bad('Only PENDING_PAYMENT can be canceled'); }

        $db->query("CALL sp_p2p_refund_escrow(?)", [$id]);
        $db->query("UPDATE p2p_orders SET status='CANCELED', canceled_at=NOW() WHERE id=?", [$id]);

        $db->transComplete();
        if (!$db->transStatus()) return $this->bad('Cancel failed');
        return $this->ok(['success'=>true]);
    }

    public function expire() {
        // Typically called by cron/system with order_id (POST)
        $in = $this->in();
        $id = $this->postInt($in, 'id', 0);
        if (!$id) return $this->bad('id required');

        $db = $this->db();
        $db->transStart();

        $order = $db->query("SELECT * FROM p2p_orders WHERE id=? FOR UPDATE", [$id])->getRowArray();
        if (!$order) { $db->transRollback(); return $this->bad('Order not found', 404); }
        if ($order['status'] !== 'PENDING_PAYMENT') { $db->transRollback(); return $this->bad('Order not in pending state'); }

        if (new \DateTime($order['payment_deadline_at']) > new \DateTime()) {
            $db->transRollback(); return $this->bad('Payment window not yet elapsed');
        }

        $db->query("CALL sp_p2p_refund_escrow(?)", [$id]);
        $db->query("UPDATE p2p_orders SET status='EXPIRED', expired_at=NOW() WHERE id=?", [$id]);

        $db->transComplete();
        if (!$db->transStatus()) return $this->bad('Expire failed');
        return $this->ok(['success'=>true]);
    }

    /* =========================
     * Order Chat (POST-only)
     * ========================= */
    public function listOrderMessages() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->postInt($in, 'uid', 0);
        $orderId = $this->postInt($in, 'orderid', 0);
        if (!$orderId) return $this->bad('order_id required');

        $ok = $this->db()->query("SELECT 1 FROM p2p_orders WHERE id=? AND (buyer_id=? OR seller_id=?)", [$orderId, $uid, $uid])->getRowArray();
        if (!$ok) return $this->bad('Forbidden', 403);

        $rows = $this->db()->query("
            SELECT m.id, m.sender_id, u.username AS sender_username, m.msg_type, m.message_text, m.file_url, m.created_at
            FROM p2p_order_messages m
            JOIN users u ON u.id=m.sender_id
            WHERE m.order_id=?
            ORDER BY m.created_at ASC
            LIMIT 500
        ", [$orderId])->getResultArray();
        return $this->ok(['success'=>true,'messages'=>$rows]);
    }

    public function postOrderMessage() {
        if ($resp = $this->ensureAuth()) return $resp;
        $uid = $this->userId();

        // multipart/form-data expected here
        $orderId = (int)($this->request->getPost('order_id') ?? 0);
        if (!$orderId) return $this->bad('order_id required');

        $ok = $this->db()->query("SELECT 1 FROM p2p_orders WHERE id=? AND (buyer_id=? OR seller_id=?)", [$orderId, $uid, $uid])->getRowArray();
        if (!$ok) return $this->bad('Forbidden', 403);

        $text = (string)$this->request->getPost('message_text');
        $file = $this->request->getFile('file');
        $fileUrl = null;

        if ($file && $file->isValid()) {
            $newName = $file->getRandomName();
            $file->move(WRITEPATH.'uploads/p2p_chat', $newName);
            $fileUrl = base_url('writable/uploads/p2p_chat/'.$newName);
        }

        if ($text === '' && !$fileUrl) return $this->bad('Nothing to send');

        $this->db()->query("
            INSERT INTO p2p_order_messages(order_id, sender_id, msg_type, message_text, file_url)
            VALUES(?, ?, 'TEXT', ?, ?)
        ", [$orderId, $uid, $text ?: null, $fileUrl]);

        return $this->ok(['success'=>true]);
    }

    /* =========================
     * Payment Proof (POST-only, multipart)
     * ========================= */
    public function uploadPaymentProof() {
        if ($resp = $this->ensureAuth()) return $resp;
        $uid = $this->userId();
        $orderId = (int)($this->request->getPost('order_id') ?? 0);
        if (!$orderId) return $this->bad('order_id required');

        $db = $this->db();
        $order = $db->query("SELECT * FROM p2p_orders WHERE id=? AND buyer_id=?", [$orderId, $uid])->getRowArray();
        if (!$order) return $this->bad('Order not found or not buyer', 404);

        $file = $this->request->getFile('proof');
        if (!$file || !$file->isValid()) return $this->bad('Invalid file');

        $newName = $file->getRandomName();
        $file->move(WRITEPATH.'uploads/p2p_payments', $newName);
        $fileUrl = base_url('writable/uploads/p2p_payments/'.$newName);

        $payId = $db->query("SELECT id FROM p2p_order_payments WHERE order_id=? ORDER BY id DESC LIMIT 1", [$orderId])->getRow('id');
        if ($payId) {
            $db->query("UPDATE p2p_order_payments SET proof_file_url=? WHERE id=?", [$fileUrl, $payId]);
        } else {
            $db->query("
                INSERT INTO p2p_order_payments(order_id, buyer_id, payment_method_id, amount_paid_fiat, proof_file_url)
                VALUES(?, ?, 0, ?, ?)
            ", [$orderId, $uid, $order['amount_fiat'], $fileUrl]);
        }
        return $this->ok(['success'=>true,'url'=>$fileUrl]);
    }

    /* =========================
     * Disputes (POST-only)
     * ========================= */
    public function openDispute() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->postInt($in, 'uid', 0);
        $orderId = $this->postInt($in, 'order_id', 0);
        $reason  = $this->postStr($in, 'reason_code', '');
        $desc    = $this->postStr($in, 'description', '');

        if (!$orderId) return $this->bad('order_id required');
        if (!in_array($reason, ['NO_PAYMENT','PARTIAL_PAYMENT','PAYMENT_NOT_RECEIVED','SUSPECT_FRAUD','OTHER'], true)) {
            return $this->bad('Invalid reason_code');
        }

        $db = $this->db();
        $order = $db->query("SELECT * FROM p2p_orders WHERE id=? AND (buyer_id=? OR seller_id=?)", [$orderId, $uid, $uid])->getRowArray();
        if (!$order) return $this->bad('Order not found', 404);
        if (!in_array($order['status'], ['PENDING_PAYMENT','PAID'], true)) return $this->bad('Can only open dispute for PENDING_PAYMENT or PAID');

        $db->transStart();
        $db->query("
            INSERT INTO p2p_disputes(order_id, opened_by, reason_code, description, status)
            VALUES(?,?,?,?, 'OPEN')
        ", [$orderId, $uid, $reason, $desc]);
        $disputeId = (int)$db->insertID();
        $db->query("UPDATE p2p_orders SET status='DISPUTED', disputed_at=NOW() WHERE id=?", [$orderId]);
        $db->transComplete();

        if (!$db->transStatus()) return $this->bad('Failed to open dispute');
        return $this->ok(['success'=>true,'dispute_id'=>$disputeId], 201);
    }

    public function getDispute() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $id = $this->postInt($in, 'id', 0);
        if (!$id) return $this->bad('id required');

        $uid = $this->userId();
        $db = $this->db();
        $disp = $db->query("
            SELECT d.*, o.buyer_id, o.seller_id
            FROM p2p_disputes d
            JOIN p2p_orders o ON o.id=d.order_id
            WHERE d.id=? AND (o.buyer_id=? OR o.seller_id=?)
        ", [$id, $uid, $uid])->getRowArray();
        if (!$disp) return $this->bad('Dispute not found', 404);

        $msgs = $db->query("
            SELECT dm.id, dm.sender_id, u.username AS sender_username, dm.message_text, dm.file_url, dm.created_at
            FROM p2p_dispute_messages dm
            JOIN users u ON u.id=dm.sender_id
            WHERE dm.dispute_id=?
            ORDER BY dm.created_at ASC
        ", [$id])->getResultArray();

        return $this->ok(['success'=>true,'dispute'=>$disp,'messages'=>$msgs]);
    }

    public function postDisputeMessage() {
        if ($resp = $this->ensureAuth()) return $resp;
        $uid = $this->userId();
        // multipart/form-data
        $id  = (int)($this->request->getPost('id') ?? 0);
        if (!$id) return $this->bad('id required');

        $ok = $this->db()->query("
            SELECT 1
            FROM p2p_disputes d
            JOIN p2p_orders o ON o.id=d.order_id
            WHERE d.id=? AND (o.buyer_id=? OR o.seller_id=?)
        ", [$id, $uid, $uid])->getRowArray();
        if (!$ok) return $this->bad('Forbidden', 403);

        $text = (string)$this->request->getPost('message_text');
        $file = $this->request->getFile('file');
        $fileUrl = null;

        if ($file && $file->isValid()) {
            $newName = $file->getRandomName();
            $file->move(WRITEPATH.'uploads/p2p_disputes', $newName);
            $fileUrl = base_url('writable/uploads/p2p_disputes/'.$newName);
        }

        if ($text === '' && !$fileUrl) return $this->bad('Nothing to send');

        $this->db()->query("
            INSERT INTO p2p_dispute_messages(dispute_id, sender_id, message_text, file_url)
            VALUES(?,?,?,?)
        ", [$id, $uid, $text ?: null, $fileUrl]);

        return $this->ok(['success'=>true]);
    }

    // Admin/mod resolves dispute (POST-only)
    public function resolveDispute() {
        // Protect with an admin filter in routes/middleware
        $in = $this->in();
        $resolverId = $this->userId() ?: 0;
        $id = $this->postInt($in, 'id', 0);
        $resolution = strtoupper($this->postStr($in, 'resolution', ''));
        if (!$id || !in_array($resolution, ['RELEASE_TO_BUYER','REFUND_TO_SELLER','CANCELED'], true)) {
            return $this->bad('Invalid id/resolution');
        }

        $db = $this->db();
        $disp = $db->query("SELECT * FROM p2p_disputes WHERE id=? AND status IN ('OPEN','UNDER_REVIEW')", [$id])->getRowArray();
        if (!$disp) return $this->bad('Dispute not found or already resolved', 404);

        $db->transStart();

        $order = $db->query("SELECT * FROM p2p_orders WHERE id=? FOR UPDATE", [(int)$disp['order_id']])->getRowArray();
        if (!$order) { $db->transRollback(); return $this->bad('Order not found', 404); }

        if ($resolution === 'RELEASE_TO_BUYER') {
            $db->query("CALL sp_p2p_release_escrow(?,?,?,?)", [
                (int)$order['id'], (int)$order['buyer_id'], (int)$order['seller_id'], $order['amount_asset']
            ]);
            $db->query("UPDATE p2p_orders SET status='RELEASED', released_at=NOW() WHERE id=?", [(int)$order['id']]);
        } elseif ($resolution === 'REFUND_TO_SELLER') {
            $db->query("CALL sp_p2p_refund_escrow(?)", [(int)$order['id']]);
            $db->query("UPDATE p2p_orders SET status='CANCELED', canceled_at=NOW() WHERE id=?", [(int)$order['id']]);
        } else {
            // CANCELED dispute only — no escrow move
        }

        $db->query("
            UPDATE p2p_disputes
               SET status='RESOLVED', resolution=?, resolved_by=?, updated_at=NOW()
             WHERE id=?
        ", [$resolution, $resolverId, $id]);

        $db->transComplete();
        if (!$db->transStatus()) return $this->bad('Failed to resolve dispute');

        return $this->ok(['success'=>true]);
    }

    /* =========================
     * Ratings (POST-only)
     * ========================= */
    public function rate() {
        if ($resp = $this->ensureAuth()) return $resp;
        $in = $this->in();
        $uid = $this->userId();
        $orderId = $this->postInt($in, 'order_id', 0);
        $score   = $this->postInt($in, 'score', 0);
        $comment = $this->postStr($in, 'comment', '');

        if (!$orderId) return $this->bad('order_id required');
        if ($score < 1 || $score > 5) return $this->bad('Score must be 1..5');

        $db = $this->db();
        $o = $db->query("SELECT * FROM p2p_orders WHERE id=? AND status='RELEASED'", [$orderId])->getRowArray();
        if (!$o) return $this->bad('Order not found or not completed', 404);
        if ($uid !== (int)$o['buyer_id'] && $uid !== (int)$o['seller_id']) return $this->bad('Forbidden', 403);

        $rateeId = ($uid === (int)$o['buyer_id']) ? (int)$o['seller_id'] : (int)$o['buyer_id'];

        try {
            $db->transStart();
            $db->query("
                INSERT INTO p2p_ratings(order_id, rater_id, ratee_id, score, comment)
                VALUES(?,?,?,?,?)
                ON DUPLICATE KEY UPDATE score=VALUES(score), comment=VALUES(comment)
            ", [$orderId, $uid, $rateeId, $score, $comment ?: null]);

            if ($uid === (int)$o['buyer_id']) {
                $db->query("UPDATE p2p_orders SET buyer_rating=? WHERE id=?", [$score, $orderId]);
            } else {
                $db->query("UPDATE p2p_orders SET seller_rating=? WHERE id=?", [$score, $orderId]);
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->bad('Failed to rate: '.$e->getMessage());
        }
        return $this->ok(['success'=>true]);
    }

    public function myOffers()
    {

        //return $this->ok(['success' => true, 'offers' => 'test']);        
        if ($resp = $this->ensureAuth()) return $resp;

        $in  = $this->in(); // reads JSON/POST assoc
        $uid = (int)($in['uid'] ?? $this->userId());

        $includeInactive = !empty($in['include_inactive']);

        $side   = strtoupper(trim((string)($in['side'] ?? '')));    // optional: BUY/SELL
        $status = strtoupper(trim((string)($in['status'] ?? '')));  // optional: ACTIVE/PAUSED/HIDDEN/ARCHIVED/CANCELED

        $where = ["o.user_id = ?"];
        $bind  = [$uid];

        if (!$includeInactive) { $where[] = "o.status = 'ACTIVE'"; }
        if (in_array($side, ['BUY','SELL'], true)) { $where[] = "o.side = ?"; $bind[] = $side; }
        if (in_array($status, ['ACTIVE','PAUSED','HIDDEN','ARCHIVED','CANCELED'], true)) { $where[] = "o.status = ?"; $bind[] = $status; }

        $sql = "
            SELECT o.id, o.user_id, u.username, o.side,
                   o.asset_id, a.code AS asset,
                   o.fiat_id,  f.code AS fiat,
                   o.price_type, o.price, o.float_bps,
                   o.min_amount_asset, o.max_amount_asset,
                   o.payment_window_minutes, o.terms_text,
                   o.status, o.success_count, o.cancel_count,
                   o.created_at, o.updated_at
            FROM p2p_offers o
            JOIN users u ON u.id = o.user_id
            JOIN p2p_assets a ON a.id = o.asset_id
            JOIN p2p_fiat_currencies f ON f.id = o.fiat_id
            WHERE ".implode(' AND ', $where)."
            ORDER BY o.updated_at DESC
            LIMIT 500
        ";
        $rows = $this->db()->query($sql, $bind)->getResultArray();

        // attach payment methods per-offer
        $ids = array_column($rows, 'id');
        $pmMap = [];
        if ($ids) {
            $inMarks = implode(',', array_fill(0, count($ids), '?'));
            $pmRows = $this->db()->query("
                SELECT opm.offer_id, pm.id AS payment_method_id, pm.code, pm.label
                FROM p2p_offer_payment_methods opm
                JOIN p2p_payment_methods pm ON pm.id = opm.payment_method_id
                WHERE opm.offer_id IN ($inMarks)
            ", $ids)->getResultArray();

            foreach ($pmRows as $r) {
                $pmMap[$r['offer_id']][] = [
                    'payment_method_id' => (int)$r['payment_method_id'],
                    'code'  => $r['code'],
                    'label' => $r['label'],
                ];
            }
        }
        foreach ($rows as &$r) {
            $r['payment_methods'] = $pmMap[$r['id']] ?? [];
        }

        return $this->ok(['success' => true, 'offers' => $rows]);
    }
    // public function setOfferStatus($id)
    // {
    //     if ($resp = $this->ensureAuth()) return $resp;
    //     $uid    = $this->userId();
    //     $in     = $this->in();
    //     $status = strtoupper((string)($in['status'] ?? ''));

    //     if (!in_array($status, ['ACTIVE','PAUSED','HIDDEN','ARCHIVED'], true)) {
    //         return $this->bad('Invalid status');
    //     }

    //     $db  = $this->db();
    //     $row = $db->query("SELECT user_id FROM p2p_offers WHERE id=?", [(int)$id])->getRowArray();
    //     if (!$row) return $this->bad('Offer not found', 404);
    //     if ((int)$row['user_id'] !== (int)$uid) return $this->bad('Not your offer', 403);

    //     $db->query("UPDATE p2p_offers SET status=?, updated_at=NOW() WHERE id=?", [$status, (int)$id]);

    //     return $this->ok(['success' => true]);
    // }


}
