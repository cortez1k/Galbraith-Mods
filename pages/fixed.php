<?php
$pageTitle = 'Исправленные сборки';
include __DIR__ . '/../templates/header.php';
$materials = getMaterials(4, null, null, 'new');
?>
    <section style="padding:4rem 0;">
        <div class="container">
            <h2 class="section-title">Исправленные сборки</h2>
            <p style="color:var(--text-2); margin-bottom:2rem;">Все сборки из каталога исправлены и готовы к установке.</p>
            <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));">
                <?php foreach ($materials as $m): ?>
                    <div class="card glass reveal">
                        <?php if (!empty($m['image'])): ?><img src="<?= htmlspecialchars($m['image']) ?>" alt="" class="card-img"><?php endif; ?>
                        <div class="card-body">
                            <div class="card-badge" style="color:var(--accent); font-weight:700;">Исправлено</div>
                            <h3 class="card-title"><?= htmlspecialchars($m['title']) ?></h3>
                            <p class="card-desc"><?= htmlspecialchars($m['desc'] ?? $m['description'] ?? '') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php'; ?>