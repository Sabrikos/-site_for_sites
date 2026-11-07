# Анимация второго пришельца — подготовка

Источник: assets/images/tariffs-alien.png,1244×1264. Сам персонаж не меняется.
Подготовлен assets/alien/tariffs/reference-green.png: та же композиция на зелёном
фоне; удалены отдельные слабые альфа-артефакты вне персонажа. Это вход в Kling,
не изображение для показа на сайте.

## Статус

Готово: два ролика Kling3.0Turbo720p по3секунды (48кредитов, остаток18).
Моргание выбрано из первых1.1секунды (27кадров), приветствие73кадра.
Сохраняются нативные цельные кадры, без разрезания конечностей и слежения.
Атласы320×326 сохраняют исходные пропорции; короткие переходы к позе ожидания
построены scripts/tariffs-alien-build.py. Исходные видео остались в D:/нарезыч/.
Manifest assets/alien/tariffs/idle-manifest.js активирует canvas в tariffs.php.
Общий проигрыватель contact-alien.js ставит анимацию на паузу вне экрана,
останавливает её при reduced motion и оставляет PNG при ошибке загрузки.
Отдельный нижний правый знак скрывается вне персонажа; ступни целы.

Проверено: PHP lint,4теста проигрывателя, браузерная страница tariffs.php#other
(прозрачный фон,320×326canvas, без ошибок console). Сайт не опубликован.

## Prompt1 — blink

Animate this EXACT seated purple alien without changing its identity, proportions,
face, original head angle, antennae or glossy purple lighting. A single natural
soft blink: eyelids close together once and reopen, then remain open. Keep its
existing raised open hand and the other resting hand in exactly their ORIGINAL
positions. Torso and seated legs stay still. No waving, no mouth movement, no
camera motion, no zoom, no new limbs or particles. Fixed full-body framing,
perfectly flat bright green background and constant lighting. Begin and end in
the exact attached pose. Silent.

## Prompt2 — greeting

Animate this EXACT seated purple alien as one coherent glossy3D character. It
turns its eyes and head gently toward the camera and gives a small friendly wave
with the EXISTING open hand on IMAGE LEFT, moving naturally from the wrist.
Keep the upper arm and elbow soft and anatomically connected, no stretched or
rubber limb, no extra hand or fingers. The other hand remains resting, legs stay
in the exact seated pose. Preserve original proportions, facial design, antennae,
glossy purple material and lighting. Return to the attached starting pose.
Locked camera, fixed full-body scale, flat solid bright green background. No
speech, no mouth motion, no props, no cuts or particles. Silent.

## Проверка перед подключением

Реальные кадры должны соответствовать исходной позе и освещению. Не подменять
персонажа первым пришельцем, не разрезать руки, не включать слежение. Для атласа
сохранять соотношение1244/1264; вариант300×400 исказит этого персонажа. Проверить
стыки, моргание, отсутствие лишних конечностей и обрезания антенн/ступней.
