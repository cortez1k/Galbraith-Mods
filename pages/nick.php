<?php
// Страница генератора ников. Ожидает $nickSlug ('' | arizona | amazing | ...) от роутера.
$projects = nickProjects();
$project = $projects[$nickSlug];
$origins = nickOriginLabels();
$groups = nickGroupDefs();
$day = nickOfDay();
$dayKey = date('Y-m-d');

// Общая ссылка ?r=Nick_One,Nick_Two&k=123
$sharedList = [];
if (!empty($_GET['r'])) {
    foreach (explode(',', (string)$_GET['r']) as $n) {
        if (nickValidNick($n) && count($sharedList) < 8) $sharedList[] = $n;
    }
}
$sharedSkin = (isset($_GET['k']) && preg_match('/^[0-9]{1,5}(-[0-9])?$/', (string)$_GET['k'])) ? (string)$_GET['k'] : '';

// Рекламный блок: 5 случайных материалов каталога
$promo = [];
$pool = [];
foreach (getMaterials() as $m) { if (empty($m['is_premiere'])) $pool[] = $m; }
shuffle($pool);
$promo = array_slice($pool, 0, 5);

$pageTitle = $project['h1'];
$pageTitleFull = $project['title'];
$pageDesc = $project['desc'];
$headExtra = nickSeoHead($nickSlug, $project);
if ($sharedList) $headExtra = str_replace('index,follow,max-image-preview:large', 'noindex,follow', $headExtra);

$cfg = [
    'csrf' => csrfToken(), 'logged' => nickUid() > 0, 'shared' => $sharedList, 'sharedSkin' => $sharedSkin,
    'path' => $project['path'], 'maxLen' => NICK_MAX_LEN, 'v' => (string)@filemtime(__DIR__ . '/../assets/js/nick-gen.js'),
];
include __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/nick.css?v=<?= @filemtime(__DIR__ . '/../assets/css/nick.css') ?: '1' ?>">

<div class="ng">
    <nav class="ng-crumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a><span>›</span>
        <?php if ($nickSlug === ''): ?><span class="cur">Генератор ников</span>
        <?php else: ?><a href="/generator-nikov">Генератор ников</a><span>›</span><span class="cur"><?= e($project['name']) ?></span><?php endif; ?>
    </nav>

    <header class="ng-hero">
        <span class="ng-eyebrow"><i></i> Бесплатно · без регистрации</span>
        <h1><?= e($project['h1']) ?></h1>
        <p><?= e($project['lead']) ?></p>
        <div class="ng-pills"><span>Имя_Фамилия</span><span>до <?= NICK_MAX_LEN ?> символов</span><span>карточка со скином</span><span><?= number_format(count(nickSkins()), 0, ',', ' ') ?> скинов</span></div>
    </header>

    <?php if ($sharedList): ?>
    <div class="ng-shared">🔗 Вам прислали подборку ников — они уже загружены ниже. <a href="<?= e($project['path']) ?>">Сгенерировать свои</a></div>
    <?php endif; ?>

    <section class="ng-tool" id="tool" aria-label="Генератор ников">
        <div class="ng-main">
            <div class="ng-display" id="ngDisplay">
                <div class="ng-reel" data-part="first">
                    <button type="button" class="ng-lock" id="ngLockFirst" title="Закрепить имя" aria-pressed="false">🔓</button>
                    <span class="ng-word" id="ngFirst">Имя</span><small>имя</small>
                </div>
                <span class="ng-us">_</span>
                <div class="ng-reel" data-part="last">
                    <button type="button" class="ng-lock" id="ngLockLast" title="Закрепить фамилию" aria-pressed="false">🔓</button>
                    <span class="ng-word" id="ngLast">Фамилия</span><small>фамилия</small>
                </div>
            </div>
            <div class="ng-meta"><span id="ngTags" class="ng-tags"></span><span class="ng-len" id="ngLen">0 / <?= NICK_MAX_LEN ?></span></div>
            <div class="ng-actions">
                <button type="button" class="btn btn-primary btn-lg" id="ngSpin">🎲 Крутить</button>
                <button type="button" class="btn btn-secondary" id="ngCopy">Копировать</button>
                <button type="button" class="btn btn-secondary" id="ngFav" aria-pressed="false">☆ В избранное</button>
                <button type="button" class="btn btn-secondary" id="ngShare">🔗 Поделиться</button>
            </div>

            <div class="ng-filters">
                <div class="ng-fg"><b>Стиль</b><div class="ng-chips" data-filter="group">
                    <?php foreach ($groups as $k => $g): ?><button type="button" class="ng-chip<?= $k === 'any' ? ' on' : '' ?>" data-v="<?= e($k) ?>"><?= e($g['label']) ?></button><?php endforeach; ?>
                </div></div>
                <div class="ng-fg"><b>Эпоха</b><div class="ng-chips" data-filter="era">
                    <button type="button" class="ng-chip on" data-v="any">Любая</button><button type="button" class="ng-chip" data-v="modern">Современные</button><button type="button" class="ng-chip" data-v="retro">Раритетные</button>
                </div></div>
                <div class="ng-fg"><b>Происхождение</b><div class="ng-chips" data-filter="origin">
                    <button type="button" class="ng-chip on" data-v="any">Любое</button>
                    <?php foreach ($origins as $k => $l): ?><button type="button" class="ng-chip" data-v="<?= e($k) ?>"><?= e($l) ?></button><?php endforeach; ?>
                </div></div>
                <div class="ng-fg"><b>Длина</b><div class="ng-chips" data-filter="length">
                    <button type="button" class="ng-chip on" data-v="any">Любая</button><button type="button" class="ng-chip" data-v="short">Короткий (до 12)</button><button type="button" class="ng-chip" data-v="long">Длинный (17–<?= NICK_MAX_LEN ?>)</button>
                </div></div>
                <div class="ng-fg"><b>Пол</b><div class="ng-chips" data-filter="gender">
                    <button type="button" class="ng-chip on" data-v="any">Любой</button><button type="button" class="ng-chip" data-v="m">Мужской</button><button type="button" class="ng-chip" data-v="f">Женский</button>
                </div></div>
            </div>

            <div class="ng-custom">
                <b>Свой вариант</b>
                <div class="ng-custom-row">
                    <input type="text" id="ngCustomFirst" placeholder="Своё имя (Ivan или Иван)" maxlength="16" autocomplete="off">
                    <input type="text" id="ngCustomLast" placeholder="Своя фамилия (Rossi или Росси)" maxlength="16" autocomplete="off">
                    <button type="button" class="btn btn-secondary" id="ngCustomClear">Сбросить</button>
                </div>
                <div class="ng-hint" id="ngCustomHint">Введите имя или фамилию — вторая часть подберётся к ней по происхождению и роду.</div>
            </div>

            <div class="ng-results-h">Ещё варианты</div>
            <div class="ng-results" id="ngResults" aria-live="polite"></div>
        </div>

        <aside class="ng-side">
            <div class="ng-card-box">
                <canvas id="ngCanvas" width="1200" height="630" aria-label="Карточка персонажа"></canvas>
                <div class="ng-card-actions">
                    <button type="button" class="btn btn-primary" id="ngDownload">⬇ Скачать PNG</button>
                    <button type="button" class="btn btn-secondary" id="ngReskin">🎭 Другой скин</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="ngSkinMode" data-mode="style">Скин: по стилю</button>
                </div>
            </div>
            <?php if ($day): ?>
            <div class="ng-day" id="ngDay" data-nick="<?= e($day['nick']) ?>" data-skin="<?= e($day['skin']) ?>" data-group="<?= e($day['group']) ?>">
                <div class="ng-day-h">⭐ Ник дня · <?= e(date('d.m.Y')) ?></div>
                <div class="ng-day-body">
                    <?php if (!empty($day['skin'])): ?><img src="/assets/img/nick/skins/<?= e($day['skin']) ?>.webp" alt="" width="64" height="80" loading="lazy"><?php endif; ?>
                    <div><b><?= e($day['nick']) ?></b><small><?= e($groups[$day['group']]['label'] ?? '') ?></small></div>
                    <button type="button" class="btn btn-secondary btn-sm" id="ngTakeDay">Взять</button>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </section>

    <section class="ng-tabs" aria-label="Дополнительно">
        <div class="ng-tabbar" role="tablist">
            <button type="button" class="on" data-tab="fancy">✨ Красивое оформление</button>
            <button type="button" data-tab="favs">★ Избранное <i id="ngFavCount">0</i></button>
            <button type="button" data-tab="hist">🕘 История</button>
            <button type="button" data-tab="top">🔥 Топ недели</button>
            <button type="button" data-tab="ach">🏆 Достижения</button>
            <button type="button" data-tab="sug">💡 Предложить слово</button>
        </div>
        <div class="ng-panel on" data-panel="fancy">
            <div class="ng-warn">Эти варианты пригодятся вам в соцсетях (ВК, Telegram, Discord). В игру такой ник не установится — там допустима только латиница в формате Имя_Фамилия.</div>
            <div class="ng-fancy" id="ngFancy"></div>
        </div>
        <div class="ng-panel" data-panel="favs"><div class="ng-list" id="ngFavs"></div></div>
        <div class="ng-panel" data-panel="hist"><div class="ng-list" id="ngHist"></div></div>
        <div class="ng-panel" data-panel="top"><div class="ng-hint">Ники, которые чаще всего копировали, добавляли в избранное и скачивали за последние 7 дней.</div><div class="ng-list" id="ngTop"></div></div>
        <div class="ng-panel" data-panel="ach"><div class="ng-ach" id="ngAch"></div></div>
        <div class="ng-panel" data-panel="sug">
            <?php if (nickUid() > 0): ?>
            <div class="ng-hint">Знаете хорошее имя или фамилию, которых нет в словаре? Отправьте — после проверки модератором слово появится в генераторе.</div>
            <form class="ng-sug" id="ngSuggest">
                <select name="kind"><option value="first">Имя</option><option value="last">Фамилия</option></select>
                <input type="text" name="word" placeholder="Слово (латиницей или кириллицей)" maxlength="16" required>
                <select name="origin"><?php foreach ($origins as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
                <select name="gender"><option value="m">Мужское</option><option value="f">Женское</option></select>
                <select name="era"><option value="any">Любая эпоха</option><option value="modern">Современное</option><option value="retro">Раритетное</option></select>
                <button type="submit" class="btn btn-primary">Отправить</button>
            </form>
            <div class="ng-hint" id="ngSugMsg"></div>
            <?php else: ?>
            <div class="ng-hint">Чтобы предлагать слова, <a href="/login">войдите в аккаунт</a>.</div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($promo): ?>
    <section class="ng-promo">
        <h2 class="section-title">Пока выбираете ник — загляните в каталог</h2>
        <div class="ng-promo-row">
            <?php foreach ($promo as $m): ?>
            <a href="/mod/<?= e($m['slug']) ?>" class="ng-promo-item">
                <span class="ng-promo-img"><?php if ($m['cover_path']): ?><img src="<?= e(UPLOAD_URL . $m['cover_path']) ?>" alt="<?= e($m['title']) ?>" loading="lazy"><?php endif; ?></span>
                <b><?= e($m['title']) ?></b>
                <small><?= $m['access_type'] === 'free' ? 'FREE' : e(formatPrice($m)) ?> · <?= e($m['category_name'] ?? 'Каталог') ?></small>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <article class="ng-seo">
        <h2>Как пользоваться генератором ников для <?= e($project['name']) ?></h2>
        <ol>
            <li><b>Выберите стиль.</b> Банды, мафия, госструктуры, элита или улица — от этого зависят происхождение имён и фамилий.</li>
            <li><b>Нажмите «Крутить».</b> Имя и фамилия подбираются отдельно, а понравившуюся часть можно закрепить замком и перекрутить вторую.</li>
            <li><b>Заберите результат.</b> Скопируйте ник, сохраните в избранное или скачайте карточку персонажа со скином.</li>
        </ol>

        <h2>Формат ника на RP-серверах: Имя_Фамилия</h2>
        <p>На ролевых проектах SA-MP и CRMP ник персонажа записывается латиницей через нижнее подчёркивание: <b>Имя_Фамилия</b>. Обе части начинаются с заглавной буквы, остальные буквы — строчные. Общая длина ограничена 24 символами — это лимит самого SA-MP, и генератор его не нарушает. Правила конкретного сервера (минимальная длина частей, запрет на известные имена) могут быть строже, поэтому перед регистрацией сверьтесь с ними.</p>

        <h2>Стили ников: банды, мафия, госструктуры</h2>
        <div class="ng-styles">
            <?php
            $styleText = [
                'gangs' => 'Уличные имена и фамилии для гангстеров: американские и латино-варианты, иногда резкие «уличные» фамилии.',
                'mafia' => 'Итальянские, русские и ирландские имена в классическом мафиозном духе.',
                'gov' => 'Строгие имена для полиции, армии и спецслужб: нейтральные американские, русские и немецкие сочетания.',
                'elite' => 'Аристократичные фамилии для богатых персонажей: бизнесменов, владельцев компаний, политиков.',
                'street' => 'Короткие, дерзкие, «уличные» сочетания — для тех, кто вырос на районе.',
            ];
            foreach ($styleText as $gk => $txt):
                $samples = nickSamples($gk, 6, $dayKey . '|' . $nickSlug);
            ?>
            <div class="ng-style">
                <h3><?= e($groups[$gk]['label']) ?></h3>
                <p><?= e($txt) ?></p>
                <ul><?php foreach ($samples as $s): ?><li><?= e($s['nick']) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endforeach; ?>
        </div>

        <h2>Свой вариант имени или фамилии</h2>
        <p>Есть идея для половины ника? Введите своё имя или фамилию — можно кириллицей, мы переведём в латиницу. Генератор определит происхождение слова и подберёт вторую часть в тон: к русской фамилии — русское имя, к итальянскому имени — итальянскую фамилию. Для женских персонажей русские фамилии автоматически получают женскую форму (Ivanov → Ivanova).</p>

        <h2>Современные и раритетные имена, короткие и длинные ники</h2>
        <p>Фильтр эпохи переключает между современными именами (Ethan, Mason, Matteo) и раритетными, «старинными» (Walter, Cornelius, Salvatore). Короткие ники до 12 символов удобно писать в чат, а длинные до 24 звучат солиднее и подходят для серьёзных ролей.</p>

        <h2>Частые вопросы</h2>
        <div class="ng-faq">
            <?php foreach (nickFaq($project) as $f): ?>
            <details><summary><?= e($f[0]) ?></summary><div><?= e($f[1]) ?></div></details>
            <?php endforeach; ?>
        </div>

        <h2>Генераторы ников для других проектов</h2>
        <p class="ng-links">
            <?php foreach ($projects as $sl => $p): if ($sl === $nickSlug) continue; ?>
            <a href="<?= e($p['path']) ?>"><?= e($sl === '' ? 'Генератор ников SAMP' : 'Генератор ников ' . $p['name']) ?></a>
            <?php endforeach; ?>
        </p>
        <p>Ищете сборки и моды для игры? Загляните в <a href="/catalog">каталог Galbraith</a> — там собраны сборки, АХК и конфиги для Amazing RP.</p>
    </article>
</div>

<script>window.NICK_CFG = <?= str_replace('</', '<\/', json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>;</script>
<script src="/assets/js/nick-gen.js?v=<?= @filemtime(__DIR__ . '/../assets/js/nick-gen.js') ?: '1' ?>" defer></script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
