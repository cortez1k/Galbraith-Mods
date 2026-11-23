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
    <script src="/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>