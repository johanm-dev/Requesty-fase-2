    <?php if (!empty($extraScripts)): ?>
        <?php foreach ((array)$extraScripts as $script): ?>
            <script src="<?= asset($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
