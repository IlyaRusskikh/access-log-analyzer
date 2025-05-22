<?php

namespace NSLogAnalyzer;

use DateTime;
use InvalidArgumentException;

class LogEntry
{
    public DateTime $timestamp;
    public int $statusCode;
    public float $responseTime;
    public bool $isError;
    
    
    public function __construct(string $logLine, float $maxResponseTime)
    {
        // Захотел решить проблему регуляркой? Теперь у тебя две проблемы...
        $pattern = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) (\S+) HTTP\/\d\.\d" (\d+) \d+ (\d+\.\d+)/';
        // Но это всё равно лучше, чем искать вхождения символов
        if (!preg_match($pattern, $logLine, $matches)) {
            throw new InvalidArgumentException("Invalid log format: " . $logLine);
        }

        // Теперь у нас не строка, а объект с нужными данными
        $this->timestamp = DateTime::createFromFormat('d/m/Y:H:i:s O', $matches[2]);
        $this->statusCode = (int)$matches[5];
        $this->responseTime = (float)$matches[6];
        $this->isError = $this->statusCode >= 500 || $this->responseTime > $maxResponseTime;
    }
    
}