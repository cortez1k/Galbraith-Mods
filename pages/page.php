<?php
$slug = $_GET['slug'] ?? '';
$page = getPageBySlug($slug);
if (!$page) { redirect('/'); }
$pageTitle = $page['meta_title'] ?: $page['title'];
$pageDesc = $page['meta_description'] ?: '';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:28px;font-weight:700;margin-bottom:20px;"><?= e($page['title']) ?></h1>
<div style="font-size:15px;line-height:1.7;color:var(--g-text-secondary);">
    <?= $page['content'] ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
