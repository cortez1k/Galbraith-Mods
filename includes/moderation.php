<?php
// =====================================================================
// Product moderation workflow
// =====================================================================

const TRACKED_PRODUCT_FIELDS = ['title', 'description', 'category_id', 'price', 'file_url', 'youtube_url', 'game_version', 'file_size'];

/** Records a diff of changed tracked fields into product_history. */
function logProductHistory($productId, $userId, $oldRow, $newRow) {
    $changes = [];
    foreach (TRACKED_PRODUCT_FIELDS as $field) {
        $old = $oldRow[$field] ?? null;
        $new = $newRow[$field] ?? null;
        if ((string)$old !== (string)$new) {
            $changes[$field] = ['old' => $old, 'new' => $new];
        }
    }
    if (!$changes) return;
    getDB()->prepare('INSERT INTO product_history (product_id, user_id, changes) VALUES (?, ?, ?)')
        ->execute([$productId, $userId, json_encode($changes, JSON_UNESCAPED_UNICODE)]);
}

function getProductHistory($productId) {
    $stmt = getDB()->prepare('SELECT h.*, u.name as user_name FROM product_history h LEFT JOIN users u ON h.user_id = u.id WHERE h.product_id = ? ORDER BY h.created_at DESC');
    $stmt->execute([$productId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) { $r['changes_decoded'] = json_decode($r['changes'], true) ?: []; }
    return $rows;
}

function productStatusLabel($status) {
    $labels = [
        'draft' => 'Черновик',
        'on_moderation' => 'На модерации',
        'approved' => 'Одобрен',
        'rejected' => 'Отклонён',
    ];
    return $labels[$status] ?? $status;
}

function getModerationQueue() {
    $stmt = getDB()->query("SELECT p.*, u.name as seller_name FROM trader_products p LEFT JOIN users u ON p.user_id = u.id WHERE p.status = 'on_moderation' ORDER BY p.updated_at ASC");
    return $stmt->fetchAll();
}

/** Admin approves a product on moderation. */
function approveProduct($productId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) return false;

    $db->prepare("UPDATE trader_products SET status = 'approved', is_active = 1, moderation_comment = NULL WHERE id = ?")->execute([$productId]);
    notify($product['user_id'], 'product_moderation', ['product_id' => $productId, 'title' => $product['title'], 'decision' => 'approved']);
    if (function_exists('notifySubscribersOfNewProduct')) {
        notifySubscribersOfNewProduct($product['user_id'], $product);
    }
    if (function_exists('processReferralOnFirstProduct')) {
        processReferralOnFirstProduct($product['user_id']);
    }
    if (function_exists('evaluateTraderTasks')) {
        evaluateTraderTasks($product['user_id']);
    }
    return true;
}

/** Admin rejects a product on moderation; a reason comment is mandatory. */
function rejectProduct($productId, $comment) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) return false;

    $db->prepare("UPDATE trader_products SET status = 'rejected', is_active = 0, moderation_comment = ? WHERE id = ?")->execute([$comment, $productId]);
    notify($product['user_id'], 'product_moderation', ['product_id' => $productId, 'title' => $product['title'], 'decision' => 'rejected', 'comment' => $comment]);
    return true;
}

/** Seller submits a draft (or a rejected product after fixes) for moderation. */
function submitProductForModeration($productId, $userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM trader_products WHERE id = ? AND user_id = ?");
    $stmt->execute([$productId, $userId]);
    $product = $stmt->fetch();
    if (!$product || !in_array($product['status'], ['draft', 'rejected'], true)) return false;
    $db->prepare("UPDATE trader_products SET status = 'on_moderation' WHERE id = ?")->execute([$productId]);
    notify($userId, 'product_moderation', ['product_id' => $productId, 'title' => $product['title'], 'decision' => 'submitted']);
    return true;
}

/** Attach/detach individual bonus-download tasks to a free product (used during moderation). */
function setProductTasks($productId, array $taskIds) {
    $db = getDB();
    $db->prepare('DELETE FROM product_tasks WHERE product_id = ?')->execute([$productId]);
    $stmt = $db->prepare('INSERT INTO product_tasks (product_id, task_id) VALUES (?, ?)');
    foreach ($taskIds as $taskId) {
        $stmt->execute([$productId, (int)$taskId]);
    }
}

function getProductTasks($productId) {
    $stmt = getDB()->prepare('SELECT t.* FROM product_tasks pt JOIN tasks t ON pt.task_id = t.id WHERE pt.product_id = ? AND t.is_active = 1');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/** Required subscription tasks for a free product = global tasks + any individually assigned ones. */
function getRequiredTasksForProduct($productId) {
    $global = getTasks();
    $individual = getProductTasks($productId);
    $byId = [];
    foreach (array_merge($global, $individual) as $t) { $byId[$t['id']] = $t; }
    return array_values($byId);
}
