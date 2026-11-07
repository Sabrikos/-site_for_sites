<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../bd.php';
$name=$argv[2] ?? '';
if (!preg_match('/^vega_chat_test_[a-f0-9]{12}$/D',$name)) throw new RuntimeException('Invalid disposable database');
if (($argv[1]??'')==='create') {
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $definitions=[];
    foreach($tables as $table) $definitions[$table]=$pdo->query('SHOW CREATE TABLE `'.str_replace('`','``',$table).'`')->fetch(PDO::FETCH_NUM)[1];
    $services=$pdo->query('SELECT * FROM services')->fetchAll();
    $tariffs=$pdo->query('SELECT * FROM tariffs')->fetchAll();
    $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$name`"); $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach($definitions as $sql) $pdo->exec($sql);
    foreach(['services'=>$services,'tariffs'=>$tariffs] as $table=>$rows) foreach($rows as $row) {
        $columns=implode(',',array_map(fn($column)=>'`'.$column.'`',array_keys($row)));
        $pdo->prepare("INSERT INTO `$table` ($columns) VALUES (".implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo json_encode(['database'=>$name]);
} elseif (($argv[1]??'')==='verify') {
    $pdo->exec("USE `$name`");
    require_once __DIR__.'/../services/ChatService.php';
    $chat=new ChatService($pdo);
    $ids=$pdo->query('SELECT id FROM chat_conversations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute(['test-operator', password_hash('DisposableOperatorTest!', PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();
    foreach ($ids as $id) {
        $chat->adminAction((int)$id, $adminId, 'reply', 'specialist-test-reply-'.$id);
    }
    $id=(int)$ids[0];
    $before=(int)$pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn();
    foreach (['bot','waiting_human'] as $staleStatus) {
        try {
            $chat->beginMessage($id,'stale-message-must-not-be-stored',$staleStatus);
            throw new RuntimeException('Stale mode accepted');
        } catch (DomainException $error) {
            if ($error->getCode()!==409) throw $error;
        }
    }
    if ($before!==(int)$pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn()) throw new RuntimeException('Stale message stored');
    echo 'Independent replies created; stale-mode messages rejected without storage';
} elseif (($argv[1]??'')==='drop') {
    $pdo->exec("DROP DATABASE `$name`"); echo 'Disposable database removed';
}
