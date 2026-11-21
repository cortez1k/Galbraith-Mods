<?php
// =====================================================================
// Favorites
// =====================================================================

function isFavorited($userId, $productId) {
    $stmt = getDB()->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    return (bool)$stmt->fetch();
}

function toggleFavorite($userId, $productId) {
    $db = getDB();
    if (isFavorited($userId, $productId)) {
        $db->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?')->execute([$userId, $productId]);
        return false;
    }
    $db->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)')->execute([$userId, $productId]);
    return true;
}

function getFavorites($userId) {
    $stmt = getDB()->prepare("SELECT p.*, c.name as category_name, u.name as seller_name
        FROM favorites f
        JOIN trader_products p ON f.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE f.user_id = ? ORDER BY f.created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// =====================================================================
// Seller subscriptions
// =====================================================================

function isSubscribed($userId, $sellerId) {
    $stmt = getDB()->prepare('SELECT id FROM seller_subscriptions WHERE user_id = ? AND seller_id = ?');
    $stmt->execute([$userId, $sellerId]);
    return (bool)$stmt->fetch();
}

function toggleSubscription($userId, $sellerId) {
    if ((int)$userId === (int)$sellerId) return false;
    $db = getDB();
    if (isSubscribed($userId, $sellerId)) {
        $db->prepare('DELETE FROM seller_subscriptions WHERE user_id = ? AND seller_id = ?')->execute([$userId, $sellerId]);
        return false;
    }
    $db->prepare('INSERT INTO seller_subscriptions (user_id, seller_id) VALUES (?, ?)')->execute([$userId, $sellerId]);
    return true;
}

function getSubscriberCount($sellerId) {
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM seller_subscriptions WHERE seller_id = ?');
    $stmt->execute([$sellerId]);
    return (int)$stmt->fetchColumn();
}

function getSubscriptions($userId) {
    $stmt = getDB()->prepare("SELECT s.*, u.name as seller_name, u.avatar as seller_avatar,
        (SELECT AVG(rating) FROM reviews r WHERE r.seller_id = u.id AND r.is_hidden = 0) as seller_rating
        FROM seller_subscriptions s JOIN users u ON s.seller_id = u.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Called after a product is approved by an admin: notifies all of the seller's subscribers. */
function notifySubscribersOfNewProduct($sellerId, $product) {
    $stmt = getDB()->prepare('SELECT user_id FROM seller_subscriptions WHERE seller_id = ?');
    $stmt->execute([$sellerId]);
    foreach ($stmt->fetchAll() as $sub) {
        notify($sub['user_id'], 'new_product_subscription', ['seller_id' => $sellerId, 'product_id' => $product['id'], 'title' => $product['title']]);
    }
}
