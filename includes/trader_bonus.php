<?php
require_once __DIR__ . '/marketplace.php';

// =====================================================================
// Trader bonus balance & transactions
// =====================================================================

function traderBonusEnabled() {
    return setting('trader_bonus_enabled', '1') === '1';
}

function getTraderBonusBalance($userId) {
    $stmt = getDB()->prepare('SELECT balance FROM trader_bonuses WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (float)$row['balance'] : 0.0;
}

function addTraderBonus($userId, $amount, $type, $reason, $orderId = null) {
    if (!traderBonusEnabled() || $amount == 0) return;
    $db = getDB();
    $db->prepare('INSERT INTO trader_bonus_transactions (user_id, order_id, amount, type, reason) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $orderId, $amount, $type, $reason]);
    $stmt = $db->prepare('SELECT id FROM trader_bonuses WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        $db->prepare('UPDATE trader_bonuses SET balance = balance + ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')->execute([$amount, $userId]);
    } else {
        $db->prepare('INSERT INTO trader_bonuses (user_id, balance) VALUES (?, ?)')->execute([$userId, max(0, $amount)]);
    }
    notify($userId, 'trader_bonus_change', ['amount' => $amount, 'reason' => $reason]);
}

function getTraderBonusHistory($userId) {
    $stmt = getDB()->prepare('SELECT * FROM trader_bonus_transactions WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Called whenever an order transitions to completed. Awards the SELLER trader-bonus
 * points for the sale (separate from the buyer's discount-bonus system in marketplace.php).
 */
function accrueTraderOrderBonus($order) {
    if (!traderBonusEnabled()) return;
    $sellerId = $order['seller_id'];
    $amount = (float)setting('tb_per_completed_order', 1);
    addTraderBonus($sellerId, $amount, 'auto_accrual', 'Завершённый заказ #' . $order['id'], $order['id']);
    $threshold = (float)setting('tb_per_order_over_amount', 1000);
    if ((float)$order['amount'] > $threshold) {
        $extra = (float)setting('tb_per_order_over_bonus', 1);
        addTraderBonus($sellerId, $extra, 'auto_accrual', 'Заказ на сумму > ' . $threshold . ' руб. (#' . $order['id'] . ')', $order['id']);
    }
}

/** Called when a 5-star review is submitted; awards the seller trader-bonus points once per review. */
function accrueTraderReviewBonus($sellerId, $reviewId, $rating) {
    if (!traderBonusEnabled() || $rating < 5) return;
    $amount = (float)setting('tb_per_positive_review', 1);
    addTraderBonus($sellerId, $amount, 'auto_accrual', 'Положительный отзыв #' . $reviewId);
}

/** Called when a dispute is resolved in the seller's favor ('release'). */
function accrueTraderDisputeBonus($sellerId, $orderId) {
    if (!traderBonusEnabled()) return;
    $amount = (float)setting('tb_per_dispute_won', 0.5);
    addTraderBonus($sellerId, $amount, 'auto_accrual', 'Спор решён в вашу пользу (заказ #' . $orderId . ')', $orderId);
}

/**
 * Lazy substitute for a cron job (this hosting environment has no scheduler access):
 * called whenever a trader visits their seller panel. Awards this calendar month's
 * activity bonuses at most once per trader per month.
 */
function checkMonthlyTraderBonuses($userId) {
    if (!traderBonusEnabled()) return;
    $db = getDB();
    $monthKey = date('Y-m');
    $marker = 'monthly:' . $monthKey;
    $stmt = $db->prepare("SELECT id FROM trader_bonus_transactions WHERE user_id = ? AND type = 'auto_accrual' AND reason LIKE ?");
    $stmt->execute([$userId, '%[' . $marker . ']%']);
    if ($stmt->fetch()) return; // already processed this month

    $activeThreshold = (int)setting('tb_monthly_active_products_threshold', 10);
    $stmt = $db->prepare('SELECT COUNT(*) FROM trader_products WHERE user_id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $activeCount = (int)$stmt->fetchColumn();
    if ($activeCount > $activeThreshold) {
        addTraderBonus($userId, (float)setting('tb_monthly_active_products_bonus', 1), 'auto_accrual', "Активных товаров > $activeThreshold [$marker]");
    }

    $ordersThreshold = (int)setting('tb_monthly_orders30_threshold', 5);
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'completed' AND created_at >= datetime('now', '-30 days')");
    $stmt->execute([$userId]);
    $orders30 = (int)$stmt->fetchColumn();
    if ($orders30 >= $ordersThreshold) {
        addTraderBonus($userId, (float)setting('tb_monthly_orders30_bonus', 2), 'auto_accrual', "Заказов за 30 дней >= $ordersThreshold [$marker]");
    }
    // Marker transaction so this trader isn't re-processed this month even if neither rule matched.
    if ($activeCount <= $activeThreshold && $orders30 < $ordersThreshold) {
        $db->prepare('INSERT INTO trader_bonus_transactions (user_id, amount, type, reason) VALUES (?, 0, "auto_accrual", ?)')
            ->execute([$userId, "Ежемесячная проверка без начислений [$marker]"]);
    }
}

// =====================================================================
// Bonus tasks (admin-defined, progress tracked per trader)
// =====================================================================

function getActiveTasks() {
    return getDB()->query('SELECT * FROM bonus_tasks WHERE is_active = 1 AND is_deleted = 0 ORDER BY id DESC')->fetchAll();
}

function getAllTasks() {
    return getDB()->query('SELECT * FROM bonus_tasks WHERE is_deleted = 0 ORDER BY id DESC')->fetchAll();
}

/** Computes the raw lifetime metric value for a trader for a given task condition type. */
function computeTaskMetric($userId, $conditionType) {
    $db = getDB();
    switch ($conditionType) {
        case 'orders_completed':
            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'completed'");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        case 'positive_reviews':
            $stmt = $db->prepare('SELECT COUNT(*) FROM reviews WHERE seller_id = ? AND rating = 5 AND is_hidden = 0');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        case 'active_products':
            $stmt = $db->prepare('SELECT COUNT(*) FROM trader_products WHERE user_id = ? AND is_active = 1');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        case 'referrals_count':
            $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        case 'revenue_total':
            $stmt = $db->prepare("SELECT COALESCE(SUM(net_amount),0) FROM orders WHERE seller_id = ? AND status = 'completed'");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        case 'downloads_admin_materials':
            $stmt = $db->prepare('SELECT COUNT(*) FROM admin_material_downloads WHERE user_id = ?');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        case 'purchases_admin_materials':
            $stmt = $db->prepare("SELECT COUNT(*) FROM admin_material_purchases WHERE user_id = ? AND status IN ('paid','completed')");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        default:
            return 0;
    }
}

/** Re-evaluates all active tasks for a trader; awards bonuses for newly-completed tasks. Call after any relevant event. */
function evaluateTraderTasks($userId) {
    if (!traderBonusEnabled()) return;
    $db = getDB();
    foreach (getActiveTasks() as $task) {
        $stmt = $db->prepare('SELECT * FROM trader_task_progress WHERE user_id = ? AND task_id = ?');
        $stmt->execute([$userId, $task['id']]);
        $prog = $stmt->fetch();
        if (!$prog) {
            $db->prepare('INSERT INTO trader_task_progress (user_id, task_id, progress, baseline_value) VALUES (?, ?, 0, 0)')->execute([$userId, $task['id']]);
            $stmt->execute([$userId, $task['id']]);
            $prog = $stmt->fetch();
        }
        if ($prog['is_completed'] && !$task['is_repeatable']) continue;

        $metric = computeTaskMetric($userId, $task['condition_type']);
        $progress = max(0, $metric - (int)$prog['baseline_value']);
        $db->prepare('UPDATE trader_task_progress SET progress = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$progress, $prog['id']]);

        if ($progress >= $task['condition_value'] && (!$prog['is_completed'] || $task['is_repeatable'])) {
            addTraderBonus($userId, (float)$task['bonus_amount'], 'auto_accrual', 'Задание: ' . $task['title']);
            notify($userId, 'task_completed', ['task' => $task['title'], 'bonus' => $task['bonus_amount']]);
            if ($task['is_repeatable']) {
                // Reset the cycle: new baseline is the current metric value, progress restarts at 0.
                $db->prepare('UPDATE trader_task_progress SET baseline_value = ?, progress = 0, is_completed = 0, completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([$metric, $prog['id']]);
            } else {
                $db->prepare('UPDATE trader_task_progress SET is_completed = 1, completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$prog['id']]);
            }
        }
    }
}

function getTraderTasksWithProgress($userId) {
    $db = getDB();
    $tasks = getActiveTasks();
    $result = [];
    foreach ($tasks as $task) {
        $stmt = $db->prepare('SELECT * FROM trader_task_progress WHERE user_id = ? AND task_id = ?');
        $stmt->execute([$userId, $task['id']]);
        $prog = $stmt->fetch();
        $task['progress'] = $prog ? (int)$prog['progress'] : 0;
        $task['is_completed'] = $prog ? (bool)$prog['is_completed'] : false;
        $task['completed_at'] = $prog['completed_at'] ?? null;
        $task['percent'] = $task['condition_value'] > 0 ? min(100, round($task['progress'] / $task['condition_value'] * 100)) : 0;
        $result[] = $task;
    }
    return $result;
}

// =====================================================================
// Referral program
// =====================================================================

function getReferralLink($userId) { $st=getDB()->prepare('SELECT referral_code FROM users WHERE id=?');$st->execute([$userId]);$code=$st->fetchColumn();return rtrim(SITE_URL,'/').'/?ref='.rawurlencode($code ?: ('u'.$userId)); }
function referralCodeAvailable($code,$userId){$code=trim($code);if(!preg_match('/^[A-Za-z0-9_-]{3,32}$/',$code))return false;$st=getDB()->prepare('SELECT id FROM users WHERE referral_code=? AND id<>?');$st->execute([$code,$userId]);return !$st->fetch();}
function setReferralCode($userId,$code){$code=trim($code);if(!referralCodeAvailable($code,$userId))return ['error'=>'Код должен быть 3–32 символа (латиница, цифры, _ или -) и быть уникальным'];getDB()->prepare('UPDATE users SET referral_code=? WHERE id=?')->execute([$code,$userId]);return ['ok'=>true];}

/** Captures a ?ref= parameter into the session for use at signup time. Call early on every request. */
function captureReferralParam() {
    if (!empty($_GET['ref'])) { $code=trim((string)$_GET['ref']); $st=getDB()->prepare('SELECT id FROM users WHERE referral_code=?');$st->execute([$code]);$id=$st->fetchColumn();if(!$id && ctype_digit($code))$id=(int)$code;if($id)$_SESSION['pending_ref']=(int)$id; }
}

/** Called from findOrCreateOAuthUser() right after a brand-new account is created. */
function attachReferralIfPending($newUserId) {
    if (empty($_SESSION['pending_ref'])) return;
    $referrerId = (int)$_SESSION['pending_ref'];
    unset($_SESSION['pending_ref']);
    if ($referrerId === (int)$newUserId) return;
    $stmt = getDB()->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$referrerId]);
    if (!$stmt->fetch()) return;
    $days = (int)setting('referral_days', 30);
    $expiresAt = date('Y-m-d H:i:s', time() + $days * 86400);
    try {
        getDB()->prepare('INSERT INTO referrals (referrer_id, referred_user_id, expires_at, status) VALUES (?, ?, ?, "active")')
            ->execute([$referrerId, $newUserId, $expiresAt]);
        getDB()->prepare('UPDATE users SET referred_by = ? WHERE id = ?')->execute([$referrerId, $newUserId]);
    } catch (Throwable $e) {
        // referred_user_id is UNIQUE; ignore if somehow already attached
    }
}

function getActiveReferral($referredUserId) {
    $stmt = getDB()->prepare("SELECT * FROM referrals WHERE referred_user_id = ? AND status = 'active' AND expires_at > datetime('now')");
    $stmt->execute([$referredUserId]);
    return $stmt->fetch();
}

function addReferralBonus($referrerId, $referredUserId, $amount, $reason, $orderId = null) {
    getDB()->prepare('INSERT INTO referral_bonuses (referrer_id, referred_user_id, order_id, amount, reason) VALUES (?, ?, ?, ?, ?)')
        ->execute([$referrerId, $referredUserId, $orderId, $amount, $reason]);
    addTraderBonus($referrerId, $amount, 'referral', $reason, $orderId);
}

/** Called whenever an order by a (possibly referred) buyer completes. */
function processReferralOnOrderComplete($order) {
    $ref = getActiveReferral($order['buyer_id']);
    if (!$ref) return;
    $referrerId = $ref['referrer_id'];

    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'completed'");
    $stmt->execute([$order['buyer_id']]);
    $completedCount = (int)$stmt->fetchColumn();

    if ($completedCount === 1) {
        addReferralBonus($referrerId, $order['buyer_id'], (float)setting('referral_first_purchase_bonus', 2), 'Первая покупка реферала', $order['id']);
        $threshold = (float)setting('referral_purchase_threshold_amount', 3000);
        if ((float)$order['amount'] > $threshold) {
            addReferralBonus($referrerId, $order['buyer_id'], (float)setting('referral_purchase_threshold_bonus', 1), 'Покупка реферала на сумму > ' . $threshold, $order['id']);
        }
    }

    if ($completedCount === 3) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'completed' AND created_at >= datetime('now', '-30 days')");
        $stmt->execute([$order['buyer_id']]);
        if ((int)$stmt->fetchColumn() >= 3) {
            addReferralBonus($referrerId, $order['buyer_id'], (float)setting('referral_3purchases_bonus', 3), 'Реферал совершил 3+ покупки за 30 дней', $order['id']);
        }
    }
}

/** Called when a referred user's product gets approved for the first time (v3.0: "published" = approved by moderation). */
function processReferralOnFirstProduct($sellerId) {
    $ref = getActiveReferral($sellerId);
    if (!$ref) return;
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM trader_products WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$sellerId]);
    if ((int)$stmt->fetchColumn() === 1) {
        addReferralBonus($ref['referrer_id'], $sellerId, (float)setting('referral_trader_signup_bonus', 5), 'Реферал стал торговцем и опубликовал первый товар');
    }
}

function getReferralStats($userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT COUNT(*) FROM referrals WHERE referrer_id = ?');
    $stmt->execute([$userId]);
    $total = (int)$stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'active' AND expires_at > datetime('now')");
    $stmt->execute([$userId]);
    $active = (int)$stmt->fetchColumn();
    $stmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM referral_bonuses WHERE referrer_id = ?');
    $stmt->execute([$userId]);
    $earned = (float)$stmt->fetchColumn();
    return ['total' => $total, 'active' => $active, 'earned' => $earned];
}

function getReferralHistory($userId) {
    $stmt = getDB()->prepare('SELECT rb.*, u.name as referred_name FROM referral_bonuses rb LEFT JOIN users u ON rb.referred_user_id = u.id WHERE rb.referrer_id = ? ORDER BY rb.created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// =====================================================================
// Bonus shop (spending trader bonuses)
// =====================================================================

function getBonusShopOptions() {
    return [
        'commission_reduction' => ['label' => 'Снижение комиссии на 1% на следующий заказ', 'cost' => (float)setting('cost_commission_reduction', 5)],
        'free_withdrawal' => ['label' => 'Бесплатный вывод средств (без комиссии)', 'cost' => (float)setting('cost_free_withdrawal', 10)],
        'top_rank' => ['label' => 'Статус «Топ-продавец» на ' . (int)setting('top_rank_days', 7) . ' дней', 'cost' => (float)setting('cost_top_rank', 15)],
        'cash_conversion' => ['label' => 'Конвертация в рубли (1 бонус = ' . (float)setting('bonus_to_rub_rate', 10) . ' ₽)', 'cost' => 1],
    ];
}

function activateBonusOption($userId, $optionType, $qty = 1) {
    $options = getBonusShopOptions();
    if (!isset($options[$optionType])) return ['error' => 'Неизвестная опция'];
    $qty = max(1, (int)$qty);
    $balance = getTraderBonusBalance($userId);
    $db = getDB();

    if ($optionType === 'cash_conversion') {
        $totalCost = $qty; // 1 bonus per unit
        if ($balance < $totalCost) return ['error' => 'Недостаточно бонусов'];
        $rate = (float)setting('bonus_to_rub_rate', 10);
        addTraderBonus($userId, -$totalCost, 'spend', 'Конвертация ' . $totalCost . ' бонусов в рубли');
        creditUserBalance($userId, $totalCost * $rate, null, 'Конвертация бонусов в рубли');
        return ['ok' => true];
    }

    $cost = $options[$optionType]['cost'];
    if ($balance < $cost) return ['error' => 'Недостаточно бонусов'];

    addTraderBonus($userId, -$cost, 'spend', 'Активация: ' . $options[$optionType]['label']);

    if ($optionType === 'commission_reduction') {
        $db->prepare('INSERT INTO trader_bonus_activations (user_id, option_type, value, status) VALUES (?, "commission_reduction", 1, "pending")')->execute([$userId]);
    } elseif ($optionType === 'free_withdrawal') {
        $db->prepare('INSERT INTO trader_bonus_activations (user_id, option_type, value, status) VALUES (?, "free_withdrawal", NULL, "pending")')->execute([$userId]);
    } elseif ($optionType === 'top_rank') {
        $days = (int)setting('top_rank_days', 7);
        $until = date('Y-m-d H:i:s', time() + $days * 86400);
        $db->prepare('UPDATE users SET top_seller_until = ? WHERE id = ?')->execute([$until, $userId]);
        $db->prepare('INSERT INTO trader_bonus_activations (user_id, option_type, value, status, used_at) VALUES (?, "top_rank", ?, "used", CURRENT_TIMESTAMP)')->execute([$userId, $days]);
    }
    return ['ok' => true];
}

/** Returns a pending commission_reduction activation for this seller, if any (does not consume it). */
function getPendingCommissionReduction($sellerId) {
    $stmt = getDB()->prepare('SELECT * FROM trader_bonus_activations WHERE user_id = ? AND option_type = "commission_reduction" AND status = "pending" ORDER BY created_at ASC LIMIT 1');
    $stmt->execute([$sellerId]);
    return $stmt->fetch();
}

function consumeCommissionReduction($activationId) {
    getDB()->prepare('UPDATE trader_bonus_activations SET status = "used", used_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$activationId]);
}

function getPendingFreeWithdrawal($userId) {
    $stmt = getDB()->prepare('SELECT * FROM trader_bonus_activations WHERE user_id = ? AND option_type = "free_withdrawal" AND status = "pending" ORDER BY created_at ASC LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function traderProductLimit($userId) { return PHP_INT_MAX; }

function isTopSeller($userId) {
    $stmt = getDB()->prepare('SELECT top_seller_until FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $until = $stmt->fetchColumn();
    return $until && strtotime($until) > time();
}

// =====================================================================
// Discipline: penalties
// =====================================================================

const PENALTY_REASONS = [
    'late_delivery' => 'Нарушение сроков передачи товара',
    'rude_communication' => 'Грубое общение с покупателем',
    'broken_product' => 'Продажа нерабочего товара (подтверждено спором)',
    'multiple_disputes' => 'Множественные споры в пользу покупателя (более 3 за месяц)',
    'fraud_attempt' => 'Попытка мошенничества',
    'ignored_messages' => 'Игнорирование сообщений покупателя более 24 часов',
];

/** Lazy substitute for a cron job: flips expired penalties to inactive. Call before reading penalty data. */
function expireOldPenalties() {
    getDB()->exec("UPDATE trader_penalties SET is_active = 0 WHERE is_active = 1 AND expires_at <= datetime('now')");
}

function getActivePenalties($userId) {
    expireOldPenalties();
    $stmt = getDB()->prepare('SELECT * FROM trader_penalties WHERE user_id = ? AND is_active = 1 ORDER BY issued_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getAllPenalties($userId) {
    expireOldPenalties();
    $stmt = getDB()->prepare('SELECT p.*, u.name as issued_by_name FROM trader_penalties p LEFT JOIN users u ON p.issued_by = u.id WHERE p.user_id = ? ORDER BY p.issued_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function countActivePenalties($userId) {
    expireOldPenalties();
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM trader_penalties WHERE user_id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function issuePenalty($userId, $reasonKey, $comment, $issuedBy) {
    $db = getDB();
    $days = (int)setting('penalty_expiry_days', 30);
    $expiresAt = date('Y-m-d H:i:s', time() + $days * 86400);
    $reasonText = PENALTY_REASONS[$reasonKey] ?? $reasonKey;
    $wasBelowThreshold = countActivePenalties($userId) < (int)setting('penalty_max_active', 5);

    $db->prepare('INSERT INTO trader_penalties (user_id, reason, issued_by, expires_at, comment) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $reasonText, $issuedBy, $expiresAt, $comment]);
    notify($userId, 'penalty_issued', ['reason' => $reasonText, 'expires_at' => $expiresAt]);

    $count = countActivePenalties($userId);
    $threshold = (int)setting('penalty_max_active', 5);
    if ($count >= $threshold && $wasBelowThreshold) {
        $admins = getDB()->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        foreach ($admins as $a) {
            notify($a['id'], 'trader_penalty_threshold', ['user_id' => $userId, 'count' => $count]);
        }
    }
}

function removePenalty($penaltyId, $comment = null) {
    $db = getDB();
    $stmt = $db->prepare('UPDATE trader_penalties SET is_active = 0, comment = COALESCE(?, comment) WHERE id = ?');
    $stmt->execute([$comment, $penaltyId]);
}

function removeOnePenaltyForUser($userId) {
    $stmt = getDB()->prepare('SELECT id FROM trader_penalties WHERE user_id = ? AND is_active = 1 ORDER BY issued_at ASC LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) removePenalty($row['id']);
}

/** "Delete trader": revoke role, hide products, cancel in-progress orders, freeze balance. */
function deleteTrader($userId) {
    $db = getDB();
    $db->prepare("UPDATE users SET role = 'user', trader_frozen = 1 WHERE id = ?")->execute([$userId]);
    $db->prepare('UPDATE trader_products SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
    $stmt = $db->prepare("SELECT id FROM orders WHERE seller_id = ? AND status IN ('awaiting_confirmation','paid','in_progress')");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $o) {
        $db->prepare('UPDATE orders SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$o['id']]);
        addOrderMessage($o['id'], null, 'Заказ отменён администратором: продавец удалён с площадки.', true);
    }
    notify($userId, 'trader_removed', []);
}

function restoreTrader($userId) {
    $db = getDB();
    $db->prepare("UPDATE users SET role = 'trader', trader_frozen = 0 WHERE id = ?")->execute([$userId]);
    $db->prepare('UPDATE trader_products SET is_active = 1 WHERE user_id = ?')->execute([$userId]);
    notify($userId, 'trader_restored', []);
}

function isTraderFrozen($userId) {
    $stmt = getDB()->prepare('SELECT trader_frozen FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

// =====================================================================
// Marketplace ordering / pinning (admin)
// =====================================================================

function getPinnedCount() {
    $stmt = getDB()->query('SELECT COUNT(*) FROM trader_products WHERE is_pinned = 1');
    return (int)$stmt->fetchColumn();
}

function pinProduct($productId) {
    if (getPinnedCount() >= (int)setting('max_pinned_products', 5)) {
        return ['error' => 'Достигнут лимит закреплённых товаров'];
    }
    getDB()->prepare('UPDATE trader_products SET is_pinned = 1, sort_order = 0 WHERE id = ?')->execute([$productId]);
    return ['ok' => true];
}

function unpinProduct($productId) {
    getDB()->prepare('UPDATE trader_products SET is_pinned = 0 WHERE id = ?')->execute([$productId]);
    return ['ok' => true];
}

function moveProductOrder($productId, $direction) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) return;
    // Раньше сосед искался строгим сравнением sort_order < / > текущего — но у
    // всех новых товаров sort_order по умолчанию 0, значений с разницей просто
    // не существовало, поэтому сосед никогда не находился и кнопки ↑/↓ не
    // работали. Теперь ищем соседа по позиции в общем списке (того же уровня
    // закрепления), а не по сырому значению — и заодно нормализуем sort_order
    // всего списка до уникальных последовательных чисел, чтобы это не
    // повторилось при следующем перемещении.
    $stmt = $db->prepare('SELECT id, sort_order FROM trader_products WHERE is_pinned = ? ORDER BY sort_order ASC, created_at DESC, id ASC');
    $stmt->execute([$product['is_pinned']]);
    $list = $stmt->fetchAll();
    $index = null;
    foreach ($list as $i => $row) { if ((int)$row['id'] === (int)$productId) { $index = $i; break; } }
    if ($index === null) return;
    $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapWith < 0 || $swapWith >= count($list)) return;
    foreach ($list as $i => $row) {
        if ((int)$row['sort_order'] !== $i) {
            $db->prepare('UPDATE trader_products SET sort_order = ? WHERE id = ?')->execute([$i, $row['id']]);
            $list[$i]['sort_order'] = $i;
        }
    }
    $a = $list[$index]; $b = $list[$swapWith];
    $db->prepare('UPDATE trader_products SET sort_order = ? WHERE id = ?')->execute([$b['sort_order'], $a['id']]);
    $db->prepare('UPDATE trader_products SET sort_order = ? WHERE id = ?')->execute([$a['sort_order'], $b['id']]);
}
