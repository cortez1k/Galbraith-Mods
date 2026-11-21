</main>

    <footer class="footer">
        <div class="container">
            <p>Galbraith © <?= date('Y') ?> — Сборки и моды для AMAZING RP</p>
            <div style="margin-top:10px;font-size:13px"><span style="color:var(--g-text-secondary);margin-right:8px">Документы:</span><a href="/privacy-policy">Политика конфиденциальности</a><a href="/personal-data-consent">Согласие на обработку персональных данных</a><a href="/terms">Пользовательское соглашение</a></div>
            <div style="margin-top: 8px;">
                <?php $yt = setting('social_yt'); $vk = setting('social_vk'); ?>
                <?php if ($yt): ?><a href="<?= e($yt) ?>" target="_blank">YouTube</a><?php endif; ?>
                
                <?php if ($vk): ?><a href="<?= e($vk) ?>" target="_blank">VK</a><?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- Theme Modal -->
    <div class="modal-overlay" id="themeModal" onclick="if(event.target===this)toggleThemeModal()">
        <div class="modal">
            <h3>Настройки темы</h3>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <span style="font-size:14px;color:var(--g-text-secondary);">Светлая тема</span>
                <button id="themeToggleBtn" onclick="toggleLightMode()" style="width:48px;height:26px;border-radius:13px;background:var(--g-card);border:1px solid var(--g-border);position:relative;transition:all 0.2s;">
                    <span id="themeToggleDot" style="position:absolute;width:20px;height:20px;border-radius:50%;background:var(--g-text);top:2px;left:2px;transition:all 0.2s;"></span>
                </button>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <span style="font-size:14px;color:var(--g-text-secondary);">Акцентный цвет</span>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="color" id="accentPicker" value="#E81C5A" onchange="updateAccent(this.value)" style="width:40px;height:40px;border:none;border-radius:8px;cursor:pointer;background:none;">
                    <span id="accentValue" style="font-size:13px;color:var(--g-text-secondary);">#E81C5A</span>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <span style="font-size:14px;color:var(--g-text-secondary);">Фон страницы</span>
                <input type="color" id="bgPicker" value="#0E0E0E" onchange="updateBg(this.value)" style="width:40px;height:40px;border:none;border-radius:8px;cursor:pointer;background:none;">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <span style="font-size:14px;color:var(--g-text-secondary);">Фон карточек</span>
                <input type="color" id="cardPicker" value="#0F0F0F" onchange="updateCard(this.value)" style="width:40px;height:40px;border:none;border-radius:8px;cursor:pointer;background:none;">
            </div>
            <button class="btn btn-secondary" style="width:100%;" onclick="resetTheme()">Сбросить настройки</button>
        </div>
    </div>

    <!-- Donate Modal -->
    <div class="modal-overlay" id="donateModal" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal">
            <h3>Поддержать проект</h3>
            <div style="color:var(--g-text-secondary);font-size:14px;margin-bottom:16px;"><?= setting('donate_thanks_text') ?></div>
            <div style="padding:16px;background:var(--g-card);border-radius:var(--g-radius-sm);font-family:monospace;font-size:14px;border:1px solid var(--g-border);margin-bottom:16px;">
                <?= nl2br(e(setting('donate_details'))) ?>
            </div>
            <button class="btn btn-secondary" style="width:100%;" onclick="document.getElementById('donateModal').classList.remove('active')">Закрыть</button>
        </div>
    </div>

    <!-- Виджет поддержки -->
    <?php $swIsHome = (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/'); $swLogged = !empty($_SESSION['user_id']); ?>
    <div id="supportWidget" class="sw" data-home="<?= $swIsHome ? '1' : '0' ?>" data-logged="<?= $swLogged ? '1' : '0' ?>">
        <button type="button" class="sw-launcher" id="swLauncher" aria-label="Открыть поддержку">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span>Поддержка</span><i class="sw-dot" id="swDot" hidden></i>
        </button>
        <div class="sw-panel" id="swPanel" role="dialog" aria-label="Поддержка">
            <div class="sw-head">
                <div><strong>Есть вопросы?</strong><span>Пиши — поможем</span></div>
                <button type="button" class="sw-close" id="swClose" aria-label="Свернуть">✕</button>
            </div>
            <div class="sw-body" id="swBody"><div class="sw-hello">👋 Привет! Опиши проблему или вопрос — ответим прямо здесь.</div></div>
            <?php if ($swLogged): ?>
            <form class="sw-form" id="swForm"><input type="text" id="swInput" placeholder="Напиши сообщение..." maxlength="2000" autocomplete="off"><button type="submit" aria-label="Отправить">➤</button></form>
            <?php else: ?>
            <div class="sw-guest"><a href="/login" class="btn btn-primary btn-sm">Войти, чтобы написать</a></div>
            <?php endif; ?>
        </div>
    </div>
    <script src="/assets/js/support-widget.js?v=<?= @filemtime(__DIR__ . '/../assets/js/support-widget.js') ?: '1' ?>"></script>

    <div class="toast" id="toast"></div>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/notify-live.js?v=<?= @filemtime(__DIR__ . '/../assets/js/notify-live.js') ?: '1' ?>"></script>
</body>
</html>
