<?php // Shared public-page analytics entry point. ?>
<link rel="stylesheet" href="analytics-consent.css?v=<?= substr(hash_file('sha256', __DIR__ . '/analytics-consent.css'), 0, 12) ?>">
<script src="analytics-consent.js?v=<?= substr(hash_file('sha256', __DIR__ . '/analytics-consent.js'), 0, 12) ?>" defer></script>
