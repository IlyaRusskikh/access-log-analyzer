<?php

namespace NSLogAnalyzer;

use DateTime;
use SplQueue;
use InvalidArgumentException;

class SlidingWindowAnalyzer
{
    private float $minAvailability;
    private float $maxResponseTime;
    private int $windowSeconds;
    private SplQueue $window;
    private array $problemIntervals = [];
    private ?array $currentProblem = null;

    public function __construct(float $minAvailability, float $maxResponseTime, int $windowSeconds = 60)
    {
        $this->minAvailability = $minAvailability;
        $this->maxResponseTime = $maxResponseTime;
        $this->windowSeconds = $windowSeconds;
        $this->window = new SplQueue();
    }

    public function processLine(string $line): void
    {
        try {
            // Из записи получаем объект
            $entry = new LogEntry($line, $this->maxResponseTime);
            // Включаем его в окно
            $this->updateWindow($entry);
            // Проверяем окно на доступность и отдаем сохраненные значения в problemIntervals, если текущее окно не подошло
            $this->checkWindowAvailability();
        } catch (InvalidArgumentException $e) {
            // Ловим ошибку, можно отдать её в логи
        }
    }

    private function updateWindow(LogEntry $entry): void
    {
        // Получаем время объекта
        $currentTime = $entry->timestamp->getTimestamp();
        
        // Удаляем записи, выпавшие из временного окна
        while (!$this->window->isEmpty()) {
            $oldest = $this->window->bottom();
            if ($currentTime - $oldest->timestamp->getTimestamp() > $this->windowSeconds) {
                $this->window->dequeue();
            } else {
                break;
            }
        }
        // Добавляем объект в окно
        $this->window->enqueue($entry);
    }

    private function checkWindowAvailability(): void
    {
        // Проверяем окно на пустоту
        if ($this->window->isEmpty()) return;

        // Находим количество объектов в окне
        $total = $this->window->count();

        // Получаем временные отметки с начала и конца окна
        $windowStart = $this->window->bottom()->timestamp;
        $windowEnd = $this->window->top()->timestamp;

        
        $errors = 0;

        // Считаем количество объектов с ошибками
        foreach ($this->window as $entry) {
            if ($entry->isError) $errors++;
        }

        // Находим процент доступности
        $availability = 100.0 * (1 - $errors / $total);


        // Сравниваем доступность окна с пороговым значением
        if ($availability < $this->minAvailability) {
            // Окно подошло, будем его добавлять в массив
            if ($this->currentProblem === null) {
                // В текущем проблемном интервале ничего нет, создаем его из окна
                $firstErrorTime = null;
                foreach ($this->window as $entry) {
                    if ($entry->isError && ($firstErrorTime === null || $entry->timestamp < $firstErrorTime)) {
                        $firstErrorTime = $entry->timestamp;
                    }
                }
                $this->currentProblem = [
                    'start' => $firstErrorTime ?? clone $windowStart,
                    'end' => clone $windowEnd,
                    'min_availability' => $availability,
                    'total_requests' => $total,
                    'total_errors' => $errors
                ];
            } else {
                // В текущем проблемном интервале уже есть значения, обновляем данные проблемного интервала
                $this->currentProblem['end'] = clone $windowEnd;
                $this->currentProblem['total_requests'] += $total;
                $this->currentProblem['total_errors'] += $errors;
            }
        } elseif ($this->currentProblem !== null) {
            // Окно не подошло, но в текущем проблемном интервале уже есть окно
            $this->problemIntervals[] = $this->currentProblem;
            $this->currentProblem = null;
        }
    }

    public function getProblemIntervals(): array
    {
        // Добавляем последний проблемный интервал, если он есть
        if ($this->currentProblem !== null) {
            $this->problemIntervals[] = $this->currentProblem;
            $this->currentProblem = null;
        }
        // Объединяем интервалы, у которых есть пересечения
        return $this->mergeIntervals($this->problemIntervals);
    }

    private function mergeIntervals(array $intervals): array
    {
        // Проверяем, что интервалов больше 1
        if (count($intervals) <= 1) {
            return $intervals;
        }

        // Сортируем интервалы по времени начала
        usort($intervals, function($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        $merged = [];
        // Закидываем первый интервал в "текущий"
        $current = $intervals[0];
        $currentRequests = $current['total_requests'] ?? 1;
        $currentErrors = $current['total_errors'] ?? 0;

        // Перебираем оставшиеся интервалы
        for ($i = 1; $i < count($intervals); $i++) {
            // Проверяем пересечение интервалов
            if ($intervals[$i]['start'] <= $current['end']) {
                // Объединяем интервалы
                $current['end'] = max($current['end'], $intervals[$i]['end']);
                $currentRequests += $intervals[$i]['total_requests'] ?? 1;
                $currentErrors += $intervals[$i]['total_errors'] ?? 0;
                
                // Пересчитываем доступность для объединенного интервала
                $current['min_availability'] = 100.0 * (1 - $currentErrors / $currentRequests);
                $current['total_requests'] = $currentRequests;
                $current['total_errors'] = $currentErrors;
            } else {
                // Фиксируем пересчитанные значения перед сохранением
                $current['min_availability'] = 100.0 * (1 - $currentErrors / $currentRequests);
                $merged[] = $current;
                
                // Начинаем новый интервал
                $current = $intervals[$i];
                $currentRequests = $current['total_requests'] ?? 1;
                $currentErrors = $current['total_errors'] ?? 0;
            }
        }

        // Добавляем последний интервал
        $current['min_availability'] = 100.0 * (1 - $currentErrors / $currentRequests);
        $merged[] = $current;

        // Возвращаем результат
        return $merged;
    }
}