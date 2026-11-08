<?php

declare(strict_types=1);

/** Render catalog features in compact or detailed form, with the same escaping. */
function renderServiceFeatures(array $features, string $class = '', ?int $limit = null): void
{
    $items = $limit === null ? $features : array_slice($features, 0, $limit);
    if (!$items) return;
    echo '<ul' . ($class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '') . '>';
    foreach ($items as $feature) echo '<li>' . htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8') . '</li>';
    echo '</ul>';
}
