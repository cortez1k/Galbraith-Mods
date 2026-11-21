<?php
$slug = $_GET['slug'] ?? '';
$material = getMaterialBySlug($slug);
if (!$material) { redirect('/'); }

$isPremiere = !empty($material['publish_at']) && strtotime($material['publish_at']) > time();

$db = getDB();
$db->prepare('UPDATE materials SET views_count = views_count + 1 WHERE id = ?')->execute([$material['id']]);

$comments = getComments($material['id']);
$rating = getMaterialRating($material['id']);
$myRating = !empty($_SESSION['user_id']) ? getUserMaterialRating($material['id'], (int)$_SESSION['user_id']) : null;
$iRemind = !empty($_SESSION['user_id']) ? hasPremiereReminder($material['id'], (int)$_SESSION['user_id']) : false;
$similar = getSimilarMaterials($material['id'], $material['category_id']);

$completed = json_decode($_COOKIE['completed_tasks'] ?? '[]', true);
$tasks = getTasks();
$requiredIds = array_column($tasks, 'id');
$allDone = count(array_intersect($requiredIds, $completed)) === count($requiredIds);

$pageTitle = $material['title'];
$pageDesc = mb_substr(strip_tags($material['description']), 0, 160);
include __DIR__ . '/../templates/header.php';
?>

<a href="/catalog" class="btn btn-secondary" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Назад
</a>

<?php if ($isPremiere): ?>
<div class="alert" style="margin-bottom:20px;background:var(--g-accent-dim);border:1px solid rgba(232,28,90,0.35);display:flex;align-items:center;gap:10px;">
    <span style="font-size:18px;">🚀</span>
    <span style="font-weight:600;color:var(--g-accent-light);">Это премьера — всю информацию можно посмотреть уже сейчас, а скачивание/покупка откроется по таймеру.</span>
</div>
<?php endif; ?>

<div class="material-header" style="display:flex;gap:24px;margin-bottom:24px;flex-wrap:wrap;">
    <div class="material-cover" style="width:320px;height:200px;background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:var(--g-radius);flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid var(--g-border);overflow:hidden;position:relative;">
        <?php if ($isPremiere): ?><span class="badge-premiere-tag">🚀 ПРЕМЬЕРА</span><?php endif; ?>
        <?php if ($material['cover_path']): ?>
            <img src="<?= e(UPLOAD_URL . $material['cover_path']) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--g-accent)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        <?php endif; ?>
    </div>
    <div style="flex:1;min-width:280px;">
        <span class="card-badge <?= $material['access_type'] === 'free' ? 'badge-free' : 'badge-paid' ?>" style="position:static;display:inline-block;margin-bottom:10px;">
            <?= $material['access_type'] === 'free' ? 'FREE' : formatPrice($material) ?>
        </span>
        <h1 style="font-size:24px;font-weight:600;margin-bottom:12px;"><?= e($material['title']) ?></h1>
        <div style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:14px;margin-bottom:20px;">
            <span style="color:var(--g-text-secondary);">Версия игры</span><span style="font-weight:500;"><?= e($material['game_version'] ?: '—') ?></span>
            <span style="color:var(--g-text-secondary);">Размер</span><span style="font-weight:500;"><?= e($material['file_size'] ?: '—') ?></span>
            <span style="color:var(--g-text-secondary);"><?= $isPremiere ? 'Выход' : 'Обновлено' ?></span><span style="font-weight:500;"><?= date('d.m.Y', strtotime($isPremiere ? $material['publish_at'] : $material['updated_at'])) ?></span>
            <?php if (!$isPremiere): ?><span style="color:var(--g-text-secondary);">Скачиваний</span><span style="font-weight:500;"><?= number_format($material['downloads_count'], 0, ',', ' ') ?></span><?php endif; ?>
        </div>

        <?php if ($isPremiere): ?>
            <div id="premiereCountdown" data-target="<?= e(date('c', strtotime($material['publish_at']))) ?>" class="premiere-countdown-box">
                <div class="premiere-countdown-label">До выхода материала</div>
                <div class="premiere-countdown-units">
                    <div class="countdown-unit"><span class="countdown-num" data-unit="days">00</span><span class="countdown-label">дней</span></div>
                    <div class="countdown-unit"><span class="countdown-num" data-unit="hours">00</span><span class="countdown-label">часов</span></div>
                    <div class="countdown-unit"><span class="countdown-num" data-unit="minutes">00</span><span class="countdown-label">минут</span></div>
                    <div class="countdown-unit"><span class="countdown-num" data-unit="seconds">00</span><span class="countdown-label">секунд</span></div>
                </div>
                <div class="premiere-countdown-date">Публикация: <?= date('d.m.Y в H:i', strtotime($material['publish_at'])) ?></div>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <form action="/materials/<?= $material['id'] ?>/remind" method="POST" style="margin-top:14px;">
                        <button type="submit" class="remind-btn<?= $iRemind ? ' is-active' : '' ?>">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <?= $iRemind ? '✓ Напомним, когда выйдет' : 'Напомнить о выходе' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div style="margin-top:14px;font-size:13px;color:var(--g-text-secondary);">
                        <a href="/login" style="color:var(--g-accent-light);font-weight:600;">Войдите</a>, чтобы получить уведомление о выходе
                    </div>
                <?php endif; ?>
            </div>
            <script>
            (function() {
                var box = document.getElementById('premiereCountdown');
                if (!box) return;
                var target = new Date(box.dataset.target).getTime();
                function tick() {
                    var diff = target - Date.now();
                    if (diff <= 0) { location.reload(); return; }
                    var v = { days: Math.floor(diff / 86400000), hours: Math.floor((diff % 86400000) / 3600000), minutes: Math.floor((diff % 3600000) / 60000), seconds: Math.floor((diff % 60000) / 1000) };
                    for (var k in v) { var el = box.querySelector('[data-unit="' + k + '"]'); if (el) el.textContent = String(v[k]).padStart(2, '0'); }
                }
                tick(); setInterval(tick, 1000);
            })();
            </script>
        <?php elseif ($material['access_type'] === 'paid'): ?>
            <button class="btn btn-primary" onclick="<?= $allDone ? "document.getElementById('payModal').classList.add('active')" : "showToast('Сначала выполните все задания')" ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4h-4z"/></svg>
                Купить за <?= formatPrice($material) ?>
            </button>
            <div style="margin-top:12px;background:rgba(255,159,10,0.08);border:1px solid rgba(255,159,10,0.2);border-radius:var(--g-radius-sm);padding:12px 14px;display:flex;align-items:flex-start;gap:10px;">
                <span style="font-size:18px;flex-shrink:0;">⚠️</span>
                <div style="font-size:13px;color:var(--g-text-secondary);line-height:1.5;">
                    <strong style="color:#FF9F0A;">Donation Alerts:</strong> комиссия платформы <strong><?= e(formatPercent(setting('da_commission_percent','15'))) ?>%</strong>. 
                    Если сумма доната меньше стоимости с учётом комиссии — потребуется <strong>доплата</strong>.
                </div>
            </div>
        <?php else: ?>
            <form action="/download/<?= $material['id'] ?>/track" method="POST" style="display:inline;">
                <button type="submit" class="btn btn-primary" <?= !$allDone ? 'disabled' : '' ?>>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    Скачать
                </button>
            </form>
        <?php endif; ?>

        <?php if (!$isPremiere && !$allDone): ?>
            <div style="margin-top:14px;background:rgba(255,159,10,0.08);border:1px solid rgba(255,159,10,0.2);border-radius:var(--g-radius-sm);padding:14px 18px;display:flex;align-items:center;gap:12px;">
                <span style="font-size:22px;">⚠️</span>
                <span style="font-size:15px;font-weight:600;color:#FF9F0A;">Выполните все задания ниже, чтобы получить доступ к скачиванию</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isPremiere && count($tasks) > 0): ?>
<div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:14px;">🔐 Выполните задания для доступа</h3>
    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($tasks as $index => $task): 
            $isDone = in_array($task['id'], $completed);
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--g-bg);border-radius:var(--g-radius-sm);border:1px solid var(--g-border);<?= $isDone ? 'opacity:0.6;' : '' ?>">
            <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;<?= $isDone ? 'background:rgba(52,199,89,0.15);color:#34C759;' : 'background:var(--g-card);color:var(--g-text-secondary);' ?>">
                <?= $isDone ? '✓' : ($index + 1) ?>
            </div>
            <div style="flex:1;font-size:14px;">
                <?= e($task['title']) ?>
            </div>
            <?php if ($isDone): ?>
                <span style="font-size:12px;color:#34C759;font-weight:600;">Выполнено</span>
            <?php else: ?>
                <a href="<?= e($task['url']) ?>" target="_blank" class="btn btn-primary btn-sm" onclick="completeTask(<?= $task['id'] ?>)" style="text-decoration:none;">Выполнить</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($allDone): ?>
        <div style="margin-top:14px;background:rgba(52,199,89,0.08);border:1px solid rgba(52,199,89,0.2);border-radius:var(--g-radius-sm);padding:14px 18px;display:flex;align-items:center;gap:12px;">
            <span style="font-size:22px;">✅</span>
            <span style="font-size:15px;font-weight:600;color:#34C759;">Все задания выполнены! Можно скачивать.</span>
        </div>
    <?php else: ?>
        <div style="margin-top:14px;background:rgba(255,59,48,0.08);border:1px solid rgba(255,59,48,0.2);border-radius:var(--g-radius-sm);padding:14px 18px;display:flex;align-items:center;gap:12px;">
            <span style="font-size:22px;">🔒</span>
            <span style="font-size:15px;font-weight:600;color:#FF3B30;">Выполните все задания выше, чтобы разблокировать скачивание</span>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="background:var(--g-card);border-radius:var(--g-radius);padding:20px;border:1px solid var(--g-border);margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Описание</h3>
    <div style="font-size:14px;line-height:1.7;color:var(--g-text-secondary);">
        <?= $material['description'] ?>
    </div>
</div>

<?php if (!$isPremiere): ?>
<div id="rating" style="background:var(--g-card);border-radius:var(--g-radius);padding:20px;border:1px solid var(--g-border);margin-bottom:24px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
    <div>
        <div style="font-size:12px;color:var(--g-text-secondary);margin-bottom:6px;">Рейтинг материала</div>
        <div style="display:flex;align-items:baseline;gap:8px;">
            <span style="font-family:var(--g-font-display);font-size:28px;font-weight:700;color:#FFB454;"><?= $rating['cnt'] > 0 ? number_format($rating['avg'], 1) : '—' ?></span>
            <span style="font-size:13px;color:var(--g-text-secondary);"><?= $rating['cnt'] > 0 ? ($rating['cnt'] . ' ' . pluralRu($rating['cnt'], 'оценка', 'оценки', 'оценок')) : 'пока нет оценок' ?></span>
        </div>
    </div>
    <?php if (!empty($_SESSION['user_id'])): ?>
        <form action="/materials/<?= $material['id'] ?>/rate" method="POST" class="star-rating" id="starRatingForm">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="submit" name="rating" value="<?= $i ?>" class="<?= $myRating && $i <= $myRating ? 'is-filled' : '' ?>" onmouseover="previewStars(this,<?= $i ?>)" title="Оценить на <?= $i ?>">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                </button>
            <?php endfor; ?>
        </form>
        <?php if ($myRating): ?><span style="font-size:12.5px;color:var(--g-text-secondary);">Ваша оценка: <?= $myRating ?> из 5</span><?php endif; ?>
        <script>
        function previewStars(btn, n) {
            var form = document.getElementById('starRatingForm');
            var stars = form.querySelectorAll('button');
            stars.forEach(function(s, i) { s.style.color = (i < n) ? '#FFB454' : ''; });
        }
        document.getElementById('starRatingForm')?.addEventListener('mouseleave', function() {
            this.querySelectorAll('button').forEach(function(s) { s.style.color = ''; });
        });
        </script>
    <?php else: ?>
        <a href="/login" style="font-size:13px;color:var(--g-accent-light);font-weight:600;">Войдите, чтобы поставить оценку</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php $screenshots = json_decode($material['screenshots'] ?? '[]', true) ?: []; if ($screenshots): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Скриншоты</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
        <?php foreach ($screenshots as $shot): ?>
            <div style="border-radius:var(--g-radius-sm);overflow:hidden;border:1px solid var(--g-border);aspect-ratio:16/9;"><img src="<?= e(UPLOAD_URL . $shot) ?>" style="width:100%;height:100%;object-fit:cover;" loading="lazy"></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($material['youtube_url']): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Видео</h3>
    <div style="position:relative;padding-bottom:56.25%;border-radius:var(--g-radius);overflow:hidden;">
        <?php
        $ytId = '';
        if (preg_match('/(?:v=|\.be\/|embed\/)([a-zA-Z0-9_-]{11})/', $material['youtube_url'], $m)) $ytId = $m[1];
        ?>
        <?php if ($ytId): ?>
        <iframe src="https://www.youtube.com/embed/<?= e($ytId) ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen></iframe>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($similar): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:14px;">Похожие материалы</h3>
    <div class="grid">
        <?php foreach ($similar as $s): ?>
        <a href="/mod/<?= e($s['slug']) ?>" class="card">
            <div class="card-img">
                <?php if ($s['cover_path']): ?>
                    <img src="<?= e(UPLOAD_URL . $s['cover_path']) ?>" alt="<?= e($s['title']) ?>">
                <?php else: ?>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--g-accent)" stroke-width="1.2" opacity="0.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                <?php endif; ?>
                <span class="card-badge <?= $s['access_type'] === 'free' ? 'badge-free' : 'badge-paid' ?>"><?= $s['access_type'] === 'free' ? 'FREE' : formatPrice($s) ?></span>
            </div>
            <div class="card-body">
                <div class="card-title"><?= e($s['title']) ?></div>
                <div class="card-desc"><?= e($s['description']) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Comments -->
<?php
$loggedIn = !empty($_SESSION['user_id']);
$renderComment = function ($c, $depth = 0) use (&$renderComment, $loggedIn, $material) { ?>
    <div class="comment<?= $depth ? ' comment-reply' : '' ?>" id="comment-<?= (int)$c['id'] ?>">
        <div class="comment-header">
            <span class="comment-author"><?= e($c['author_name']) ?></span>
            <span class="comment-date"><?= date('d.m, H:i', strtotime($c['created_at'])) ?></span>
        </div>
        <p class="comment-text"><?= e($c['content']) ?></p>
        <?php if ($loggedIn): ?>
            <button type="button" class="comment-reply-btn" onclick="toggleReply(<?= (int)$c['id'] ?>)">↩ Ответить</button>
            <form action="/comments/<?= (int)$material['id'] ?>" method="POST" class="comment-reply-form" id="reply-form-<?= (int)$c['id'] ?>" style="display:none;">
                <input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>">
                <input type="text" name="content" class="input" placeholder="Ответ для <?= e($c['author_name']) ?>..." required maxlength="500">
                <button type="submit" class="btn btn-primary btn-sm">Отправить</button>
            </form>
        <?php endif; ?>
        <?php foreach ($c['replies'] as $r): $renderComment($r, min($depth + 1, 3)); endforeach; ?>
    </div>
<?php }; ?>
<div id="comments" style="border-top:1px solid var(--g-border);padding-top:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;">Комментарии (<?= countCommentsTotal($comments) ?>)</h3>

    <?php if ($loggedIn): ?>
    <form action="/comments/<?= $material['id'] ?>" method="POST" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
        <input type="text" name="content" class="input" placeholder="Написать комментарий..." required style="flex:1;min-width:200px;" maxlength="500">
        <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
        </button>
    </form>
    <?php else: ?>
    <div class="comment-login-hint">
        <span>💬 Комментировать и отвечать могут только авторизованные пользователи.</span>
        <a href="/login" class="btn btn-primary btn-sm">Войти</a>
    </div>
    <?php endif; ?>

    <?php foreach ($comments as $c) $renderComment($c); ?>
    <?php if (!$comments): ?><p style="color:var(--g-text-secondary);font-size:14px;">Пока нет комментариев — будьте первым.</p><?php endif; ?>
</div>
<script>function toggleReply(id){var f=document.getElementById('reply-form-'+id);if(!f)return;f.style.display=f.style.display==='none'?'flex':'none';if(f.style.display==='flex')f.querySelector('input[name=content]').focus();}</script>

<!-- Support -->
<div style="margin-top:24px;text-align:center;">
    <a href="/support" class="btn btn-secondary">🆘 Сообщить о проблеме</a>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal">
        <h3>Оплата</h3>
        <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:16px;">Переведите сумму по реквизитам. После оплаты нажмите «Я оплатил».</p>
        <div style="padding:16px;background:var(--g-card);border-radius:var(--g-radius-sm);font-family:monospace;font-size:14px;border:1px solid var(--g-border);margin-bottom:16px;">
            <div>Сумма: <strong><?= formatPrice($material) ?></strong></div>
            <div style="margin-top:8px;"><?= nl2br(e(setting('donate_details'))) ?></div>
        </div>
        <a href="/payment-success/<?= $material['id'] ?>" class="btn btn-primary" style="width:100%;margin-bottom:8px;display:block;text-align:center;">Я оплатил</a>
        <button class="btn btn-secondary" style="width:100%;" onclick="document.getElementById('payModal').classList.remove('active')">Отмена</button>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
