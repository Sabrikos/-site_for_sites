# Задание для внешней анимации

Статус: внешняя генерация ещё не запускалась. Ожидается подключение аккаунта Runway.
Исходник: assets/images/contact-alien.png. На сайте временно восстановлен этот оригинал; SVG-рука и её контроллер отключены.

Нужны три короткие сцены с одним и тем же персонажем и неподвижной камерой: спокойное моргание, приветствие правой рукой, осторожное вытягивание правой руки к точке справа. Начало и конец каждой сцены — исходная сидячая поза. Сохранять ровно две руки, две ноги и две антенны. Запрещены смена дизайна, лишние конечности, деформация лица, движение камеры, масштабирование и новые предметы. Не выдавать непроверенную генерацию за готовую анимацию.

Базовый prompt: Animate the supplied exact purple seated alien. Locked camera, unchanged framing, unchanged face, materials, lighting and proportions. Exactly two arms, two legs and two antennae throughout. Preserve the original character identity. Smooth restrained motion with natural shoulder and elbow articulation. No extra limbs, no duplicated hands, no detached fingers, no body morphing, no camera motion. Begin and finish in the reference sitting pose. Keep the background consistent and suitable for compositing.

Перед интеграцией просмотреть все кадры на дополнительные части тела, совпадение размеров, возврат в исходную позу и качество контура. Видео само по себе не обеспечивает слежение за мышью: это отдельный интерактивный слой или полноценный rig/state machine. Не подключать предыдущие неудачные SVG-манипуляции как якобы исправленную версию.
