<?php
$tab = $_GET['tab'] ?? 'new';
if (!in_array($tab, ['new', 'mine', 'closed'], true)) $tab = 'new';
$agentId = $_SESSION['admin_id'];
$chats = getSupportChatsForAgent($agentId, $tab);
$pageTitle = 'Чаты поддержки';
include __DIR__ . '/../templates/support_header.php';
?>
<?php if (!$chats): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">
        <?php if ($tab === 'new'): ?>Новых обращений нет.
        <?php elseif ($tab === 'mine'): ?>У вас пока нет чатов в работе.
        <?php else: ?>Закрытых чатов пока нет.
        <?php endif; ?>
    </div>
<?php else: ?>
<div style="display:grid;gap:12px;">
    <?php foreach ($chats as $c): ?>
        <a href="/support/chats/<?= $c['id'] ?>/view" class="card chat-list-item">
            <div>
                <strong><?= e($c['user_name'] ?: 'Пользователь') ?></strong>
                <div style="font-size:12px;color:var(--g-text-secondary);margin-top:2px;"><?= e($c['user_email'] ?? '') ?></div>
                <?php if ($c['last_message']): ?><div style="font-size:13px;color:var(--g-text-secondary);margin-top:6px;max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($c['last_message']) ?></div><?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div style="font-size:12px;color:var(--g-text-secondary);"><?= e(date('d.m.Y H:i', strtotime($c['last_message_at']))) ?></div>
                <?php if ($tab === 'new'): ?>
                    <span style="display:inline-block;margin-top:6px;padding:3px 10px;border-radius:999px;background:var(--g-warning-dim);color:var(--g-warning);font-size:12px;font-weight:600;">Не взят в работу</span>
                <?php elseif ($tab === 'mine'): ?>
                    <span style="display:inline-block;margin-top:6px;padding:3px 10px;border-radius:999px;background:<?= $c['status'] === 'answered' ? 'var(--g-success-dim)' : 'var(--g-accent-dim)' ?>;color:<?= $c['status'] === 'answered' ? 'var(--g-success)' : 'var(--g-accent-light)' ?>;font-size:12px;font-weight:600;"><?= $c['status'] === 'answered' ? 'Ответили' : 'Ждёт ответа' ?></span>
                <?php else: ?>
                    <div style="font-size:12px;color:var(--g-text-secondary);margin-top:6px;">Агент: <?= e($c['agent_name'] ?? '—') ?></div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
