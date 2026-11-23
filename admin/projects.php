<?php
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Управление проектами';
$errors = [];
$success = null;

$csrf = csrfToken();

/**
 * Сохраняет фото проекта. Возвращает путь (строку) при успехе или текст ошибки для админа.
 * Раньше при неудачной загрузке ошибка молча терялась, поэтому фото «не добавлялось» без объяснения.
 */
function saveProjectPhoto(array $file)
{
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE => 'файл больше лимита upload_max_filesize на сервере',
            UPLOAD_ERR_FORM_SIZE => 'файл больше лимита формы',
            UPLOAD_ERR_PARTIAL => 'файл загружен не полностью',
            UPLOAD_ERR_NO_TMP_DIR => 'на сервере нет временной папки',
            UPLOAD_ERR_CANT_WRITE => 'сервер не может записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'загрузку остановило расширение PHP',
        ];
        return 'Фото не загружено: ' . ($msgs[(int)$file['error']] ?? 'неизвестная ошибка') . '.';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return 'Фото не загружено: допустимы только изображения JPG, PNG, WEBP или GIF.';
    }
    $saved = uploadFile($file, 'projects');
    return $saved ?: 'Фото не загружено: не удалось сохранить файл (проверьте права на папку assets/uploads).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
    }
    $db = getDB();

    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $iconPath = trim($_POST['icon_path'] ?? '');
        if (isset($_FILES['project_image']) && (int)$_FILES['project_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = saveProjectPhoto($_FILES['project_image']);
            if (is_string($upload) && strpos($upload, 'Фото не загружено') === 0) { $errors[] = $upload; } else { $iconPath = $upload; }
        }
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if ($title === '' || $url === '') {
            $errors[] = 'Название и URL обязательны.';
        } elseif (!$errors) {
            // Соцсети — общие для всего сайта, задаются в «Настройках», а не у проекта.
            $db->prepare('INSERT INTO projects (title, url, category, description, icon_path, is_active, sort_order, socials) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$title, $url, trim($_POST['category'] ?? ''), trim($_POST['description'] ?? ''), $iconPath, isset($_POST['is_active']) ? 1 : 0, (int)($_POST['sort_order'] ?? 0), null]);
            redirect('/admin/projects');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $iconPath = trim($_POST['icon_path'] ?? '');
        if (isset($_FILES['project_image']) && (int)$_FILES['project_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = saveProjectPhoto($_FILES['project_image']);
            if (is_string($upload) && strpos($upload, 'Фото не загружено') === 0) { $errors[] = $upload; } else { $iconPath = $upload; }
        }
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if ($id <= 0 || $title === '' || $url === '') {
            $errors[] = 'Неверные данные проекта.';
        } elseif (!$errors) {
            // Соцсети — общие для всего сайта, задаются в «Настройках», а не у проекта.
            $db->prepare('UPDATE projects SET title = ?, url = ?, category = ?, description = ?, icon_path = ?, is_active = ?, sort_order = ?, socials = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$title, $url, trim($_POST['category'] ?? ''), trim($_POST['description'] ?? ''), $iconPath, isset($_POST['is_active']) ? 1 : 0, (int)($_POST['sort_order'] ?? 0), null, $id]);
            redirect('/admin/projects');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
            redirect('/admin/projects');
        }
    }
}

$db = getDB();
$projects = $db->query('SELECT * FROM projects ORDER BY sort_order ASC, id ASC')->fetchAll();
$editProject = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editProject = $stmt->fetch();
    if ($editProject && $editProject['socials']) {
        $editProject['socials_arr'] = json_decode($editProject['socials'], true);
    }
}

include __DIR__ . '/../templates/admin_header.php';
?>

<div style="max-width: 900px;">
    <h1 style="font-size: 22px; font-weight: 600; margin-bottom: 20px;">Управление проектами</h1>

    <?php if ($errors): ?>
        <div style="background: var(--g-danger-dim); border: 1px solid var(--g-danger); border-radius: var(--g-radius); padding: 12px 16px; color: var(--g-danger); margin-bottom: 20px;">
            <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div style="background: var(--g-success-dim); border: 1px solid var(--g-success); border-radius: var(--g-radius); padding: 12px 16px; color: var(--g-success); margin-bottom: 20px;"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 32px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $editProject ? 'update' : 'add' ?>">
        <?php if ($editProject): ?><input type="hidden" name="id" value="<?= (int)$editProject['id'] ?>"><?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div>
                <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Название *</label>
                <input type="text" name="title" class="input" value="<?= e($editProject['title'] ?? '') ?>" required>
            </div>
            <div>
                <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">URL *</label>
                <input type="text" name="url" class="input" placeholder="https://..." value="<?= e($editProject['url'] ?? '') ?>" required>
            </div>
            <div>
                <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Категория</label>
                <input type="text" name="category" class="input" placeholder="Например: Моды, Сборки, Сервисы" value="<?= e($editProject['category'] ?? '') ?>">
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="is_active" value="1" <?= (!isset($editProject['is_active']) || (int)$editProject['is_active'] === 1) ? 'checked' : '' ?>>
                <span style="font-size:14px; color:var(--g-text-secondary);">Активно</span>
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Описание</label>
            <textarea name="description" class="input" rows="3" placeholder="Краткое описание проекта…"><?= e($editProject['description'] ?? '') ?></textarea>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Фото проекта</label>
            <input type="file" name="project_image" class="input" accept="image/jpeg,image/png,image/webp,image/gif">
            <input type="hidden" name="icon_path" value="<?= e($editProject['icon_path'] ?? '') ?>">
            <?php if (!empty($editProject['icon_path'])): ?>
                <div style="display:flex;align-items:center;gap:10px;margin-top:10px;">
                    <img src="<?= e(preg_match('~^https?://~i', $editProject['icon_path']) ? $editProject['icon_path'] : UPLOAD_URL . ltrim($editProject['icon_path'], '/')) ?>" alt="" style="width:96px;height:54px;object-fit:cover;border-radius:8px;border:1px solid var(--g-border);">
                    <span style="font-size:12px;color:var(--g-text-secondary);">Текущее изображение. Новое заменит его.</span>
                </div>
            <?php endif; ?>
            <span style="display:block; font-size: 12px; color: var(--g-text-secondary); margin-top: 6px;">JPG, PNG, WEBP или GIF. Изображение автоматически сохранится в загрузках проекта.</span>
        </div>

        <div style="background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 12px 16px; font-size: 12.5px; color: var(--g-text-secondary); margin-bottom: 16px;">
            Соцсети задаются один раз для всего сайта — в разделе <a href="/admin/settings" style="color: var(--g-accent-light);">«Настройки» → «Соц. сети»</a>.
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Порядок сортировки</label>
            <input type="number" name="sort_order" class="input" value="<?= e($editProject['sort_order'] ?? 0) ?>">
        </div>

        <div style="margin-top: 24px; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary"><?= $editProject ? 'Сохранить проект' : 'Добавить проект' ?></button>
            <a href="/admin/projects" class="btn btn-secondary">Отмена</a>
        </div>
    </form>

    <h2 style="font-size: 20px; font-weight: 600; margin: 32px 0 16px;">Список проектов</h2>
    <?php if (empty($projects)): ?>
        <p style="color: var(--g-text-secondary);">Проекты не добавлены. Используйте форму выше.</p>
    <?php else: ?>
    <table class="admin-table" style="width: 100%;">
        <thead>
            <tr><th>#</th><th>Название</th><th>Категория</th><th>Иконка</th><th>Статус</th><th>Порядок</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $p): ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= e($p['title']) ?></td>
                <td style="color: var(--g-text-secondary); font-size: 13px;"><?= e($p['category'] ?? '-') ?></td>
                <td style="font-size: 13px;">
                    <?php if (!empty($p['icon_path'])): ?><?= e($p['icon_path']) ?>
                    <?php else: ?><span style="color: var(--g-text-secondary);">—</span><?php endif; ?>
                </td>
                <td><span style="padding:2px 8px;border-radius:999px;font-size:12px;background:<?= (int)$p['is_active'] ? 'var(--g-success-dim)' : 'var(--g-text-secondary-dim)' ?>;color:<?= (int)$p['is_active'] ? 'var(--g-success)' : '#fff' ?>;"><?= (int)$p['is_active'] ? 'Активно' : 'Выключено' ?></span></td>
                <td style="font-size:13px;color:var(--g-text-secondary);"><?= (int)$p['sort_order'] ?></td>
                <td style="font-size:13px;">
                    <a href="/admin/projects?edit=<?= (int)$p['id'] ?>" style="color: var(--g-accent-light); margin-right: 10px;">Редактировать</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить проект «<?= e($p['title']) ?>»?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
