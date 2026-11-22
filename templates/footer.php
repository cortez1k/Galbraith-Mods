    </main>
    <footer class="footer-glass glass" style="padding:3rem 0 2rem; margin-top:3rem;">
        <div class="container">
            <div style="display:flex; flex-wrap:wrap; gap:2rem; justify-content:space-between; margin-bottom:2rem;">
                <div style="font-family:Rajdhani, Manrope, sans-serif; font-size:1.3rem; font-weight:700;">Galbraith Mods</div>
                <div style="display:flex; gap:1.5rem;">
                    <a href="/privacy-policy" style="color:var(--text-2); text-decoration:none;">Конфиденциальность</a>
                    <a href="/personal-data-consent" style="color:var(--text-2); text-decoration:none;">Согласие на обработку данных</a>
                    <a href="/terms" style="color:var(--text-2); text-decoration:none;">Условия</a>
                </div>
            </div>
            <div style="display:flex; gap:1rem;">
                <?php if (setting('social_yt')): ?><a href="<?= htmlspecialchars(setting('social_yt')) ?>" style="color:var(--text-2);">YouTube</a><?php endif; ?>
                <?php if (setting('social_vk')): ?><a href="<?= htmlspecialchars(setting('social_vk')) ?>" style="color:var(--text-2);">VK</a><?php endif; ?>
            </div>
            <div style="margin-top:2rem; color:var(--text-2); font-size:.85rem; text-align:center;">
                &copy; <?= date('Y') ?> Galbraith Mods. Все права защищены.
            </div>
        </div>
    </footer>

    <div id="themeModal" style="position:fixed; inset:0; z-index:9999; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.6);">
        <div class="glass" style="padding:2rem; max-width:400px; width:90%;">
            <h3 style="margin-top:0; font-family:Rajdhani, Manrope, sans-serif;">Тема оформления</h3>
            <button class="btn-primary btn" onclick="toggleLightMode(); document.getElementById('themeModal').style.display='none';" style="width:100%;">Переключить тему</button>
            <button class="btn-secondary btn" style="width:100%; margin-top:.5rem;" onclick="document.getElementById('themeModal').style.display='none';">Закрыть</button>
        </div>
    </div>

    <script>
        function toggleLightMode() { document.body.classList.toggle('light-mode'); }
        function toggleThemeModal() { var m = document.getElementById('themeModal'); if (m) m.style.display = (m.style.display === 'flex') ? 'none' : 'flex'; }
    </script>
</body>
</html>