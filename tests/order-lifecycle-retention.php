<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/OrderLifecycleService.php';
require_once __DIR__ . '/../services/ChatService.php';
require_once __DIR__ . '/../services/PersonalDataService.php';
require_once __DIR__ . '/../services/OrderService.php';

function checkLifecycle(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo 'PASS: ' . $label . PHP_EOL;
}

// Every write below targets a connection-local temporary table shadowing the real
// table. No real client records, Telegram calls or external AI are used.
foreach (['orders', 'chat_conversations', 'chat_messages', 'chat_assistant_state', 'chat_admin_presence',
    'personal_data_consents', 'order_items', 'chat_admin_reads', 'telegram_sessions', 'telegram_assignments', 'telegram_profiles', 'telegram_relays'] as $table) {
    $definition = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    $definition = preg_replace('/^\s*CONSTRAINT[^\n]*\n?/m', '', $definition);
    $definition = preg_replace('/,\s*\)/', "\n)", $definition);
    $pdo->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $definition));
}
foreach (['orders' => ['last_contact_at DATETIME NULL', 'completed_at DATETIME NULL', 'closed_at DATETIME NULL'],
    'personal_data_consents' => ['consent_expires_at DATETIME NULL', 'consent_ended_at DATETIME NULL', 'subject_hash CHAR(64) NULL'],
    'chat_conversations' => ['last_contact_at DATETIME NULL', 'linked_order_id BIGINT UNSIGNED NULL', 'closed_at DATETIME NULL']] as $table => $columns) {
    foreach ($columns as $column) {
        $name = strtok($column, ' ');
        $exists = $pdo->query('SHOW COLUMNS FROM ' . $table . " LIKE '$name'")->fetch();
        if (!$exists) $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column);
    }
}
checkLifecycle(RetentionPolicy::addMonths('2026-08-31 12:00:00', 6) === '2027-02-28 12:00:00', 'six calendar months clamp month end');
checkLifecycle(RetentionPolicy::addMonths('2023-11-30 12:00:00', 3) === '2024-02-29 12:00:00', 'three calendar months support leap year');
checkLifecycle(OrderLifecycleService::contactFromForm('2026-01-10T12:00') === '2026-01-10 09:00:00', 'admin contact date converts Moscow to UTC');
foreach (['2999-01-01T12:00', '2026-02-31T12:00', 'bad'] as $bad) {
    try { OrderLifecycleService::contactFromForm($bad); throw new RuntimeException('Bad date accepted'); } catch (DomainException) {}
}
checkLifecycle(true, 'invalid and future contact dates rejected');

$insert = $pdo->prepare("INSERT INTO orders (customer_name,phone,total,status,created_at,last_contact_at,personal_data_expires_at)
    VALUES ('Synthetic','+70000000000',0,'new','2025-01-01','2025-01-01','2025-07-01')");
$insert->execute(); $orderId = (int) $pdo->lastInsertId();
$lifecycle = new OrderLifecycleService($pdo);
$order = function () use ($pdo, $orderId) { $q=$pdo->prepare('SELECT * FROM orders WHERE id=?'); $q->execute([$orderId]); return $q->fetch(PDO::FETCH_ASSOC); };
$lifecycle->update($orderId, 'discussion');
checkLifecycle($order()['personal_data_expires_at'] === '2025-07-01 00:00:00', 'changing status does not imitate a contact');
$contact = gmdate('Y-m-d H:i:s', time()-86400);
$lifecycle->update($orderId, 'discussion', $contact);
checkLifecycle($order()['personal_data_expires_at'] === RetentionPolicy::addMonths($contact,6), 'manual contact updates six-month deadline');
$lifecycle->update($orderId, 'processing');
checkLifecycle($order()['personal_data_expires_at'] === null, 'active order excluded from consultation deadline');
$lifecycle->update($orderId, 'completed');
checkLifecycle($order()['completed_at'] !== null && $order()['personal_data_expires_at'] === null, 'completion date saved without consultation expiry');

$chatService = new ChatService($pdo);
$conversation = $chatService->conversation('synthetic-lifecycle-test'); $chatId=(int)$conversation['id'];
$pdo->prepare("UPDATE chat_conversations SET last_contact_at='2025-01-01', personal_data_expires_at='2025-04-01' WHERE id=?")->execute([$chatId]);
$lifecycle->linkChat($orderId, $chatId);
$chat = function () use ($pdo,$chatId) { $q=$pdo->prepare('SELECT * FROM chat_conversations WHERE id=?'); $q->execute([$chatId]); return $q->fetch(PDO::FETCH_ASSOC); };
checkLifecycle($chat()['personal_data_expires_at'] === null, 'linked chat protected with completed order');
$chatService->add($chatId,'bot','Synthetic menu');
checkLifecycle($chat()['last_contact_at'] === '2025-01-01 00:00:00', 'bot does not renew contact');
$chatService->add($chatId,'user','Synthetic menu choice');
checkLifecycle($chat()['last_contact_at'] === '2025-01-01 00:00:00', 'menu label does not renew contact');
$chatService->add($chatId,'admin','Synthetic actual reply',true);
checkLifecycle($chat()['last_contact_at'] !== '2025-01-01 00:00:00' && $chat()['personal_data_expires_at'] === null, 'operator reply updates contact and preserves order protection');

$pdo->prepare("UPDATE chat_conversations SET personal_data_expires_at='2020-01-01' WHERE id=?")->execute([$chatId]);
$result = PersonalDataService::eraseExpired($pdo);
checkLifecycle($result['orders'] === 0 && $result['conversations'] === 0 && $order()['personal_data_erased_at'] === null, 'cleanup rechecks protected status even with stale expiry');
$lifecycle->unlinkChat($orderId,$chatId);
checkLifecycle($chat()['linked_order_id'] === null && $chat()['personal_data_expires_at'] !== null, 'unlink restores deadline from real contact');
$lifecycle->update($orderId,'discussion');
$closedAt = gmdate('Y-m-d H:i:s',time()-10*86400);
$closedDeadline = gmdate('Y-m-d H:i:s',time()+20*86400);
$pdo->prepare("UPDATE chat_conversations SET status='closed', closed_at=?, personal_data_expires_at=? WHERE id=?")->execute([$closedAt,$closedDeadline,$chatId]);
$lifecycle->linkChat($orderId,$chatId);
$lifecycle->update($orderId,'discussion');
checkLifecycle($chat()['personal_data_expires_at'] <= $closedDeadline, 'link and order save do not extend a closed consultation');
$lifecycle->update($orderId,'processing');
$lifecycle->unlinkChat($orderId,$chatId);
checkLifecycle($chat()['personal_data_expires_at'] <= $closedDeadline, 'detaching a protected closed chat restores original closure limit');
$lifecycle->update($orderId,'discussion');
$lifecycle->update($orderId,'closed');
checkLifecycle($order()['closed_at'] !== null && $order()['personal_data_expires_at'] <= gmdate('Y-m-d H:i:s',time()+30*86400+2), 'closed inquiry gets at most thirty days');
$lifecycle->update($orderId,'discussion','2020-01-01 00:00:00');
$lifecycle->update($orderId,'closed');
checkLifecycle($order()['personal_data_expires_at'] === '2020-07-01 00:00:00', 'closing never extends an earlier expiry');
$pdo->prepare("UPDATE chat_conversations SET personal_data_expires_at='2020-01-01' WHERE id=?")->execute([$chatId]);
$result = PersonalDataService::eraseExpired($pdo);
checkLifecycle($result['orders'] === 1 && $result['conversations'] === 1, 'expired consultation records erased only in isolated temporary tables');
checkLifecycle($order()['phone'] === '' && $order()['personal_data_erased_at'] !== null, 'expired inquiry contacts removed');
$insert->execute(); $withdrawOrderId=(int)$pdo->lastInsertId();
$withdrawChatId=(int)$chatService->conversation('synthetic-withdrawal')['id'];
$lifecycle->update($withdrawOrderId,'processing');
$lifecycle->linkChat($withdrawOrderId,$withdrawChatId);
$chatService->add($withdrawChatId,'user','Synthetic private content',true);
PersonalDataService::withdrawAndErase($pdo,$withdrawOrderId,'synthetic_test');
$q=$pdo->prepare('SELECT COUNT(*) FROM chat_messages WHERE conversation_id=?');$q->execute([$withdrawChatId]);
checkLifecycle((int)$q->fetchColumn()===0, 'explicit erasure includes linked private messages and their conversation');
$menuChatId=(int)$chatService->conversation('synthetic-menu-retention')['id'];
$pdo->prepare("UPDATE chat_conversations SET last_contact_at='2025-01-01', personal_data_expires_at='2025-04-01' WHERE id=?")->execute([$menuChatId]);
$pending=$chatService->beginMessage($menuChatId,'Menu selection','bot');
$chatService->finishMessage($menuChatId,$pending['key'],['text'=>'Local menu reply']);
$q=$pdo->prepare('SELECT last_contact_at FROM chat_conversations WHERE id=?');$q->execute([$menuChatId]);
checkLifecycle($q->fetchColumn()==='2025-01-01 00:00:00', 'real menu request and generated menu response do not renew retention');
$pdo->prepare("UPDATE chat_conversations SET status='waiting_human' WHERE id=?")->execute([$menuChatId]);
$chatService->beginMessage($menuChatId,'Actual consultation text','waiting_human');
$q->execute([$menuChatId]);
checkLifecycle($q->fetchColumn()!=='2025-01-01 00:00:00', 'free text after specialist request renews consultation contact');
$publicService=(int)$pdo->query('SELECT id FROM services WHERE active=1 ORDER BY id LIMIT 1')->fetchColumn();
$evidence=ConsentService::evidence('synthetic_test',str_repeat('a',64));
$created=(new OrderService($pdo))->createLead(['name'=>'Synthetic','phone'=>'+70000000000','comment'=>'Test only'], $publicService, str_repeat('b',64), $evidence);
$q=$pdo->prepare('SELECT personal_data_expires_at FROM orders WHERE id=?');$q->execute([$created['id']]);
checkLifecycle($q->fetchColumn()===RetentionPolicy::addMonths($evidence['accepted_at'],6), 'new lead uses six months independently of consent-evidence retention');
$evidence['consent_version']='project-request-v1';$evidence['consent_expires_at']=gmdate('Y-m-d H:i:s',time()+60*86400);
$insert->execute();$legacyId=(int)$pdo->lastInsertId();ConsentService::record($pdo,$legacyId,$evidence);
$lifecycle->update($legacyId,'discussion',gmdate('Y-m-d H:i:s'));
$q->execute([$legacyId]);
checkLifecycle($q->fetchColumn()===$evidence['consent_expires_at'], 'old one-year consent cap is not extended by new contact');

$readConsent = function(int $id) use ($pdo): array { $q=$pdo->prepare('SELECT * FROM personal_data_consents WHERE order_id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC) ?: []; };
$newId = $created['id'];
$proof = $readConsent($newId);
checkLifecycle($proof['retention_until'] === RetentionPolicy::addMonths($proof['consent_expires_at'],36), 'proof initially kept through consent expiry plus three years');
checkLifecycle($proof['subject_hash'] === ConsentService::subjectHash($proof['evidence_hash'], '+7 (000) 000-00-00'), 'subject matching uses normalized phone without storing it in proof');
checkLifecycle(RetentionPolicy::addMonths('2024-02-29 12:00:00',36)==='2027-02-28 12:00:00', 'three-year retention clamps leap day');
$pdo->prepare('UPDATE personal_data_consents SET consent_expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE order_id=?')->execute([$newId]);
$lifecycle->update($newId,'discussion',gmdate('Y-m-d H:i:s'));
$proof=$readConsent($newId);
checkLifecycle($proof['consent_expires_at']===RetentionPolicy::addMonths($proof['accepted_at'],6), 'meaningful contact refreshes active consent independently of proof retention');
$lifecycle->update($newId,'processing');
$proof=$readConsent($newId);
checkLifecycle($proof['consent_ended_at']!==null && $proof['retention_until']===RetentionPolicy::addMonths($proof['consent_ended_at'],36), 'moving to contract work ends request consent and starts proof archive');
$lifecycle->update($newId,'discussion',gmdate('Y-m-d H:i:s'));
checkLifecycle($readConsent($newId)['consent_ended_at']===$proof['consent_ended_at'], 'reopening does not reactivate ended consent');
PersonalDataService::withdrawAndErase($pdo,$newId,'synthetic_test');
$withdrawn=$readConsent($newId);
PersonalDataService::withdrawAndErase($pdo,$newId,'synthetic_test');
checkLifecycle($readConsent($newId)['retention_until']===$withdrawn['retention_until'] && $readConsent($newId)['withdrawn_at']===$withdrawn['withdrawn_at'], 'repeated withdrawal does not extend proof retention');
checkLifecycle($readConsent($newId)['subject_hash']===$proof['subject_hash'], 'erasure preserves only the subject matching proof');

$insert->execute(); $pastId=(int)$pdo->lastInsertId();
$past=ConsentService::evidence('synthetic_test','old','2020-01-01 00:00:00');
ConsentService::record($pdo,$pastId,$past);
PersonalDataService::eraseExpired($pdo);
checkLifecycle($readConsent($pastId)===[], 'expired consent proof is deleted independently of orders');
checkLifecycle($readConsent($newId)!==[], 'unexpired proof survives consultation erasure');

$insert->execute(); $migrationId=(int)$pdo->lastInsertId();
$historical=ConsentService::evidence('synthetic_test','migration','2025-01-01 00:00:00');
$historical['consent_version']='project-request-v1';
ConsentService::record($pdo,$migrationId,$historical);
$pdo->prepare("UPDATE personal_data_consents SET consent_expires_at=NULL, consent_ended_at=NULL, subject_hash=NULL,
    retention_until='2026-01-01 00:00:00', withdrawn_at='2025-02-01 00:00:00' WHERE order_id=?")->execute([$migrationId]);
$migrationSql=file_get_contents(__DIR__.'/../migrations/010_consent_evidence_retention.sql');
foreach(explode(';',$migrationSql) as $statement) {
    $statement=trim(preg_replace('/^--[^\r\n]*\R?/m','',$statement));
    if(str_starts_with($statement,'UPDATE ')) $pdo->exec($statement);
}
$migrated=$readConsent($migrationId);
checkLifecycle($migrated['consent_expires_at']==='2026-01-01 00:00:00' && $migrated['retention_until']==='2028-02-01 00:00:00', 'migration preserves legacy permission cap and archives from historical withdrawal');
checkLifecycle($migrated['evidence_hash']===$historical['evidence_hash'] && $migrated['consent_text']===$historical['consent_text'], 'migration never rewrites accepted evidence or text');
checkLifecycle($migrated['subject_hash']===ConsentService::subjectHash($historical['evidence_hash'],'+70000000000'), 'migration matching token equals runtime token');

$insert->execute(); $activeWithdrawId=(int)$pdo->lastInsertId();
ConsentService::record($pdo,$activeWithdrawId,ConsentService::evidence('synthetic_test','withdraw-active'));
PersonalDataService::withdrawAndErase($pdo,$activeWithdrawId,'synthetic_test');
$activeWithdraw=$readConsent($activeWithdrawId);
checkLifecycle($activeWithdraw['consent_ended_at']===$activeWithdraw['withdrawn_at'] && $activeWithdraw['retention_until']===RetentionPolicy::addMonths($activeWithdraw['withdrawn_at'],36), 'active withdrawal starts three-year proof period at actual withdrawal');
$pdo->prepare('UPDATE personal_data_consents SET consent_ended_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 YEAR) WHERE order_id=?')->execute([$activeWithdrawId]);
PersonalDataService::eraseExpired($pdo);
checkLifecycle($readConsent($activeWithdrawId)===[], 'proof is removed at exact three-year expiry boundary');
echo "All writes were isolated to temporary tables; real records were not changed.\n";
