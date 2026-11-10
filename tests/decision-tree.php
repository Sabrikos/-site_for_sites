<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../services/DecisionTree.php';
require_once __DIR__.'/../services/SupportRelay.php';
require_once __DIR__.'/../services/ChatService.php';
function expectTree(bool $ok,string $name): void { if(!$ok) throw new RuntimeException($name); echo "PASS: $name\n"; }
$catalog=[['id'=>2,'service_id'=>1,'slug'=>'landing','service_name'=>'Лендинг','service_description'=>'Описание услуги','tariff_name'=>'Бизнес','description'=>'Блоки и формы','price'=>22000,'deadline'=>'5–7 дней'],
 ['id'=>3,'service_id'=>2,'slug'=>'other','service_name'=>'Другое','tariff_name'=>'Индивидуальный проект','description'=>'Индивидуальные задачи','price'=>10000,'deadline'=>'по договорённости']];
$tree=new DecisionTree($catalog);
$expandedCatalog = $catalog;
$expandedCatalog[] = array_replace($catalog[0], ['id' => 9, 'price' => 45000, 'tariff_name' => 'Премиум']);
$baseTree = new DecisionTree($expandedCatalog);
expectTree(in_array('cart:2', array_column($baseTree->resolve('service:1')['choices'], 'id'), true), 'base service matches the cheapest tariff on the pricing page');
try { $baseTree->resolve('tariff:9'); throw new LogicException('hidden tariff accepted'); } catch (InvalidArgumentException $e) {}
expectTree(in_array('service:1',array_column($tree->resolve('root')['choices'],'id'),true),'active service in menu');
expectTree(in_array('cart:2',array_column($tree->resolve('service:1')['choices'],'id'),true),'single-tariff service opens its final selection directly');
foreach ($catalog as $row) {
    $reply = $tree->resolve('tariff:' . $row['id']);
    $cartChoice = array_values(array_filter($reply['choices'], fn($choice) => $choice['id'] === 'cart:' . $row['id']));
    expectTree(count($cartChoice) === 1 && $cartChoice[0]['cart_item'] === [
        'tariff_id' => (string) $row['id'], 'service' => $row['service_name'],
        'tariff' => $row['tariff_name'], 'price' => (int) $row['price'],
    ], 'cart metadata comes from the selected catalog row ' . $row['id']);
    expectTree(in_array('human', array_column($reply['choices'], 'id'), true), 'specialist remains available ' . $row['id']);
}
expectTree(str_contains($tree->resolve('tariff:2')['text'],'22 000 ₽'),'price from catalog');
$catalog[0]['price']=23000;
expectTree(str_contains((new DecisionTree($catalog))->resolve('tariff:2')['text'],'23 000 ₽'),'updated price reflected');
expectTree(!str_contains($tree->resolve('tariff:3')['text'],'10 000'),'individual project price by agreement');
foreach(['tariff:999','service:0','service:1 OR 1=1','Мой телефон +79991234567',''] as $bad) {
 try {$tree->resolve($bad); throw new LogicException('invalid choice accepted');} catch(InvalidArgumentException $e) {}
}
expectTree(ChatService::needsHuman($tree->resolve('human')['label'],false),'human choice triggers handoff');
expectTree(!ChatService::needsHuman('Нет, не подключай специалиста',true),'declined handoff stays local');
putenv('TELEGRAM_SUPPORT_CHAT_ID=-1000000000000');
$before=websiteHumanNotification();
$_POST=['message'=>'Иван Иванов, +79991234567, user@example.com','conversation_id'=>123];
expectTree($before===websiteHumanNotification(),'notification independent of visitor data');
expectTree(array_keys($before)===['chat_id','text','reply_markup'] && !str_contains($before['text'],'123'),'notification has no visitor ID and uses a fixed generic admin button');
$fakePdo=new class extends PDO { public function __construct(){} };
supportNotify($fakePdo,123,'website',null,'Иван','username','private data');
expectTree(true,'legacy website relay neither queries database nor sends Telegram');
echo "No database, LLM or Telegram requests were made.\n";
