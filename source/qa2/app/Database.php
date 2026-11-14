<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $config): PDO
    {
        if (self::$pdo) return self::$pdo;
        $db = $config['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?? 3306, $db['name']);
        self::$pdo = new PDO($dsn, $db['user'], $db['pass'], self::pdoOptions());
        self::synchronizeSessionTimezone(self::$pdo, (string)($config['timezone'] ?? 'Asia/Tehran'));
        return self::$pdo;
    }


    public static function pdoOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        return $options;
    }

    public static function mysqlTimezoneOffset(string $timezone, ?DateTimeImmutable $at = null): string
    {
        $tz = new DateTimeZone($timezone !== '' ? $timezone : 'Asia/Tehran');
        $at ??= new DateTimeImmutable('now', $tz);
        if ($at->getTimezone()->getName() !== $tz->getName()) $at = $at->setTimezone($tz);
        $seconds = $tz->getOffset($at);
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);
        return sprintf('%s%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    public static function synchronizeSessionTimezone(PDO $pdo, string $timezone): void
    {
        $offset = self::mysqlTimezoneOffset($timezone);
        $statement = $pdo->prepare('SET time_zone = ?');
        $statement->execute([$offset]);
        $statement->closeCursor();
    }
}
