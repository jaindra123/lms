<?php
namespace local_iiidem_coursecalendar;

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal iCalendar (VEVENT) parser for public Google Calendar feeds.
 */
class ical_parser {

    /**
     * Parse VEVENT blocks from raw iCal text.
     *
     * @param string $ical Raw .ics content.
     * @param int $limit Maximum events to return.
     * @param int|null $fromtime Only events ending on/after this timestamp.
     * @return array<int, array{summary:string,start:int,end:int,allday:bool}>
     */
    public static function parse_events(string $ical, int $limit = 15, ?int $fromtime = null): array {
        $fromtime = $fromtime ?? time();
        $events = [];

        $blocks = preg_split('/BEGIN:VEVENT/i', $ical);
        if (!$blocks) {
            return [];
        }

        array_shift($blocks);
        foreach ($blocks as $block) {
            if (!preg_match('/END:VEVENT/i', $block)) {
                continue;
            }

            $summary = self::extract_property($block, 'SUMMARY') ?? '';
            $uid = self::extract_property($block, 'UID') ?? '';
            $start = self::extract_datetime($block, 'DTSTART');
            $end = self::extract_datetime($block, 'DTEND', $start);

            if ($start === null) {
                continue;
            }

            $endtime = $end ?? ($start + \DAYSECS);
            if ($endtime < $fromtime) {
                continue;
            }

            $events[] = [
                'uid' => $uid,
                'summary' => self::unescape_text($summary),
                'start' => $start,
                'end' => $endtime,
                'allday' => self::is_allday($block, 'DTSTART'),
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return $a['start'] <=> $b['start'];
        });

        return array_slice($events, 0, $limit);
    }

    /**
     * Build a hash of event UIDs and start times for change detection.
     *
     * @param string $ical Raw .ics content.
     * @return string SHA-256 hex digest.
     */
    public static function events_hash(string $ical): string {
        $blocks = preg_split('/BEGIN:VEVENT/i', $ical);
        if (!$blocks) {
            return sha1('');
        }

        $parts = [];
        array_shift($blocks);
        foreach ($blocks as $block) {
            $uid = self::extract_property($block, 'UID') ?? '';
            $start = self::extract_datetime($block, 'DTSTART');
            if ($uid !== '' && $start !== null) {
                $parts[] = $uid . ':' . $start;
            }
        }

        sort($parts);
        return hash('sha256', implode('|', $parts));
    }

    private static function extract_property(string $block, string $name): ?string {
        if (preg_match('/^' . preg_quote($name, '/') . '(?:;[^:]*)?:(.*)$/mi', $block, $m)) {
            return trim(self::unwrap_lines($m[1]));
        }
        return null;
    }

    private static function is_allday(string $block, string $name): bool {
        if (preg_match('/^' . preg_quote($name, '/') . ';VALUE=DATE:/mi', $block)) {
            return true;
        }
        $value = self::extract_property($block, $name);
        if ($value === null) {
            return false;
        }
        return (bool) preg_match('/^\d{8}$/', $value);
    }

    private static function extract_datetime(string $block, string $name, ?int $fallbackstart = null): ?int {
        if (!preg_match('/^' . preg_quote($name, '/') . '(?:;[^:]*)?:(.*)$/mi', $block, $m)) {
            return null;
        }

        $raw = trim(self::unwrap_lines($m[1]));
        $allday = self::is_allday($block, $name);

        if ($allday) {
            $date = \DateTime::createFromFormat('Ymd', substr($raw, 0, 8), \core_date::get_server_timezone_object());
            return $date ? $date->getTimestamp() : null;
        }

        if (preg_match('/^\d{8}T\d{6}Z$/', $raw)) {
            $dt = \DateTime::createFromFormat('Ymd\THis\Z', $raw, new \DateTimeZone('UTC'));
            return $dt ? $dt->getTimestamp() : null;
        }

        if (preg_match('/^\d{8}T\d{6}$/', $raw)) {
            $dt = \DateTime::createFromFormat('Ymd\THis', $raw, \core_date::get_server_timezone_object());
            return $dt ? $dt->getTimestamp() : null;
        }

        if ($fallbackstart !== null && preg_match('/^P/', $raw)) {
            // DTEND as duration — not common in Google feeds; skip.
            return $fallbackstart;
        }

        return null;
    }

    private static function unwrap_lines(string $value): string {
        return preg_replace("/\r?\n[ \t]/", '', $value) ?? $value;
    }

    private static function unescape_text(string $text): string {
        return str_replace(['\\n', '\\,', '\\;'], ["\n", ',', ';'], $text);
    }
}
