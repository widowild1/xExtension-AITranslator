<?php
class AITranslator_ActivityLogger {

    public static function getLogFile(): string {
        return __DIR__ . '/data/activity.log';
    }

    public static function log(string $badge, string $title = '', string $details = '', string $level = 'INFO'): void {
        $file = self::getLogFile();
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $time = date('Y-m-d H:i:s');
        $shortTitle = !empty($title) ? '"' . mb_strimwidth(strip_tags($title), 0, 45, '...') . '" - ' : '';
        $line = "[{$time}] [{$level}] [{$badge}] {$shortTitle}{$details}\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        // Auto-rotation probabiliste : maintien des 500 dernières lignes si le fichier dépasse 100 Ko
        if (mt_rand(1, 50) === 1 && @filesize($file) > 100000) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines) && count($lines) > 500) {
                $trimmed = array_slice($lines, -500);
                @file_put_contents($file, implode("\n", $trimmed) . "\n", LOCK_EX);
            }
        }
    }

    public static function getRecentLogs(int $maxLines = 150): array {
        $file = self::getLogFile();
        if (!file_exists($file)) return [];
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) return [];
        return array_reverse(array_slice($lines, -$maxLines));
    }

    public static function clearLogs(): bool {
        $file = self::getLogFile();
        return @file_put_contents($file, '') !== false;
    }
}
