<?php

namespace NSLogAnalyzer\Tests;

use NSLogAnalyzer\LogEntry;
use NSLogAnalyzer\SlidingWindowAnalyzer;
use PHPUnit\Framework\TestCase;

class LogAnalyzerTest extends TestCase
{
    private function createTestLogEntry(
        string $time, 
        int $status, 
        float $responseTime
    ): string {
        return "192.168.1.26 - - [{$time} +1000] \"POST /api/data HTTP/1.1\" {$status} 2 {$responseTime} \"-\" \"@list-item-updater\" prio:0";
    }

    // проверка правильности парсинга записи из лога
    public function testLogEntryParsing()
    {
        $line = "192.168.1.26 - - [21/05/2024:19:36:29 +1000] \"POST /api/data HTTP/1.1\" 200 2 26.560833 \"-\" \"@list-item-updater\" prio:0";
        $entry = new LogEntry($line, 50);
        
        $this->assertEquals('21/05/2024:19:36:29', $entry->timestamp->format('d/m/Y:H:i:s'));
        $this->assertEquals(200, $entry->statusCode);
        $this->assertEquals(26.560833, $entry->responseTime);
        $this->assertFalse($entry->isError);
    }

    // Проверка на обнаружение отказов
    public function testErrorDetection()
    {
        $line = "192.168.1.26 - - [21/05/2024:19:36:29 +1000] \"POST /api/data HTTP/1.1\" 200 2 56.560833 \"-\" \"@list-item-updater\" prio:0";
        $entry = new LogEntry($line, 50);
        $this->assertTrue($entry->isError);
        
        $line = "192.168.1.26 - - [21/05/2024:19:36:29 +1000] \"POST /api/data HTTP/1.1\" 500 2 36.560833 \"-\" \"@list-item-updater\" prio:0";
        $entry = new LogEntry($line, 50);
        $this->assertTrue($entry->isError);
        
        $line = "192.168.1.26 - - [21/05/2024:19:36:29 +1000] \"POST /api/data HTTP/1.1\" 500 2 56.560833 \"-\" \"@list-item-updater\" prio:0";
        $entry = new LogEntry($line, 50);
        $this->assertTrue($entry->isError);
    }

    // Проверка выявления процента отказов
    public function testSlidingWindowAnalysis()
    {
        $analyzer = new SlidingWindowAnalyzer(90.0, 50);
        
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:00', 200, 26.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:33', 200, 58.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:38', 200, 28.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:49', 200, 60.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:53', 500, 26.560833));
        
        $intervals = $analyzer->getProblemIntervals();
        
        $this->assertCount(1, $intervals);
        $this->assertEquals('19:36:33', $intervals[0]['start']->format('H:i:s'));
        $this->assertEquals('19:36:53', $intervals[0]['end']->format('H:i:s'));
        $this->assertEquals(50, $intervals[0]['min_availability']);
    }

    // Проверка смещения окна при превышении интервала в 1 минуту
    public function testWindowSliding()
    {
        $analyzer = new SlidingWindowAnalyzer(90.0, 50);
        
        // Заполняем окно (60 секунд)
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:00', 200, 26.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:33', 200, 58.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:38', 200, 28.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:49', 200, 60.560833));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:36:53', 500, 26.560833));
        
        // Добавляем запись, которая должна вытолкнуть первую
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:37:05', 500, 26.560833)); // Ошибка
        
        $intervals = $analyzer->getProblemIntervals();
        $this->assertCount(1, $intervals);
        $this->assertEquals('19:36:33', $intervals[0]['start']->format('H:i:s'));
    }

    // Проверка отсутствия интервала при отсутствии ошибок
    public function testNoProblemIntervals()
    {
        $analyzer = new SlidingWindowAnalyzer(90.0, 50);
        
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:37:05', 200, 30.0));
        $analyzer->processLine($this->createTestLogEntry('21/05/2024:19:37:55', 200, 40.0));
        
        $this->assertEmpty($analyzer->getProblemIntervals());
    }
}