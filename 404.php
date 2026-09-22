<?php
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Страница не найдена — Vega Studio</title>
  <link rel="icon" href="/assets/images/favicon-32x32.png">
  <link rel="stylesheet" href="/error-page.css?v=hero-hover-3">
</head>
<body>
<main class="error-page">
  <div class="scene-frame">
    <img class="error-page__scene" src="/assets/images/404-space-scene.png?v=20260922-2" width="1672" height="941" alt="" aria-hidden="true" draggable="false" fetchpriority="high">
  <section class="error-copy" aria-labelledby="error-heading">
    <div class="error-code">404</div>
    <h1 id="error-heading">Упс, мы потеряли сигнал этой страницы.</h1>
    <p>Давайте вернёмся туда, где всё работает.</p>
    <div class="error-actions">
      <a class="button button-primary" href="/index.php#top">На главную</a>
      <a class="button button-secondary" href="/index.php#application">Обсудить проект</a>
    </div>
  </section>


  </div>
</main>
</body>
</html>
