
<?php

ini_set('memory_limit', '512M');
require __DIR__ . '/vendor/autoload.php';

use NSLogAnalyzer\SlidingWindowAnalyzer;

// Проверяем правильность команды
if ($argc < 4 || $argv[1] !== '-u' || $argv[3] !== '-t') {
    echo "Usage: cat access.log | php analyze.php -u 88 -t 47\n";
    exit(1);
}

// Забираем процент доступности и время обработки
$minAvailability = (float)$argv[2];
$maxResponseTime = (float)$argv[4];

// Создаем экземпляр класса, в который мы будем кормить наши данные
$analyzer = new SlidingWindowAnalyzer($minAvailability, $maxResponseTime);

// Пока есть, чем кормить - кормим
while ($line = fgets(STDIN)) {
    $line = trim($line);
    if (!empty($line)) {
        $analyzer->processLine($line);
    }
}

// Получаем результат
foreach ($analyzer->getProblemIntervals() as $interval) {
    // Красиво выводим (доступность округляем до десятой)
    printf("%s %s %.1f\n",
        $interval['start']->format('H:i:s'),
        $interval['end']->format('H:i:s'),
        $interval['min_availability']
    );
}