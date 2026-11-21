<?php
// =====================================================================
// Auto-delivery: sellers pre-load a stock of unique digital items (keys,
// accounts, codes...); when payment is confirmed, one item is popped from
// the stock and delivered instantly, with no manual seller action needed —
// mirrors the "автовыдача" feature on FunPay.
// =====================================================================

/** Добавляет одну или несколько позиций склада (каждая строка — своя позиция,
 *  выдаётся один раз и больше не используется). Раньше эта функция перестала
 *  писать в таблицу product_stock и вместо этого перезаписывала одно поле
 *  auto_delivery_content — из-за этого склад с несколькими кодами/ссылками
 *  фактически перестал работать. Восстановлено. */
function addStockItems($productId, $rawText) {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$rawText)), function ($l) { return $l !== ''; }));
    if (!$lines) return 0;
    $stmt = getDB()->prepare('INSERT INTO product_stock (product_id, content) VALUES (?, ?)');
    foreach ($lines as $line) { $stmt->execute([$productId, $line]); }
    return count($lines);
}

/** Постоянная позиция (необязательно) — одно и то же значение выдаётся всем
 *  покупателям и не расходуется (например, инвайт-ссылка на Discord). Если
 *  задана — имеет приоритет над складом; чтобы вернуться к складу, её нужно
 *  очистить пустым значением. */
function setConstantPosition($productId, $value) {
    getDB()->prepare('UPDATE trader_products SET auto_delivery_content = ? WHERE id = ?')->execute([trim((string)$value), $productId]);
}
function getAutoDeliveryContent($productId) {
    $stmt=getDB()->prepare('SELECT auto_delivery_content FROM trader_products WHERE id=?'); $stmt->execute([$productId]); return (string)($stmt->fetchColumn() ?? '');
}
function getStockCount($productId) { $content=getAutoDeliveryContent($productId); if($content!=='')return 1; $stmt=getDB()->prepare('SELECT COUNT(*) FROM product_stock WHERE product_id=? AND is_used=0');$stmt->execute([$productId]);return (int)$stmt->fetchColumn(); }

function getStockItems($productId, $includeUsed = true) {
    $sql = 'SELECT * FROM product_stock WHERE product_id = ?';
    if (!$includeUsed) $sql .= ' AND is_used = 0';
    $sql .= ' ORDER BY is_used ASC, id ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function deleteStockItem($id, $productId) {
    getDB()->prepare('DELETE FROM product_stock WHERE id = ? AND product_id = ? AND is_used = 0')->execute([$id, $productId]);
}

/** Pops the oldest unused stock item for a product and marks it used against an order. Returns the content string or null if out of stock. */
function popStockItem($productId, $orderId) {
    $content = getAutoDeliveryContent($productId);
    if ($content !== '') return $content;
    $db=getDB();$stmt=$db->prepare('SELECT * FROM product_stock WHERE product_id=? AND is_used=0 ORDER BY id ASC LIMIT 1');$stmt->execute([$productId]);$item=$stmt->fetch();
    if(!$item)return null;$db->prepare('UPDATE product_stock SET is_used=1,order_id=?,used_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$orderId,$item['id']]);return $item['content'];
}

/**
 * Called right after an admin confirms payment (order -> paid). If the product has
 * auto-delivery enabled, instantly pops a stock item, posts it in the order chat,
 * and completes the order — no manual seller/buyer action required, like FunPay.
 * Returns true if auto-delivery fired, false otherwise (falls back to the normal manual flow).
 */
function tryAutoDeliver($orderId) {
    $order = getOrderById($orderId);
    if (!$order) return false;
    $product = getTraderProductById($order['product_id']);
    if (!$product || !$product['auto_delivery']) return false;

    $content = popStockItem($product['id'], $orderId);
    if ($content === null) {
        addOrderMessage($orderId, null, '⚠️ Автовыдача включена, но товар закончился на складе. Продавец должен передать товар вручную.', true);
        notify($order['seller_id'], 'auto_delivery', ['order_id'=>$orderId,'status'=>'out_of_stock']);
        return false;
    }

    // Count every successful auto-delivery as a marketplace download.
    // This also covers constant positions, not only stock items.
    getDB()->prepare('UPDATE trader_products SET downloads_count = downloads_count + 1 WHERE id = ?')->execute([$product['id']]);

    addOrderMessage($orderId, null, '[[AUTO_DELIVERY_NOTICE]]🤖 Автовыдача: ваш товар готов!', true);
    if (!empty($product['auto_delivery_message'])) {
        addOrderMessage($orderId, null, '[[AUTO_DELIVERY_MESSAGE]]' . $product['auto_delivery_message'], true);
    }
    addOrderMessage($orderId, null, '[[AUTO_DELIVERY_ITEM]]' . $content, true);
    notify($order['buyer_id'],'auto_delivery',['order_id'=>$orderId,'status'=>'delivered']);
    notify($order['seller_id'],'auto_delivery',['order_id'=>$orderId,'status'=>'delivered']);
    completeOrder($orderId, 'auto_delivery');
    return true;
}

function getStockWarningThreshold() {
    return (int)setting('stock_low_threshold', 3);
}
