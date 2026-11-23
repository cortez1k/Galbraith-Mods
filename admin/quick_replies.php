<?php
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Быстрые ответы поддержки';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
    }
    $db = getDB();

    if (($_POST['action'] ?? '') === 'add') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title === '' || $content === '') {
            $errors[] = 'Заголовок и текст ответа обязательны.';
        } else {
            $db->prepare('INSERT INTO support_quick_replies (title, content, shortcut, sort_order, is_active) VALUES (?, ?, ?, ?, ?)')
                ->execute([$title, $content, trim($_POST['shortcut'] ?? ''), (int)($_POST['sort_order'] ?? 0), (int)($_POST['is_active'] ?? 1)]);
            redirect('/admin/quick-replies');
        }
    } elseif (($_POST['action'] ?? '') === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($id <= 0 || $title === '' || $content === '') {
            $errors[] = 'Неверные данные ответа.';
        } else {
            $db->prepare('UPDATE support_quick_replies SET title = ?, content = ?, shortcut = ?, sort_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$title, $content, trim($_POST['shortcut'] ?? ''), (int)($_POST['sort_order'] ?? 0), (int)($_POST['is_active'] ?? 1), $id]);
            redirect('/admin/quick-replies');
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM support_quick_replies WHERE id = ?')->execute([$id]);
            redirect('/admin/quick-replies');
        }
    }
}

$db = getDB();
$replies = $db->query('SELECT * FROM support_quick_replies ORDER BY sort_order ASC, id ASC')->fetchAll();
$editReply = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM support_quick_replies WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editReply = $stmt->fetch();
}

include __DIR__ . '/../templates/admin_header.php';
?>

<div style="max-width: 900px;">
    <h1 style="font-size: 22px; font-weight: 600; margin-bottom: 8px;">Быстрые ответы поддержки</h1>
    <p style="color: var(--g-text-secondary); font-size: 14px; margin-bottom: 20px;">
        Эти шаблоны показываются агентам в чате поддержки. Форматирование: <code>**жирный**</code>, <code>*курсив*</code>, <code>`код`</code>, перенос строки = новая строка.
    </p>

    <?php if ($errors): ?>
        <div style="background: var(--g-danger-dim); border: 1px solid var(--g-danger); border-radius: var(--g-radius); padding: 12px 16px; color: var(--g-danger); margin-bottom: 20px;">
            <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="margin-bottom: 32px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $editReply ? 'update' : 'add' ?>">
        <?php if ($editReply): ?><input type="hidden" name="id" value="<?= (int)$editReply['id'] ?>"><?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr 80px; gap: 16px; margin-bottom: 16px;">
            <div>
                <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Заголовок *</label>
                <input type="text" name="title" class="input" placeholder="Например: Приветствие" value="<?= e($editReply['title'] ?? '') ?>" required>
            </div>
            <div>
                <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Шорткат</label>
                <input type="text" name="shortcut" class="input" placeholder="/hello" value="<?= e($editReply['shortcut'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Порядок</label>
                <input type="number" name="sort_order" class="input" value="<?= e($editReply['sort_order'] ?? 0) ?>">
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Текст ответа *</label>
            <textarea name="content" class="input" rows="5" placeholder="Здравствуйте! Чем могу помочь?" required><?= e($editReply['content'] ?? '') ?></textarea>
        </div>

        <div style="display:flex; align-items:center; gap:8px; margin-bottom: 20px;">
            <input type="checkbox" name="is_active" value="1" <?= (!isset($editReply['is_active']) || (int)$editReply['is_active'] === 1) ? 'checked' : '' ?>>
            <span style="font-size:14px; color:var(--g-text-secondary);">Активен (виден агентам)</span>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary"><?= $editReply ? 'Сохранить ответ' : 'Добавить ответ' ?></button>
            <a href="/admin/quick-replies" class="btn btn-secondary">Отмена</a>
        </div>
    </form>

    <h2 style="font-size: 20px; font-weight: 600; margin: 32px 0 16px;">Список быстрых ответов</h2>
    <?php if (empty($replies)): ?>
        <p style="color: var(--g-text-secondary);">Быстрых ответов пока нет. Используйте форму выше.</p>
    <?php else: ?>
    <table class="admin-table" style="width: 100%;">
        <thead>
            <tr><th>#</th><th>Заголовок</th><th>Шорткат</th><th>Текст</th><th>Статус</th><th>Порядок</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($replies as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['title']) ?></td>
                <td style="font-size: 13px; color: var(--g-accent-light);"><?= e($r['shortcut'] ?: '—') ?></td>
                <td style="font-size: 13px; color: var(--g-text-secondary); max-width: 320px;"><?= e(mb_strimwidth($r['content'], 0, 100, '…')) ?></td>
                <td><span style="padding:2px 8px;border-radius:999px;font-size:12px;background:<?= (int)$r['is_active'] ? 'var(--g-success-dim)' : 'var(--g-text-secondary-dim)' ?>;color:<?= (int)$r['is_active'] ? 'var(--g-success)' : '#fff' ?>;"><?= (int)$r['is_active'] ? 'Активен' : 'Выключен' ?></span></td>
                <td style="font-size:13px;color:var(--g-text-secondary);"><?= (int)$r['sort_order'] ?></td>
                <td style="font-size:13px; white-space: nowrap;">
                    <a href="/admin/quick-replies?edit=<?= (int)$r['id'] ?>" style="color: var(--g-accent-light); margin-right: 10px;">Редактировать</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить ответ «<?= e($r['title']) ?>»?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--g-danger);">Удалить</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
