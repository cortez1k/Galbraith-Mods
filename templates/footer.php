<?php
// templates/footer.php
?>
    </main>
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Galbraith. Все права защищены.</p>
            <div style="margin-top: 10px;">
                <a href="/pages/legal.php">Пользовательское соглашение</a>
                <a href="/pages/support.php">Техподдержка</a>
            </div>
        </div>
    </footer>
    
    <!-- Support Widget -->
    <div id="supportWidget" data-logged="<?= (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])) ? '1' : '0' ?>" data-home="<?= (isset($_GET['page']) ? '0' : '1') ?>">
        <div id="swPanel" class="sw-panel">
            <div class="sw-header">
                <strong>Поддержка</strong>
                <button id="swClose" type="button" class="sw-close" aria-label="Закрыть">✕</button>
            </div>
            <div id="swBody" class="sw-body"></div>
            <div class="sw-footer">
                <form id="swForm" style="display: flex; gap: 8px;">
                    <input id="swInput" type="text" placeholder="Опиши проблему…" class="sw-input" autocomplete="off">
                    <button type="submit" class="sw-send" aria-label="Отправить">➤</button>
                </form>
            </div>
        </div>
        <button id="swLauncher" type="button" class="sw-launcher" aria-label="Открыть чат поддержки" title="Поддержка">
            💬
            <span id="swDot" class="sw-dot" hidden></span>
        </button>
    </div>
    
    <script src="/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
    <script src="/assets/js/notify-live.js?v=<?= filemtime(__DIR__ . '/../assets/js/notify-live.js') ?>"></script>
    <script src="/assets/js/support-widget.js?v=<?= filemtime(__DIR__ . '/../assets/js/support-widget.js') ?>"></script>
</body>
</html>