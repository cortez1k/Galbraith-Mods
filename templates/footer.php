<?php
// templates/footer.php
?>
    </main>
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Galbraith. Все права защищены.</p>
            <div style="margin-top: 10px;">
                <a href="/pages/legal.php">Пользовательское соглашение</a>
                <a href="/support">Техническая поддержка</a>
            </div>
        </div>
    </footer>
    
    <script src="/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
    <script src="/assets/js/notify-live.js?v=<?= filemtime(__DIR__ . '/../assets/js/notify-live.js') ?>"></script>
    <script src="/assets/js/support-widget.js?v=<?= filemtime(__DIR__ . '/../assets/js/support-widget.js') ?>"></script>
</body>
</html>
