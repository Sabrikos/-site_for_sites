<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../bd.php';
$db = $argv[1] ?? '';
$kind = $argv[2] ?? '';
$value = $argv[3] ?? '';
if (!preg_match('/^vega_chat_test_[a-f0-9]{12}$/D', $db)) throw new RuntimeException('Disposable database required');
$pdo->exec("USE `$db`");
if ($kind === 'poll' && preg_match('/^[1-9][0-9]*$/D', $value)) {
    $seconds=60; $scope='chat_poll_conversation:'.$value; $hits=179; $table='assistant_limits';
} elseif ($kind === 'control') {
    $seconds=60; $scope='chat_control_ip:'.hash('sha256', '127.0.0.1'); $hits=179; $table='assistant_limits';
} elseif ($kind === 'login') {
    $seconds=300; $scope='admin_login_ip:127.0.0.1'; $hits=29; $table='app_rate_limits';
} elseif ($kind === 'order') {
    $seconds=600; $scope='order:127.0.0.1'; $hits=0; $table='app_rate_limits';
} else throw new RuntimeException('Invalid fixture');
$window=intdiv(time(),$seconds);
$bucket=hash('sha256',$scope.':'.$window);
$statement=$pdo->prepare("INSERT INTO $table (bucket,hits,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE hits=VALUES(hits),expires_at=VALUES(expires_at)");
$statement->execute([$bucket,$hits,date('Y-m-d H:i:s',($window+1)*$seconds)]);
echo 'ok';
