<?php

declare(strict_types=1);
require_once __DIR__ . '/RetentionPolicy.php';

final class ConsentService
{
    public const PURPOSE_CODE = 'project_request';
    public const CONSENT_VERSION = 'project-request-v5-draft';
    public const PRIVACY_POLICY_VERSION = '2026-11-10-draft-1';
    public const EVIDENCE_MONTHS = 36;
    private const OPERATOR_TEXT = 'Я свободно, своей волей и в своём интересе даю Полехиной Ксении Евгеньевне, физическому лицу — плательщику НПД, самостоятельно действующему под обозначением Vega Studio, согласие на обработку указанных ниже персональных данных. Сайт: vegastud1o.ru. Место деятельности оператора: Россия, Ростовская область, г. Ростов-на-Дону. Контакты: Ve9a.Studio@yandex.ru, +7 938 116-31-64.';
    private const PURPOSE_TEXT = 'Цель согласия — рассмотрение моей заявки, ответ на обращение, обсуждение задачи, подготовка предложения и согласование возможного заказа на разработку или доработку сайта. Оно относится к форме обсуждения проекта и оформлению заявки из корзины.';
    private const DATA_TEXT = 'Данные: имя, номер телефона, выбранные услуги или тарифы, описание задачи, пожелания, срочность и дополнительные возможности проекта, если я их указываю; дата, источник и идентификатор обращения. Поля email в формах нет. Для регистрации согласия фиксируются его дата, источник, цель, принятый текст, версии согласия и политики и контрольные значения. Паспорт, адрес проживания, сведения о здоровье, данные банковской карты и иные избыточные сведения по этому согласию не запрашиваются.';
    private const PROCESS_TEXT = 'Разрешённые действия в пределах указанной цели: сбор, запись, систематизация, накопление, хранение, уточнение, извлечение, использование, предоставление или доступ исполнителю хостинга в необходимом объёме, блокирование, удаление и уничтожение. Обработка смешанная, с применением средств автоматизации и без них. Доступ к клиентским данным со стороны исполнителя имеет только сама Полехина Ксения Евгеньевна. Указанный телефон используется для связи по моей заявке; отдельные каналы связи и их условия раскрываются в политике.';
    private const HOSTING_TEXT = 'Для размещения сайта и базы выбран SpaceWeb, ООО «СпейсВэб», ИНН 7813376370. По письменному подтверждению провайдера данные аккаунта виртуального хостинга, включая MySQL, файлы и резервные копии, размещаются в России: «Дата-Центр №1», Санкт-Петербург, Предпортовая ул., д. 5; передача за пределы РФ в рамках услуг хостинга не осуществляется. Провайдер подтвердил применимость пункта 2.1.3 Оферты, действующей с 09.11.2026, к данным посетителей в MySQL и резервных копиях; отдельное поручение в его договорной модели не предусмотрено. Копии MySQL создаются ежедневно, все такие копии без исключений хранятся не более семи дней с момента создания каждой копии, затем удаляются. После удаления записи из действующей базы она может временно оставаться в ранее созданных копиях в пределах этого срока. Подтверждение не устанавливает срок хранения резервных копий файлов сайта. Серверные журналы SpaceWeb хранятся 30 дней. По сообщению провайдера, сотрудники в стандартной модели оказания услуги не имеют доступа к содержимому сервера; автоматизированное хранение и резервное копирование при этом осуществляются. Сведения об иных привлечённых организациях и объёме их доступа: ????????????. Условия очистки копий файлов при наличии в них персональных данных и проверка повторного удаления после восстановления: ????????????.';
    private const TERM_TEXT = 'Согласие действует с момента самостоятельной отметки в форме и её отправки до достижения цели, отзыва либо истечения шести календарных месяцев после последнего содержательного общения по обращению — в зависимости от того, что наступит раньше. При отсутствии общения срок считается от даты заявки. Автоматические сообщения, выбор меню, просмотры и технические запросы этот срок не продлевают. После прекращения согласие не возобновляется автоматически. При переходе к исполнению заказа необходимая обработка может осуществляться на самостоятельном договорном или законном основании.';
    private const EVIDENCE_TEXT = 'Минимальное доказательство согласия хранится в течение его действия и три календарных года после прекращения действия или отзыва для подтверждения законности обработки и защиты прав при наличии соответствующего основания. Оно включает дату, источник, цель, принятый текст, версии документов, идентификатор записи и проверочные хеши, в том числе для сопоставления с предъявленным телефоном. Сам телефон и содержание обращения в этот журнал не копируются; контрольные значения не объявляются анонимными. Архив доказательств не продлевает срок хранения заявки или переписки и не даёт разрешения продолжать общение.';
    private const WITHDRAW_TEXT = 'Я могу отозвать согласие или обратиться по вопросам данных на Ve9a.Studio@yandex.ru, сообщив сведения, достаточные для поиска обращения, например использованный телефон и примерную дату. По умолчанию копия паспорта не требуется. Если другого основания нет, оператор прекращает обработку на основании согласия и уничтожает данные в установленный законом срок, обычно не позднее 30 дней после получения отзыва. Если остаётся основание по пунктам 2–11 части 1 статьи 6 № 152-ФЗ, необходимые данные могут сохраняться только для соответствующей цели, о чём оператор сообщает при рассмотрении требования.';
    private const LIMITS_TEXT = 'Согласие оформляется отдельно от условий заказа и не является согласием на рекламу, распространение данных или Яндекс Метрику. Аналитика включается отдельным выбором в уведомлении cookie. Оно также не является безусловным разрешением трансграничной передачи. Квалификация и условия ручного поиска по номеру и переписки в Telegram или WhatsApp, иностранные получатели, страны и необходимые уведомления: ????????????. Эти сведения будут уточнены после ответа Роскомнадзора; до этого такой сценарий не считается юридически проверенным. Общие уведомления сайта в Telegram не содержат моих контактов, текста обращения или идентификатора заявки.';
    public const CONSENT_SECTIONS = [
        ['1. Кому предоставляется согласие', self::OPERATOR_TEXT],
        ['2. Цель', self::PURPOSE_TEXT],
        ['3. Состав данных', self::DATA_TEXT],
        ['4. Действия и способы обработки', self::PROCESS_TEXT],
        ['5. Размещение и технический доступ', self::HOSTING_TEXT],
        ['6. Срок действия', self::TERM_TEXT],
        ['7. Доказательство согласия', self::EVIDENCE_TEXT],
        ['8. Отзыв и его последствия', self::WITHDRAW_TEXT],
        ['9. Пределы согласия', self::LIMITS_TEXT],
    ];
    public const CONSENT_TEXT = self::OPERATOR_TEXT . "\n\n" . self::PURPOSE_TEXT . "\n\n" . self::DATA_TEXT
        . "\n\n" . self::PROCESS_TEXT . "\n\n" . self::HOSTING_TEXT . "\n\n" . self::TERM_TEXT
        . "\n\n" . self::EVIDENCE_TEXT . "\n\n" . self::WITHDRAW_TEXT . "\n\n" . self::LIMITS_TEXT;

    public static function evidence(string $source, string $nonce, ?string $acceptedAt = null): array
    {
        $acceptedAt = $acceptedAt ?: gmdate('Y-m-d H:i:s');
        $acceptedTimestamp = strtotime($acceptedAt . ' UTC');
        if ($acceptedTimestamp === false) {
            throw new InvalidArgumentException('Invalid consent acceptance time.');
        }
        $expiresAt = RetentionPolicy::addMonths($acceptedAt, RetentionPolicy::LEAD_MONTHS);
        $retentionUntil = RetentionPolicy::addMonths($expiresAt, self::EVIDENCE_MONTHS);
        $fingerprint = implode('|', [
            self::CONSENT_VERSION,
            self::PRIVACY_POLICY_VERSION,
            self::CONSENT_TEXT,
            $source,
            $nonce,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            $acceptedAt,
        ]);

        return [
            'purpose_code' => self::PURPOSE_CODE,
            'consent_version' => self::CONSENT_VERSION,
            'privacy_policy_version' => self::PRIVACY_POLICY_VERSION,
            'consent_text' => self::CONSENT_TEXT,
            'source' => $source,
            'accepted_at' => $acceptedAt,
            'evidence_hash' => hash('sha256', $fingerprint),
            'retention_until' => $retentionUntil,
            'consent_expires_at' => $expiresAt,
        ];
    }

    public static function record(PDO $pdo, int $orderId, array $evidence): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO personal_data_consents
                (order_id, purpose_code, consent_version, privacy_policy_version, consent_text,
                 source, accepted_at, evidence_hash, retention_until, consent_expires_at, subject_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $orderId,
            $evidence['purpose_code'],
            $evidence['consent_version'],
            $evidence['privacy_policy_version'],
            $evidence['consent_text'],
            $evidence['source'],
            $evidence['accepted_at'],
            $evidence['evidence_hash'],
            $evidence['retention_until'],
            $evidence['consent_expires_at'],
            self::subjectHash($evidence['evidence_hash'], self::orderPhone($pdo, $orderId)),
        ]);
    }

    private static function orderPhone(PDO $pdo, int $id): string
    {
        $statement = $pdo->prepare('SELECT phone FROM orders WHERE id = ?');
        $statement->execute([$id]);
        return (string) $statement->fetchColumn();
    }

    public static function subjectHash(string $evidenceHash, string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        return $digits === '' ? null : hash('sha256', $evidenceHash . '|' . $digits);
    }

    // Call inside the caller's transaction, after contact/status changes and before erasure.
    // An ended or expired consent never becomes active again without a new consent.
    public static function sync(PDO $pdo, ?int $orderId = null): void
    {
        $where = $orderId === null ? '' : ' AND p.order_id = ?';
        $params = $orderId === null ? [] : [$orderId];
        $pdo->prepare("UPDATE personal_data_consents p JOIN orders o ON o.id = p.order_id
            SET p.consent_ended_at = LEAST(p.consent_expires_at,
                COALESCE(p.withdrawn_at, '9999-12-31 23:59:59'),
                COALESCE(o.personal_data_erased_at, '9999-12-31 23:59:59'),
                CASE WHEN o.status = 'closed' THEN COALESCE(o.closed_at, UTC_TIMESTAMP()) ELSE '9999-12-31 23:59:59' END,
                CASE WHEN o.status NOT IN ('new', 'discussion', 'closed') THEN COALESCE(o.completed_at, UTC_TIMESTAMP()) ELSE '9999-12-31 23:59:59' END)
            WHERE p.purpose_code = 'project_request' AND p.consent_ended_at IS NULL
              AND (p.consent_expires_at <= UTC_TIMESTAMP() OR p.withdrawn_at IS NOT NULL
                   OR o.personal_data_erased_at IS NOT NULL OR o.status NOT IN ('new', 'discussion'))$where")->execute($params);
        $pdo->prepare("UPDATE personal_data_consents p JOIN orders o ON o.id = p.order_id
            SET p.consent_expires_at = DATE_ADD(GREATEST(p.accepted_at, COALESCE(o.last_contact_at, o.created_at)), INTERVAL 6 MONTH)
            WHERE p.purpose_code = 'project_request' AND p.consent_ended_at IS NULL
              AND p.consent_expires_at > UTC_TIMESTAMP() AND p.withdrawn_at IS NULL
              AND p.consent_version <> 'project-request-v1' AND o.personal_data_erased_at IS NULL
              AND o.status IN ('new', 'discussion')$where")->execute($params);
        $pdo->prepare("UPDATE personal_data_consents p
            SET p.retention_until = DATE_ADD(COALESCE(p.consent_ended_at, p.consent_expires_at), INTERVAL 3 YEAR)
            WHERE p.purpose_code = 'project_request'$where")->execute($params);
        $pdo->prepare("UPDATE orders o JOIN personal_data_consents p ON p.order_id = o.id
            SET o.personal_data_expires_at = LEAST(o.personal_data_expires_at, DATE_ADD(p.consent_ended_at, INTERVAL 30 DAY))
            WHERE p.purpose_code = 'project_request' AND p.consent_ended_at IS NOT NULL
              AND o.status IN ('new', 'discussion', 'closed') AND o.personal_data_erased_at IS NULL$where")->execute($params);
    }
}
