<?php
function maintenanceModeOn() {
    return setting('maintenance_mode', '0') === '1';
}

function enableMaintenanceMode($message) {
    setSetting('maintenance_mode', '1');
    setSetting('maintenance_message', $message);
    // Only reset the start time if we're turning it on fresh (keeps "since" time stable across saves).
    if (!setting('maintenance_started_at', '')) {
        setSetting('maintenance_started_at', date('Y-m-d H:i:s'));
    }
}

function disableMaintenanceMode() {
    setSetting('maintenance_mode', '0');
    setSetting('maintenance_started_at', '');
}

/**
 * Gate the entire site behind a maintenance page when enabled. Staff (admin/moderator,
 * checked via the existing /admin/login session) always bypass automatically so they can
 * keep working and turn maintenance off. Everyone else can bypass with a code (if one is
 * configured) which is remembered for the rest of their session.
 * Call this early in index.php, before routing, after $uri is computed.
 */
function enforceMaintenanceMode($uri) {
    if (!maintenanceModeOn()) return;
    if (function_exists('checkAdminSession') && checkAdminSession()) return; // staff always bypass
    if (strpos($uri, 'admin') === 0) return; // always allow reaching /admin/login to disable it
    if (!empty($_SESSION['maintenance_bypass'])) return;

    $bypassCode = setting('maintenance_bypass_code', '');

    if ($uri === 'maintenance-bypass' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($bypassCode !== '' && hash_equals($bypassCode, trim($_POST['code'] ?? ''))) {
            $_SESSION['maintenance_bypass'] = true;
            header('Location: /');
            exit;
        }
        $_SESSION['maintenance_error'] = 'Неверный код доступа';
        header('Location: /');
        exit;
    }

    http_response_code(503);
    header('Retry-After: 1800');
    require __DIR__ . '/../pages/maintenance.php';
    exit;
}
