<?php
$pageTitle = 'Проекты';
include __DIR__ . '/../templates/header.php';
$materials = getMaterials(4, null, null, 'new');
?>
    <section style="padding:4rem 0;">
        <div class="container">
            <h2 class="section-title">Наши проекты</h2>
            <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));">
                <?php foreach ($materials as $p): ?>
                    <div class="tile glass" style="padding:1.5rem; display:flex; flex-direction:column; gap:.8rem;">
                        <div class="card-badge" style="color:var(--accent-light);"><?= htmlspecialchars($p['type'] ?? 'Проект') ?></div>
                        <h3 style="margin:0; font-family:Rajdhani, Manrope, sans-serif; font-size:1.4rem;"><?= htmlspecialchars($p['title']) ?></h3>
                        <p style="color:var(--text-2); font-size:.9rem; margin:0;"><?= htmlspecialchars($p['desc'] ?? $p['description'] ?? '') ?></p>
                        <a href="/projects/<?= htmlspecialchars($p['slug'] ?? $p['id']) ?>" class="btn-primary btn" style="margin-top:auto;">Подробнее</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php'; ?>