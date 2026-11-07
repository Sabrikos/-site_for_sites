<?php
declare(strict_types=1);

final class DecisionTree
{
    public function __construct(private array $catalog) {}

    public function resolve(string $choice): array
    {
        $extras = [
            'automation'=>['Автоматизация задач','Сокращение ручного ввода, сбор данных и настройка повторяющихся процессов.'],
            'reports'=>['Таблицы и отчёты','Подготовка данных, расчётов и отчётов для ежедневной работы.'],
            'internal'=>['Внутренние инструменты','Небольшие панели, каталоги и личные кабинеты для задач команды.'],
            'integrations'=>['Связь между сервисами','Передача данных между сайтом, таблицами и другими системами при наличии доступа.'],
        ];
        $choices = [['id'=>'root','label'=>'Все услуги'], ['id'=>'human','label'=>'Позвать специалиста']];
        $text = 'Выберите услугу или подключите специалиста. Цены и сроки указаны по текущему каталогу.';
        $label = 'Все услуги';
        if ($choice === 'human') return ['label'=>'Позвать специалиста','text'=>'','choices'=>[]];
        if ($choice === 'root') {
            $services = [];
            foreach ($this->catalog as $row) $services[(int)$row['service_id']] = $row['service_name'];
            foreach ($services as $id=>$name) array_unshift($choices, ['id'=>'service:'.$id,'label'=>$name]);
        } elseif (preg_match('/^(service|tariff):([1-9][0-9]*)$/D', $choice, $m)) {
            $rows = array_values(array_filter($this->catalog, fn($r)=>(int)$r[$m[1]==='service'?'service_id':'id']===(int)$m[2]));
            if (!$rows) throw new InvalidArgumentException('Выберите доступный пункт меню.');
            $label = $m[1]==='service' ? $rows[0]['service_name'] : $rows[0]['tariff_name'];
            $text = $rows[0]['service_name'];
            if ($m[1]==='service') {
                $text .= "\n" . ($rows[0]['service_description'] ?? '');
                foreach ($rows as $row) array_unshift($choices, ['id'=>'tariff:'.$row['id'],'label'=>$row['tariff_name']]);
            } else {
                $r=$rows[0];
                $price=($r['slug']==='other'||$r['price']===null||$r['price']==='') ? 'по договорённости' : 'от '.number_format((int)$r['price'],0,'',' ').' ₽';
                $text .= ' — '.$r['tariff_name']."\n".$r['description']."\nСтоимость: ".$price."\nСрок: ".($r['deadline'] ?: 'по договорённости');
                array_unshift($choices,['id'=>'service:'.$r['service_id'],'label'=>'Другие тарифы услуги']);
            }
        } elseif (str_starts_with($choice, 'extra:') && isset($extras[substr($choice,6)])) {
            [$label,$description]=$extras[substr($choice,6)];
            $text=$label."\n".$description."\nСтоимость и срок: по договорённости.";
        } elseif ($choice==='terms') {
            $label='Условия работы';
            $text='Условия оплаты, договора, правок и поддержки нужно уточнить у специалиста. В этом меню они не установлены.';
        } else throw new InvalidArgumentException('Выберите доступный пункт меню.');
        if ($choice==='root') {
            foreach($extras as $id=>$extra) $choices[]=['id'=>'extra:'.$id,'label'=>$extra[0]];
            $choices[]=['id'=>'terms','label'=>'Оплата, договор и правки'];
        }
        return ['label'=>$label,'text'=>trim($text),'choices'=>$choices];
    }
}
