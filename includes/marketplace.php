<?php
require_once __DIR__ . '/functions.php';

// =====================================================================
// Trader products
// =====================================================================

function getTraderProducts($limit = null, $categorySlug = null, $search = null, $sort = 'new', $maxPrice = null, $minRating = null, $tab = null) {
    $db = getDB();
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug,
                   u.name as seller_name, u.id as seller_id,
                   (u.top_seller_until IS NOT NULL AND u.top_seller_until > datetime('now')) as is_top_seller,
                   (SELECT AVG(rating) FROM reviews r WHERE r.seller_id = u.id AND r.is_hidden = 0) as seller_rating,
                   (SELECT COUNT(*) FROM reviews r WHERE r.seller_id = u.id AND r.is_hidden = 0) as seller_reviews_count
            FROM trader_products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.is_active = 1 AND p.status = 'approved'";
    $params = [];
    if ($tab === 'free') { $sql .= ' AND p.price = 0'; }
    elseif ($tab === 'paid') { $sql .= ' AND p.price > 0'; }
    if ($categorySlug) { $sql .= ' AND c.slug = ?'; $params[] = $categorySlug; }
    if ($search) { $sql .= ' AND p.title LIKE ?'; $params[] = '%' . $search . '%'; }
    if ($maxPrice !== null && $maxPrice !== '') { $sql .= ' AND p.price <= ?'; $params[] = $maxPrice; }
    // Pinned products always come first (admin-curated), then top-seller boost, then the chosen sort.
    $secondarySort = $sort === 'popular' ? 'p.downloads_count DESC' : ($sort === 'price_asc' ? 'p.price ASC' : ($sort === 'price_desc' ? 'p.price DESC' : ($sort === 'date' ? 'p.created_at DESC' : ($sort === 'random' ? 'RANDOM()' : 'p.sort_order ASC, p.created_at DESC'))));
    $sql .= " ORDER BY p.is_pinned DESC, is_top_seller DESC, $secondarySort";
    if ($limit) $sql .= ' LIMIT ' . (int)$limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if ($minRating) {
        $rows = array_values(array_filter($rows, function ($r) use ($minRating) {
            return ($r['seller_rating'] ?? 0) >= $minRating;
        }));
    }
    return $rows;
}

function getTraderProductBySlug($slug) {
    $stmt = getDB()->prepare('SELECT p.*, c.name as category_name, u.name as seller_name, u.avatar as seller_avatar,
        (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status = "completed") AS completed_orders_count
        FROM trader_products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getTraderProductById($id) {
    $stmt = getDB()->prepare('SELECT * FROM trader_products WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getProductsBySeller($sellerId) {
    $stmt = getDB()->prepare('SELECT p.*, c.name as category_name FROM trader_products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC');
    $stmt->execute([$sellerId]);
    return $stmt->fetchAll();
}

function sellerHasPayoutDetails($user) {
    return !empty($user['card_number']) && !empty($user['full_name']) && !empty($user['contact_info']);
}

// =====================================================================
// Ratings / reviews
// =====================================================================

function getSellerRating($sellerId) {
    $stmt = getDB()->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM reviews WHERE seller_id = ? AND is_hidden = 0');
    $stmt->execute([$sellerId]);
    return $stmt->fetch();
}

function getSellerReviews($sellerId) {
    $stmt = getDB()->prepare('SELECT r.*, u.name as buyer_name FROM reviews r LEFT JOIN users u ON r.buyer_id = u.id WHERE r.seller_id = ? AND r.is_hidden = 0 ORDER BY r.created_at DESC');
    $stmt->execute([$sellerId]);
    return $stmt->fetchAll();
}

// =====================================================================
// Bonuses
// =====================================================================

function getBonusBalance($userId) {
    $stmt = getDB()->prepare('SELECT balance FROM bonuses WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['balance'] : 0;
}

function getBonusThreshold() {
    return (int)setting('bonus_threshold', 10);
}

function bonusSystemEnabled() {
    return setting('bonus_enabled', '1') === '1';
}

function addBonusTransaction($userId, $amount, $type, $description, $orderId = null) {
    $db = getDB();
    $db->prepare('INSERT INTO bonus_transactions (user_id, order_id, amount, type, description) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $orderId, $amount, $type, $description]);
    $stmt = $db->prepare('SELECT id FROM bonuses WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        $db->prepare('UPDATE bonuses SET balance = balance + ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')->execute([$amount, $userId]);
    } else {
        $db->prepare('INSERT INTO bonuses (user_id, balance) VALUES (?, ?)')->execute([$userId, max(0, $amount)]);
    }
    notify($userId, 'bonus_change', ['amount' => $amount, 'description' => $description]);
}

function setBonusBalanceToZero($userId, $description, $orderId = null) {
    $current = getBonusBalance($userId);
    if ($current <= 0) return;
    addBonusTransaction($userId, -$current, 'spending', $description, $orderId);
}

function getBonusHistory($userId) {
    $stmt = getDB()->prepare('SELECT * FROM bonus_transactions WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// =====================================================================
// Commission
// =====================================================================

function getCommissionPercentFor($sellerId) {
    $stmt = getDB()->prepare('SELECT commission_percent FROM users WHERE id = ?');
    $stmt->execute([$sellerId]);
    $row = $stmt->fetch();
    $base = ($row && $row['commission_percent'] !== null && $row['commission_percent'] !== '')
        ? (float)$row['commission_percent']
        : (float)setting('commission_percent', 10);

    // v2.1: each active discipline penalty adds a configurable percentage step, capped at a maximum.
    if (function_exists('countActivePenalties')) {
        $penaltyCount = countActivePenalties($sellerId);
        if ($penaltyCount > 0) {
            $step = (float)setting('penalty_commission_step', 1);
            $max = (float)setting('penalty_max_commission', 50);
            $base = min($max, $base + $penaltyCount * $step);
        }
    }
    return $base;
}

// =====================================================================
// Orders
// =====================================================================

function getOrderById($id) {
    $stmt = getDB()->prepare('SELECT o.*, p.title as product_title, p.slug as product_slug,
        b.name as buyer_name, b.email as buyer_email, s.name as seller_name, s.email as seller_email
        FROM orders o
        LEFT JOIN trader_products p ON o.product_id = p.id
        LEFT JOIN users b ON o.buyer_id = b.id
        LEFT JOIN users s ON o.seller_id = s.id
        WHERE o.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getOrdersForBuyer($buyerId, $status = null) {
    $sql = 'SELECT o.*, p.title as product_title, p.slug as product_slug, s.name as seller_name
            FROM orders o LEFT JOIN trader_products p ON o.product_id = p.id LEFT JOIN users s ON o.seller_id = s.id
            WHERE o.buyer_id = ?';
    $params = [$buyerId];
    if ($status) { $sql .= ' AND o.status = ?'; $params[] = $status; }
    $sql .= ' ORDER BY o.created_at DESC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getOrdersForSeller($sellerId, $status = null) {
    $sql = 'SELECT o.*, p.title as product_title, p.slug as product_slug, b.name as buyer_name
            FROM orders o LEFT JOIN trader_products p ON o.product_id = p.id LEFT JOIN users b ON o.buyer_id = b.id
            WHERE o.seller_id = ?';
    $params = [$sellerId];
    if ($status) { $sql .= ' AND o.status = ?'; $params[] = $status; }
    $sql .= ' ORDER BY o.created_at DESC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function orderStatusLabel($status) {
    $labels = [
        'awaiting_confirmation' => 'Ожидает подтверждения оплаты',
        'paid' => 'Оплачен',
        'in_progress' => 'В процессе передачи',
        'completed' => 'Завершён',
        'disputed' => 'Открыт спор',
        'refunded' => 'Возврат',
        'cancelled' => 'Отменён',
    ];
    return $labels[$status] ?? $status;
}

/** Create an order for a trader product. Returns [order_id] or ['error' => ...]. */
function getBuyerBonusMaxPercent() { return (int)setting('buyer_bonus_max_percent', 5); }
function getBuyerBonusMaxPoints() { return getBuyerBonusMaxPercent() * 2; }
function validatePromoCode($code,$userId=null,$amount=null,$productId=null){$code=strtoupper(trim((string)$code));if($code===''||setting('promo_enabled','1')!=='1')return null;$st=getDB()->prepare('SELECT * FROM promo_codes WHERE code=? AND is_active=1');$st->execute([$code]);$p=$st->fetch();if(!$p)return null;if(!empty($p['expires_at'])&&strtotime($p['expires_at'])<time())return null;if((int)$p['max_uses']>0&&(int)$p['used_count']>=(int)$p['max_uses'])return null;if($userId){$st=getDB()->prepare('SELECT id FROM promo_code_usages WHERE promo_id=? AND user_id=? LIMIT 1');$st->execute([$p['id'],$userId]);if($st->fetch())return null;}if($amount!==null&&(float)$p['min_order_amount']>0&&(float)$amount<(float)$p['min_order_amount'])return null;
    // Промокод может быть привязан к конкретному товару или к конкретному продавцу
    // (все его товары) — если привязка есть, покупка должна ей соответствовать.
    if (!empty($p['product_id']) && (int)$p['product_id'] !== (int)$productId) return null;
    if (!empty($p['seller_id'])) {
        $product = $productId ? getTraderProductById($productId) : null;
        if (!$product || (int)$product['user_id'] !== (int)$p['seller_id']) return null;
    }
    return $p;}
function calculatePromoDiscount($promo,$amount){if(!$promo)return 0.0;if($promo['discount_type']==='fixed')return min((float)$amount,(float)$promo['discount_value']);return round((float)$amount*(float)$promo['discount_value']/100,2);}
/** Тот же расчёт скидок/комиссии, что и createOrder(), но без записи в БД —
 *  используется для живого предпросмотра цены в модалке покупки (промокод,
 *  бонусы, сумма к переводу через Donation Alerts с учётом комиссии 15%). */
function previewOrderPricing($productId, $buyerId, $bonusesToUse = 0, $promoCode = '') {
    $product = getTraderProductById($productId);
    if (!$product || !$product['is_active']) return ['error' => 'Товар не найден'];
    $amount = (float)$product['price'];
    $bonusBalance = getBonusBalance($buyerId);
    $maxPoints = getBuyerBonusMaxPoints();
    $bonusesToUse = max(0, min((int)$bonusesToUse, $bonusBalance, $maxPoints));
    $bonusesToUse -= $bonusesToUse % 2;
    if (!bonusSystemEnabled()) $bonusesToUse = 0;
    $discountPercent = (int)floor($bonusesToUse / 2);
    $bonusDiscount = round($amount * $discountPercent / 100, 2);
    $afterBonus = max(0, $amount - $bonusDiscount);
    $promoError = null;
    $promo = validatePromoCode($promoCode, $buyerId, $afterBonus, $productId);
    if (trim((string)$promoCode) !== '' && !$promo) {
        $promoError = 'Промокод недействителен, истёк, уже использован или сумма заказа меньше минимальной';
    }
    $promoDiscount = calculatePromoDiscount($promo, $afterBonus);
    $finalAmount = round(max(0, $afterBonus - $promoDiscount), 2);
    $daCommissionPercent = (float)setting('da_commission_percent', 15);
    $daAmount = $daCommissionPercent > 0 ? round($finalAmount / (1 - $daCommissionPercent / 100), 2) : $finalAmount;
    return [
        'amount' => $amount,
        'bonus_discount' => $bonusDiscount,
        'promo_discount' => $promoDiscount,
        'promo_valid' => (bool)$promo,
        'promo_error' => $promoError,
        'final_amount' => $finalAmount,
        'da_amount' => $daAmount,
        'da_commission_percent' => $daCommissionPercent,
    ];
}

function createOrder($buyerId,$productId,$bonusesToUse=0,$promoCode=''){
    $db=getDB();$product=getTraderProductById($productId);if(!$product||!$product['is_active'])return ['error'=>'Товар не найден'];if((int)$product['user_id']===(int)$buyerId)return ['error'=>'Нельзя купить собственный товар'];if((float)$product['price']<=0)return ['error'=>'Этот товар бесплатный'];
    $amount=(float)$product['price'];$bonusBalance=getBonusBalance($buyerId);$maxPercent=getBuyerBonusMaxPercent();$maxPoints=getBuyerBonusMaxPoints();$bonusesToUse=max(0,min((int)$bonusesToUse,$bonusBalance,$maxPoints));$bonusesToUse-=$bonusesToUse%2;if(!bonusSystemEnabled())$bonusesToUse=0;
    $discountPercent=(int)floor($bonusesToUse/2);$bonusDiscount=round($amount*$discountPercent/100,2);$afterBonus=max(0,$amount-$bonusDiscount);$promo=validatePromoCode($promoCode,$buyerId,$afterBonus,$productId);if(trim((string)$promoCode)!==''&&!$promo)return ['error'=>'Промокод недействителен, истёк, уже использован или сумма заказа меньше минимальной для этого промокода'];$promoDiscount=calculatePromoDiscount($promo,$afterBonus);$finalAmount=round(max(0,$afterBonus-$promoDiscount),2);
    $commissionPercent=getCommissionPercentFor($product['user_id']);$pendingReduction=function_exists('getPendingCommissionReduction')?getPendingCommissionReduction($product['user_id']):null;if($pendingReduction)$commissionPercent=max(0,$commissionPercent-(float)$pendingReduction['value']);$commissionAmount=round($finalAmount*$commissionPercent/100,2);$netAmount=max(0,round($finalAmount-$commissionAmount,0));
    $db->prepare('INSERT INTO orders (buyer_id,seller_id,product_id,amount,discount_percent,discount_amount,final_amount,commission_percent,commission_amount,net_amount,promo_code,promo_discount_amount,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"awaiting_confirmation")')->execute([$buyerId,$product['user_id'],$productId,$amount,$discountPercent,round($bonusDiscount+$promoDiscount,2),$finalAmount,$commissionPercent,$commissionAmount,$netAmount,$promo?$promo['code']:null,$promoDiscount]);
    $orderId=$db->lastInsertId();addOrderMessage($orderId,null,'Заказ создан. Ожидайте подтверждения оплаты администратором. Не передавайте товар до получения уведомления о подтверждении.',true);
    if($bonusesToUse>0)addBonusTransaction($buyerId,-$bonusesToUse,'spending',"Скидка {$discountPercent}% на заказ #$orderId",$orderId);
    if($promo){$db->prepare('INSERT INTO promo_code_usages (promo_id,user_id,order_id,discount_amount) VALUES (?,?,?,?)')->execute([$promo['id'],$buyerId,$orderId,$promoDiscount]);$db->prepare('UPDATE promo_codes SET used_count=used_count+1 WHERE id=?')->execute([$promo['id']]);}
    notify($buyerId,'order_created',['order_id'=>$orderId,'product'=>$product['title']]);notify($product['user_id'],'order_created',['order_id'=>$orderId,'product'=>$product['title']]);if($pendingReduction&&function_exists('consumeCommissionReduction'))consumeCommissionReduction($pendingReduction['id']);return ['order_id'=>$orderId];
}

function addOrderMessage($orderId,$senderId,$message,$isSystem=false,$filePath=null,$isAdmin=false){getDB()->prepare('INSERT INTO messages (order_id,sender_id,message,file_path,is_system,is_admin) VALUES (?,?,?,?,?,?)')->execute([$orderId,$senderId,$message,$filePath,$isSystem?1:0,$isAdmin?1:0]);$order=getOrderById($orderId);if($order&&!$isSystem){$recipient=((int)$senderId===(int)$order['buyer_id'])?$order['seller_id']:$order['buyer_id'];notify($recipient,'new_message',['order_id'=>$orderId]);}}
function getOrderMessages($orderId){$st=getDB()->prepare('SELECT m.*,u.name as sender_name,u.role as sender_role FROM messages m LEFT JOIN users u ON m.sender_id=u.id WHERE m.order_id=? ORDER BY m.created_at ASC');$st->execute([$orderId]);return $st->fetchAll();}

/** Admin confirms that payment was received: awaiting_confirmation -> paid */
function confirmOrderPayment($orderId) {
    $db = getDB();
    $order = getOrderById($orderId);
    if (!$order || $order['status'] !== 'awaiting_confirmation') return false;
    $days = (int)setting('auto_confirm_days', 3);
    $autoConfirmAt = date('Y-m-d H:i:s', time() + $days * 86400);
    $db->prepare('UPDATE orders SET status = "paid", auto_confirm_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
        ->execute([$autoConfirmAt, $orderId]);
    addOrderMessage($orderId, null, 'Оплата подтверждена администратором. Можно передавать товар покупателю.', true);
    notify($order['seller_id'], 'order_status', ['order_id' => $orderId, 'status' => 'paid']);
    notify($order['buyer_id'], 'order_status', ['order_id' => $orderId, 'status' => 'paid']);

    if (function_exists('tryAutoDeliver')) {
        tryAutoDeliver($orderId);
    }
    return true;
}

function cancelOrder($orderId) {
    $db = getDB();
    $order = getOrderById($orderId);
    if (!$order || $order['status'] !== 'awaiting_confirmation') return false;
    $db->prepare('UPDATE orders SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$orderId]);
    addOrderMessage($orderId, null, 'Заказ отменён администратором (оплата не подтверждена).', true);
    notify($order['buyer_id'], 'order_status', ['order_id' => $orderId, 'status' => 'cancelled']);
    notify($order['seller_id'], 'order_status', ['order_id' => $orderId, 'status' => 'cancelled']);
    return true;
}

function markOrderInProgress($orderId) {
    $db = getDB();
    $order = getOrderById($orderId);
    if (!$order || $order['status'] !== 'paid') return false;
    $db->prepare('UPDATE orders SET status = "in_progress", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$orderId]);
    return true;
}

/** Buyer confirms receipt, or system auto-confirms. Handles payout + bonus accrual. */
function completeOrder($orderId, $source = 'buyer') {
    $db = getDB();
    $order = getOrderById($orderId);
    if (!$order || !in_array($order['status'], ['paid', 'in_progress', 'disputed'])) return false;

    $db->prepare('UPDATE orders SET status = "completed", buyer_confirmed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$orderId]);

    // Credit seller balance
    creditUserBalance($order['seller_id'], (float)$order['net_amount'], $orderId, 'Продажа: заказ #' . $orderId);

    // Accrue bonuses to buyer only if no discount was used on this order
    if ((int)$order['discount_percent'] === 0 && (float)($order['promo_discount_amount'] ?? 0) <= 0 && bonusSystemEnabled()) {
        $rubPerPoint = (float)setting('bonus_rub_per_point', 100);
        if ($rubPerPoint > 0) {
            $points = (int)floor((float)$order['amount'] / $rubPerPoint);
            if ($points > 0) {
                addBonusTransaction($order['buyer_id'], $points, 'accrual', 'Начислено за заказ #' . $orderId, $orderId);
            }
        }
    }

    $msg = $source === 'auto'
        ? 'Заказ автоматически завершён по истечении срока ожидания подтверждения.'
        : ($source === 'auto_delivery'
            ? 'Товар выдан автоматически. Заказ завершён.'
            : 'Покупатель подтвердил получение товара. Заказ завершён.');
    addOrderMessage($orderId, null, $msg, true);
    notify($order['buyer_id'], 'order_status', ['order_id' => $orderId, 'status' => 'completed']);
    notify($order['seller_id'], 'order_status', ['order_id' => $orderId, 'status' => 'completed']);

    // v2.1: seller trader-bonus accrual, referral processing, task progress (safe no-ops if trader_bonus.php isn't loaded)
    if (function_exists('accrueTraderOrderBonus')) {
        $freshOrder = getOrderById($orderId);
        accrueTraderOrderBonus($freshOrder);
        processReferralOnOrderComplete($freshOrder);
        evaluateTraderTasks($order['seller_id']);
    }
    return true;
}

function creditUserBalance($userId, $amount, $orderId, $description) {
    $db = getDB();
    $db->prepare('INSERT INTO transactions (user_id, order_id, type, amount, description) VALUES (?, ?, "credit", ?, ?)')
        ->execute([$userId, $orderId, $amount, $description]);
    $stmt = $db->prepare('SELECT id FROM user_balances WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        $db->prepare('UPDATE user_balances SET balance = balance + ? WHERE user_id = ?')->execute([$amount, $userId]);
    } else {
        $db->prepare('INSERT INTO user_balances (user_id, balance) VALUES (?, ?)')->execute([$userId, $amount]);
    }
}

function getUserBalance($userId) {
    $stmt = getDB()->prepare('SELECT balance FROM user_balances WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (float)$row['balance'] : 0.0;
}

function openDispute($orderId,$byUserId){$db=getDB();$order=getOrderById($orderId);if(!$order||!in_array($order['status'],['paid','in_progress']))return false;if((int)$order['buyer_id']!==(int)$byUserId&&(int)$order['seller_id']!==(int)$byUserId)return false;$db->prepare('UPDATE orders SET status="disputed",dispute_opened_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$orderId]);addOrderMessage($orderId,null,'⚠️ Спор открыт. Администратор рассмотрит переписку и детали заказа.',true);foreach($db->query("SELECT id FROM users WHERE role IN ('admin','moderator')")->fetchAll() as $a)notify($a['id'],'dispute_opened',['order_id'=>$orderId]);$recipient=(int)$byUserId===(int)$order['buyer_id']?$order['seller_id']:$order['buyer_id'];notify($recipient,'dispute_opened',['order_id'=>$orderId]);return true;}

/** Admin resolves a dispute: 'refund' or 'release' (release = complete order, pay seller). */
function resolveDispute($orderId, $decision) {
    $db = getDB();
    $order = getOrderById($orderId);
    if (!$order || $order['status'] !== 'disputed') return false;
    if ($decision === 'refund') {
        $db->prepare('UPDATE orders SET status = "refunded", dispute_resolved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$orderId]);
        addOrderMessage($orderId, null, 'Спор решён в пользу покупателя: оформлен возврат средств.', true);
        notify($order['buyer_id'], 'dispute_resolved', ['order_id' => $orderId, 'decision' => 'refund']);
        notify($order['seller_id'], 'dispute_resolved', ['order_id' => $orderId, 'decision' => 'refund']);
        return true;
    }
    if ($decision === 'release') {
        $db->prepare('UPDATE orders SET dispute_resolved_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$orderId]);
        completeOrder($orderId, 'admin');
        if (function_exists('accrueTraderDisputeBonus')) {
            accrueTraderDisputeBonus($order['seller_id'], $orderId);
        }
        addOrderMessage($orderId, null, 'Спор решён в пользу продавца: средства зачислены продавцу.', true);
        notify($order['buyer_id'], 'dispute_resolved', ['order_id' => $orderId, 'decision' => 'release']);
        notify($order['seller_id'], 'dispute_resolved', ['order_id' => $orderId, 'decision' => 'release']);
        return true;
    }
    return false;
}

/** Call on order view to auto-complete overdue orders. Returns true if it auto-completed. */
function autoConfirmIfDue($order) {
    if (!$order) return false;
    if (!in_array($order['status'], ['paid', 'in_progress'])) return false;
    if (empty($order['auto_confirm_at'])) return false;
    if (strtotime($order['auto_confirm_at']) > time()) return false;
    return completeOrder($order['id'], 'auto');
}

// =====================================================================
// Reviews
// =====================================================================

function submitReview($orderId, $buyerId, $rating, $comment) {
    $db = getDB();
    $order = getOrderById($orderId);
    if (!$order || $order['status'] !== 'completed') return ['error' => 'Отзыв можно оставить только по завершённому заказу'];
    if ((int)$order['buyer_id'] !== (int)$buyerId) return ['error' => 'Недостаточно прав'];
    $stmt = $db->prepare('SELECT id FROM reviews WHERE order_id = ?');
    $stmt->execute([$orderId]);
    if ($stmt->fetch()) return ['error' => 'Отзыв уже оставлен'];
    $rating = max(1, min(5, (int)$rating));
    $db->prepare('INSERT INTO reviews (order_id, seller_id, buyer_id, rating, comment) VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, $order['seller_id'], $buyerId, $rating, trim($comment) ?: null]);
    $reviewId = $db->lastInsertId();
    notify($order['seller_id'], 'new_review', ['order_id' => $orderId]);
    if (function_exists('accrueTraderReviewBonus')) {
        accrueTraderReviewBonus($order['seller_id'], $reviewId, $rating);
        evaluateTraderTasks($order['seller_id']);
    }
    return ['ok' => true];
}

// =====================================================================
// Seller pre-sale messages ("Задать вопрос продавцу")
// =====================================================================

function sendSellerQuestion($sellerId,$buyerId,$productId,$message,$attachmentPath=null){$db=getDB();$db->prepare('INSERT INTO seller_messages (seller_id,buyer_id,product_id,message,sender_id,attachment_path,is_admin) VALUES (?,?,?,?,?,?,0)')->execute([$sellerId,$buyerId,$productId,trim($message),$buyerId,$attachmentPath]);$p=getTraderProductById($productId);notify($sellerId,'seller_question',['product_id'=>$productId,'product'=>$p['title']??'']);return true;}
function getSellerMessages($sellerId){$st=getDB()->prepare('SELECT sm.*,u.name as buyer_name,p.title as product_title FROM seller_messages sm LEFT JOIN users u ON sm.buyer_id=u.id LEFT JOIN trader_products p ON sm.product_id=p.id WHERE sm.seller_id=? ORDER BY sm.created_at DESC');$st->execute([$sellerId]);return $st->fetchAll();}
/** Чаты, где текущий пользователь — покупатель, задающий вопрос продавцам (а не наоборот). */
function getBuyerChats($buyerId){$st=getDB()->prepare('SELECT sm.*,u.name as seller_name,p.title as product_title,p.slug as product_slug FROM seller_messages sm LEFT JOIN users u ON sm.seller_id=u.id LEFT JOIN trader_products p ON sm.product_id=p.id WHERE sm.buyer_id=? ORDER BY sm.created_at DESC');$st->execute([$buyerId]);return $st->fetchAll();}
function getSellerChatMessages($productId,$buyerId,$sellerId){$st=getDB()->prepare('SELECT sm.*,u.name as sender_name,u.role as sender_role FROM seller_messages sm LEFT JOIN users u ON sm.sender_id=u.id WHERE sm.product_id=? AND sm.buyer_id=? AND sm.seller_id=? ORDER BY sm.created_at ASC');$st->execute([$productId,$buyerId,$sellerId]);$rows=$st->fetchAll();$out=[];foreach($rows as $r){if(empty($r['sender_id'])){$r['sender_id']=$buyerId;$r['sender_name']=$r['buyer_name']??'Покупатель';$out[]=$r;if(!empty($r['response'])){$x=$r;$x['id']=(int)$r['id'].'r';$x['sender_id']=$sellerId;$st2=getDB()->prepare('SELECT name,role FROM users WHERE id=?');$st2->execute([$sellerId]);$su=$st2->fetch();$x['sender_name']=$su['name']??'Продавец';$x['message']=$r['response'];$x['attachment_path']=null;$x['created_at']=$r['updated_at']??$r['created_at'];$out[]=$x;}}else{$out[]=$r;}}usort($out,function($a,$b){return strcmp($a['created_at'],$b['created_at']);});return $out;}
function sendSellerChatMessage($sellerId,$buyerId,$productId,$senderId,$message,$attachmentPath=null,$isAdmin=false){$message=trim($message);if($message===''&&!$attachmentPath)return false;getDB()->prepare('INSERT INTO seller_messages (seller_id,buyer_id,product_id,message,sender_id,attachment_path,is_admin) VALUES (?,?,?,?,?,?,?)')->execute([$sellerId,$buyerId,$productId,$message,$senderId,$attachmentPath,$isAdmin?1:0]);$recipient=(int)$senderId===(int)$sellerId?$buyerId:$sellerId;$p=getTraderProductById($productId);notify($recipient,'seller_question',['product_id'=>$productId,'product'=>$p['title']??'']);return true;}
function replySellerMessage($id,$sellerId,$response,$attachmentPath=null){$st=getDB()->prepare('SELECT * FROM seller_messages WHERE id=? AND seller_id=?');$st->execute([$id,$sellerId]);$m=$st->fetch();if(!$m)return false;return sendSellerChatMessage($m['seller_id'],$m['buyer_id'],$m['product_id'],$sellerId,$response,$attachmentPath,false);}

/** Handles POST for seller product create/edit (GET falls through to render the form page). */
function handleTraderProductForm($productId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_GET['id'] = $productId;
        require __DIR__ . '/../seller/product_form.php';
        return;
    }
    $db = getDB();
    $userId = $_SESSION['user_id'];
    if (isBanned()) {
        $_SESSION['flash'] = 'Ваш аккаунт заблокирован, публикация товаров недоступна';
        redirect('/seller/products');
    }

    if ($productId) {
        $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ? AND user_id = ?');
        $stmt->execute([$productId, $userId]);
        if (!$stmt->fetch()) { redirect('/seller/products'); }
    }

    $price = (float)($_POST['price'] ?? 0);
    if ($price > 0) {
        $stmt = $db->prepare('SELECT card_number, full_name, contact_info FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!sellerHasPayoutDetails($u)) {
            $_SESSION['flash'] = 'Для платных товаров сначала заполните реквизиты для выплат';
            redirect('/seller/settings');
        }
    }

    $cover = uploadFile($_FILES['cover'] ?? null, 'covers');
    $screenshots = [];
    if (!empty($_FILES['screenshots']['tmp_name'][0])) {
        foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmp) {
            if ($_FILES['screenshots']['error'][$i] === UPLOAD_ERR_OK) {
                $f = ['tmp_name' => $tmp, 'name' => $_FILES['screenshots']['name'][$i], 'error' => 0];
                $s = uploadFile($f, 'screenshots');
                if ($s) $screenshots[] = $s;
            }
        }
    }
    // Если товар создаётся из шаблона и продавец не загрузил новую обложку/
    // скриншоты — используем те, что сохранены в шаблоне.
    if (!$productId && !empty($_POST['template_id'])) {
        $tpl = getTraderProductTemplateById((int)$_POST['template_id'], $userId);
        if ($tpl) {
            if (!$cover && !empty($_POST['template_cover']) && $_POST['template_cover'] === $tpl['cover_path']) {
                $cover = $tpl['cover_path'];
            }
            if (!$screenshots && !empty($_POST['template_screenshots'])) {
                $decoded = json_decode($_POST['template_screenshots'], true);
                if (is_array($decoded) && $decoded === (json_decode($tpl['screenshots'] ?? '[]', true) ?: [])) {
                    $screenshots = $decoded;
                }
            }
        }
    }
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?: slugify($title));
    if ($slug === '') { $slug = 'item-' . substr(md5($title . microtime()), 0, 8); }
    // Гарантируем уникальность: два товара с одинаковым названием раньше получали
    // одинаковый slug, и ссылка на второй молча вела на первый (первый найденный
    // по SELECT ... WHERE slug = ?). Дописываем -2, -3 и т.д. при коллизии.
    $baseSlug = $slug; $suffix = 2;
    while (true) {
        $stmt = $db->prepare('SELECT id FROM trader_products WHERE slug = ?' . ($productId ? ' AND id != ?' : ''));
        $stmt->execute($productId ? [$slug, $productId] : [$slug]);
        if (!$stmt->fetch()) break;
        $slug = $baseSlug . '-' . $suffix; $suffix++;
    }

    if ($productId) {
        $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ? AND user_id = ?');
        $stmt->execute([$productId, $userId]);
        $oldProduct = $stmt->fetch();
        if (!$oldProduct) { redirect('/seller/products'); }

        // Any edit to an approved or rejected product sends it back for re-moderation.
        $newStatus = in_array($oldProduct['status'], ['approved', 'rejected'], true) ? 'on_moderation' : $oldProduct['status'];

        $sql = 'UPDATE trader_products SET title=?, slug=?, description=?, category_id=?, price=?, file_url=?, youtube_url=?, game_version=?, file_size=?, is_active=?, status=?, version=version+1, updated_at=CURRENT_TIMESTAMP';
        $params = [$title, $slug, $_POST['description'] ?? '', $_POST['category_id'] ?: null, $price, $_POST['file_url'] ?? '', $_POST['youtube_url'] ?: null, $_POST['game_version'] ?: null, $_POST['file_size'] ?: null, isset($_POST['is_active']) ? 1 : 0, $newStatus];
        if ($cover) { $sql .= ', cover_path=?'; $params[] = $cover; }
        if ($screenshots) { $sql .= ', screenshots=?'; $params[] = json_encode(array_merge(json_decode($oldProduct['screenshots'] ?? '[]', true) ?: [], $screenshots)); }
        $sql .= ' WHERE id = ? AND user_id = ?';
        $params[] = $productId; $params[] = $userId;
        $db->prepare($sql)->execute($params);

        $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ?');
        $stmt->execute([$productId]);
        logProductHistory($productId, $userId, $oldProduct, $stmt->fetch());

        $_SESSION['flash'] = $newStatus === 'on_moderation' ? 'Товар отправлен на повторную модерацию' : 'Товар сохранён';
        redirect('/seller/products');
    } else {
        $action = $_POST['action'] ?? 'submit';
        $status = $action === 'draft' ? 'draft' : 'on_moderation';
        $stmt = $db->prepare('INSERT INTO trader_products (user_id, title, slug, description, category_id, price, file_url, youtube_url, cover_path, screenshots, game_version, file_size, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $slug, $_POST['description'] ?? '', $_POST['category_id'] ?: null, $price, $_POST['file_url'] ?? '', $_POST['youtube_url'] ?: null, $cover, $screenshots ? json_encode($screenshots) : null, $_POST['game_version'] ?: null, $_POST['file_size'] ?: null, $status]);
        $newId = $db->lastInsertId();
        if ($status === 'on_moderation') {
            notify($userId, 'product_moderation', ['product_id' => $newId, 'title' => $title, 'decision' => 'submitted']);
        }
        $_SESSION['flash'] = $status === 'draft' ? 'Товар сохранён как черновик' : 'Товар отправлен на модерацию. Ожидайте решения администратора.';
        redirect('/seller/products');
    }
}

// =====================================================================
// Seller product templates
// =====================================================================
// Позволяют торговцу сохранить набор часто повторяющихся полей товара
// (описание, категория, версия игры, обложку и т.д.) и быстро подставить
// их при создании нового товара.

function getTraderProductTemplates($userId) {
    $stmt = getDB()->prepare('SELECT t.*, c.name as category_name FROM trader_product_templates t LEFT JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? ORDER BY t.updated_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getTraderProductTemplateById($id, $userId) {
    $stmt = getDB()->prepare('SELECT * FROM trader_product_templates WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $tpl = $stmt->fetch();
    return $tpl ?: null;
}

/** Handles GET (render form) and POST (create/update) for a seller product template. */
function handleTraderProductTemplateForm($templateId) {
    $userId = $_SESSION['user_id'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_GET['id'] = $templateId;
        require __DIR__ . '/../seller/template_form.php';
        return;
    }

    if ($templateId && !getTraderProductTemplateById($templateId, $userId)) {
        redirect('/seller/templates');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $_SESSION['flash'] = 'Укажите название шаблона';
        redirect($templateId ? "/seller/templates/$templateId/edit" : '/seller/templates/create');
    }

    $cover = uploadFile($_FILES['cover'] ?? null, 'covers');
    $screenshots = [];
    if (!empty($_FILES['screenshots']['tmp_name'][0])) {
        foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmp) {
            if ($_FILES['screenshots']['error'][$i] === UPLOAD_ERR_OK) {
                $f = ['tmp_name' => $tmp, 'name' => $_FILES['screenshots']['name'][$i], 'error' => 0];
                $s = uploadFile($f, 'screenshots');
                if ($s) $screenshots[] = $s;
            }
        }
    }

    $db = getDB();
    if ($templateId) {
        $old = getTraderProductTemplateById($templateId, $userId);
        $sql = 'UPDATE trader_product_templates SET name=?, title=?, description=?, category_id=?, price=?, file_url=?, youtube_url=?, game_version=?, file_size=?, updated_at=CURRENT_TIMESTAMP';
        $params = [$name, trim($_POST['title'] ?? ''), $_POST['description'] ?? '', $_POST['category_id'] ?: null, (float)($_POST['price'] ?? 0), $_POST['file_url'] ?? '', $_POST['youtube_url'] ?: null, $_POST['game_version'] ?: null, $_POST['file_size'] ?: null];
        if ($cover) { $sql .= ', cover_path=?'; $params[] = $cover; }
        if ($screenshots) { $sql .= ', screenshots=?'; $params[] = json_encode(array_merge(json_decode($old['screenshots'] ?? '[]', true) ?: [], $screenshots)); }
        $sql .= ' WHERE id = ? AND user_id = ?';
        $params[] = $templateId; $params[] = $userId;
        $db->prepare($sql)->execute($params);
        $_SESSION['flash'] = 'Шаблон обновлён';
    } else {
        $stmt = $db->prepare('INSERT INTO trader_product_templates (user_id, name, title, description, category_id, price, file_url, youtube_url, cover_path, screenshots, game_version, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $name, trim($_POST['title'] ?? ''), $_POST['description'] ?? '', $_POST['category_id'] ?: null, (float)($_POST['price'] ?? 0), $_POST['file_url'] ?? '', $_POST['youtube_url'] ?: null, $cover, $screenshots ? json_encode($screenshots) : null, $_POST['game_version'] ?: null, $_POST['file_size'] ?: null]);
        $_SESSION['flash'] = 'Шаблон создан';
    }
    redirect('/seller/templates');
}

function deleteTraderProductTemplate($id, $userId) {
    getDB()->prepare('DELETE FROM trader_product_templates WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
}

// =====================================================================
// Withdrawals
// =====================================================================

function requestWithdrawal($userId, $amount) {
    $balance = getUserBalance($userId);
    $amount = round((float)$amount, 0);
    if ($amount <= 0 || $amount > $balance) return ['error' => 'Некорректная сумма'];
    $stmt = getDB()->prepare('SELECT card_number FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (empty($user['card_number'])) return ['error' => 'Укажите реквизиты для выплат в настройках'];

    $feePercent = (float)setting('withdrawal_fee_percent', 0);
    $freeActivation = function_exists('getPendingFreeWithdrawal') ? getPendingFreeWithdrawal($userId) : null;
    if ($freeActivation) { $feePercent = 0; }
    $feeAmount = round($amount * $feePercent / 100, 2);

    // Informational snapshot for the admin: lifetime gross sales / commission for this trader.
    $commissionPercent = getCommissionPercentFor($userId);
    $stmt = getDB()->prepare("SELECT COALESCE(SUM(amount),0) as gross, COALESCE(SUM(commission_amount),0) as comm FROM orders WHERE seller_id = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $rev = $stmt->fetch();

    getDB()->prepare('INSERT INTO withdrawal_requests (user_id, amount, card_number, status, fee_percent, fee_amount, commission_percent_snapshot, gross_sales_snapshot, commission_amount_snapshot) VALUES (?, ?, ?, "pending", ?, ?, ?, ?, ?)')
        ->execute([$userId, $amount, $user['card_number'], $feePercent, $feeAmount, $commissionPercent, $rev['gross'], $rev['comm']]);

    if ($freeActivation) {
        getDB()->prepare('UPDATE trader_bonus_activations SET status = "used", used_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$freeActivation['id']]);
    }
    return ['ok' => true];
}

function processWithdrawal($id, $approve, $comment = null) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM withdrawal_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req || $req['status'] !== 'pending') return false;
    if ($approve) {
        $db->prepare('UPDATE user_balances SET balance = balance - ? WHERE user_id = ?')->execute([$req['amount'], $req['user_id']]);
        $db->prepare('INSERT INTO transactions (user_id, type, amount, description) VALUES (?, "withdrawal", ?, ?)')
            ->execute([$req['user_id'], -$req['amount'], 'Выплата по заявке #' . $id]);
        $db->prepare('UPDATE withdrawal_requests SET status = "completed", admin_comment = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$comment, $id]);
        notify($req['user_id'], 'withdrawal', ['status' => 'completed', 'amount' => $req['amount']]);
    } else {
        $db->prepare('UPDATE withdrawal_requests SET status = "failed", admin_comment = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$comment, $id]);
        notify($req['user_id'], 'withdrawal', ['status' => 'failed', 'amount' => $req['amount']]);
    }
    return true;
}

// =====================================================================
// Moderation: bans
// =====================================================================

function banUser($userId, $days, $reason) {
    $until = $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : date('Y-m-d H:i:s', time() + 100 * 365 * 86400);
    getDB()->prepare('UPDATE users SET banned_until = ?, ban_reason = ? WHERE id = ?')->execute([$until, $reason, $userId]);
}

function unbanUser($userId) {
    getDB()->prepare('UPDATE users SET banned_until = NULL, ban_reason = NULL WHERE id = ?')->execute([$userId]);
}

function getBannedUsers() {
    return getDB()->query("SELECT * FROM users WHERE banned_until IS NOT NULL ORDER BY banned_until DESC")->fetchAll();
}

// =====================================================================
// Admin action logging
// =====================================================================

function logAdminAction($action, $details = '') {
    $staffId = $_SESSION['admin_id'] ?? null;
    if (!$staffId) return;
    getDB()->prepare('INSERT INTO admin_logs (user_id, action, details) VALUES (?, ?, ?)')->execute([$staffId, $action, $details]);
}
