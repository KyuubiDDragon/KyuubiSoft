<?php

declare(strict_types=1);

namespace App\Core\Services;

/**
 * Simple iCal/ICS Parser Service
 * Parses iCal format and extracts calendar events
 */
class ICalService
{
    /**
     * Fetch and parse iCal from URL
     */
    public function fetchAndParse(string $url, ?string $username = null, ?string $password = null): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'KyuubiSoft Calendar Sync/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/calendar, application/calendar+json, */*',
            ],
        ]);

        // Add basic auth if credentials provided
        if ($username && $password) {
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || !empty($error)) {
            throw new \RuntimeException('Failed to fetch calendar: ' . ($error ?: 'Unknown error'));
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("Failed to fetch calendar: HTTP $httpCode");
        }

        if (empty($content)) {
            throw new \RuntimeException('Failed to fetch calendar: Empty response');
        }

        return $this->parse($content);
    }

    /**
     * Parse iCal content string
     */
    public function parse(string $content): array
    {
        $events = [];
        $lines = $this->unfoldLines($content);

        $currentEvent = null;
        $inEvent = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $currentEvent = [
                    'uid' => '',
                    'title' => '',
                    'description' => '',
                    'location' => '',
                    'start_date' => null,
                    'end_date' => null,
                    'all_day' => false,
                    'recurrence_rule' => null,
                ];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($currentEvent && $currentEvent['uid'] && $currentEvent['start_date']) {
                    $events[] = $currentEvent;
                }
                $inEvent = false;
                $currentEvent = null;
                continue;
            }

            if (!$inEvent || !$currentEvent) {
                continue;
            }

            // Parse property
            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $property = substr($line, 0, $colonPos);
            $value = substr($line, $colonPos + 1);

            // Handle properties with parameters (e.g., DTSTART;VALUE=DATE:20231201)
            $propertyName = $property;
            $propertyParams = [];
            if (strpos($property, ';') !== false) {
                $parts = explode(';', $property);
                $propertyName = $parts[0];
                for ($i = 1; $i < count($parts); $i++) {
                    $paramParts = explode('=', $parts[$i], 2);
                    if (count($paramParts) === 2) {
                        $propertyParams[$paramParts[0]] = $paramParts[1];
                    }
                }
            }

            switch ($propertyName) {
                case 'UID':
                    $currentEvent['uid'] = $value;
                    break;

                case 'SUMMARY':
                    $currentEvent['title'] = $this->unescapeValue($value);
                    break;

                case 'DESCRIPTION':
                    $currentEvent['description'] = $this->unescapeValue($value);
                    break;

                case 'LOCATION':
                    $currentEvent['location'] = $this->unescapeValue($value);
                    break;

                case 'DTSTART':
                    $isAllDay = isset($propertyParams['VALUE']) && $propertyParams['VALUE'] === 'DATE';
                    $currentEvent['start_date'] = $this->parseDateTime($value, $isAllDay);
                    $currentEvent['all_day'] = $isAllDay || strlen($value) === 8;
                    break;

                case 'DTEND':
                    $isAllDay = isset($propertyParams['VALUE']) && $propertyParams['VALUE'] === 'DATE';
                    $currentEvent['end_date'] = $this->parseDateTime($value, $isAllDay);
                    break;

                case 'RRULE':
                    $currentEvent['recurrence_rule'] = $value;
                    break;
            }
        }

        return $events;
    }

    /**
     * Unfold iCal lines (lines starting with space or tab are continuations)
     */
    private function unfoldLines(string $content): array
    {
        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        // Unfold continuation lines
        $content = preg_replace("/\n[ \t]/", '', $content);

        return explode("\n", $content);
    }

    /**
     * Parse iCal date/time format
     */
    private function parseDateTime(string $value, bool $isDateOnly = false): ?string
    {
        // Remove timezone suffix if present
        $value = preg_replace('/Z$/', '', $value);

        // Date only: YYYYMMDD
        if ($isDateOnly || strlen($value) === 8) {
            $date = \DateTime::createFromFormat('Ymd', $value);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        // Date and time: YYYYMMDDTHHMMSS
        if (strlen($value) >= 15) {
            $date = \DateTime::createFromFormat('Ymd\THis', substr($value, 0, 15));
            if ($date) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        // Try other formats
        $formats = ['Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * Unescape iCal values
     */
    private function unescapeValue(string $value): string
    {
        $value = str_replace('\\n', "\n", $value);
        $value = str_replace('\\N', "\n", $value);
        $value = str_replace('\\,', ',', $value);
        $value = str_replace('\\;', ';', $value);
        $value = str_replace('\\\\', '\\', $value);
        return $value;
    }
}
