<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Requesty') ?></title>
    
    <!-- Fuentes e Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Base del Sistema -->
    <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
    
    <?php if (!empty($extraCss)): ?>
        <?php foreach ((array)$extraCss as $css): ?>
            <link rel="stylesheet" href="<?= asset($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($extraHead)): ?>
        <?= $extraHead ?>
    <?php endif; ?>
    <!-- Anti-flicker de colapso de barra lateral -->
    <script>
        (function() {
            try {
                if (localStorage.getItem('rq_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-is-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>
