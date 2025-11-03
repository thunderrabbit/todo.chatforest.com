<?php

namespace Todo;

/**
 * RecurringCalculator - calculates next occurrence dates for recurring todos
 *
 * Handles #d (daily), #w:mon:tue:fri (weekly), and #m:1,11,21 (monthly) patterns
 */
class RecurringCalculator
{
    /**
     * Calculate the next occurrence date for a recurring todo
     *
     * @param string $recurringMarker The marker (e.g., '#d', '#w:mon:tue:fri', '#m:1,11,21')
     * @param string $completionDate Completion date in format "HH:MM:SS DD-mon-YYYY"
     * @param string $createDate Original create date in format "HH:MM:SS DD-mon-YYYY" (for time preservation)
     * @param string $timezone Timezone string (e.g., "America/New_York")
     * @return string Next occurrence date in format "HH:MM:SS DD-mon-YYYY"
     * @throws \Exception If marker is invalid or calculation fails
     */
    public function calculateNextOccurrence(
        string $recurringMarker,
        string $completionDate,
        string $createDate,
        string $timezone = 'UTC'
    ): string {
        if (empty($recurringMarker)) {
            throw new \Exception("Empty recurring marker");
        }

        // Parse completion date to get reference point
        $completionDateTime = $this->parseDate($completionDate, $timezone);
        if ($completionDateTime === null) {
            throw new \Exception("Invalid completion date: $completionDate");
        }

        // Extract time from original createDate
        $timeComponent = $this->extractTimeComponent($createDate);

        // Calculate based on marker type
        if ($recurringMarker === '#d') {
            $nextDate = $this->nextDaily($completionDateTime, $timeComponent);
        } elseif (strpos($recurringMarker, '#w:') === 0) {
            $days = $this->parseWeeklyDays($recurringMarker);
            $nextDate = $this->nextWeekly($completionDateTime, $days, $timeComponent);
        } elseif (strpos($recurringMarker, '#m:') === 0) {
            $dayNumbers = $this->parseMonthlyDays($recurringMarker);
            $nextDate = $this->nextMonthly($completionDateTime, $dayNumbers, $timeComponent);
        } else {
            throw new \Exception("Unknown recurring marker: $recurringMarker");
        }

        return $nextDate;
    }

    /**
     * Calculate next daily occurrence (completion date + 1 day)
     *
     * @param \DateTime $completionDateTime Completion date/time
     * @param string $timeComponent Time in HH:MM:SS format
     * @return string Next occurrence in "HH:MM:SS DD-mon-YYYY" format
     */
    private function nextDaily(\DateTime $completionDateTime, string $timeComponent): string
    {
        $next = clone $completionDateTime;
        $next->modify('+1 day');
        return $this->formatDateWithTime($next, $timeComponent);
    }

    /**
     * Calculate next weekly occurrence (next matching weekday)
     *
     * @param \DateTime $completionDateTime Completion date/time
     * @param array $targetDays Array of day numbers (0=Sunday, 6=Saturday)
     * @param string $timeComponent Time in HH:MM:SS format
     * @return string Next occurrence in "HH:MM:SS DD-mon-YYYY" format
     */
    private function nextWeekly(\DateTime $completionDateTime, array $targetDays, string $timeComponent): string
    {
        $next = clone $completionDateTime;
        $currentDayOfWeek = (int)$next->format('w'); // 0=Sunday, 6=Saturday

        // Try next 7 days to find a match
        for ($i = 1; $i <= 7; $i++) {
            $next->modify('+1 day');
            $dayOfWeek = (int)$next->format('w');
            if (in_array($dayOfWeek, $targetDays)) {
                return $this->formatDateWithTime($next, $timeComponent);
            }
        }

        // Should never reach here, but fallback to +7 days
        $next = clone $completionDateTime;
        $next->modify('+7 days');
        return $this->formatDateWithTime($next, $timeComponent);
    }

    /**
     * Calculate next monthly occurrence (next matching day of month)
     *
     * @param \DateTime $completionDateTime Completion date/time
     * @param array $targetDays Array of day numbers (1-31)
     * @param string $timeComponent Time in HH:MM:SS format
     * @return string Next occurrence in "HH:MM:SS DD-mon-YYYY" format
     */
    private function nextMonthly(\DateTime $completionDateTime, array $targetDays, string $timeComponent): string
    {
        $next = clone $completionDateTime;
        $currentDay = (int)$next->format('d');
        $currentMonth = (int)$next->format('n');
        $currentYear = (int)$next->format('Y');

        // Find next matching day in current month (if completion is early in month)
        sort($targetDays);
        foreach ($targetDays as $day) {
            if ($day > $currentDay) {
                // Check if day exists in this month (handle months with <31 days)
                $lastDayOfMonth = (int)$next->format('t');
                if ($day <= $lastDayOfMonth) {
                    $next->setDate($currentYear, $currentMonth, $day);
                    return $this->formatDateWithTime($next, $timeComponent);
                }
            }
        }

        // No match in current month, try next month
        $next->modify('+1 month');
        $nextMonth = (int)$next->format('n');
        $nextYear = (int)$next->format('Y');

        // Use first target day
        $firstDay = $targetDays[0];
        $lastDayOfNextMonth = (int)$next->format('t');
        $targetDay = min($firstDay, $lastDayOfNextMonth);

        $next->setDate($nextYear, $nextMonth, $targetDay);
        return $this->formatDateWithTime($next, $timeComponent);
    }

    /**
     * Parse weekly marker to array of day numbers
     * #w:mon:tue:fri -> [1, 2, 5] (Monday=1, Tuesday=2, Friday=5)
     *
     * @param string $marker The weekly marker
     * @return array Array of day numbers (0=Sunday, 6=Saturday)
     */
    private function parseWeeklyDays(string $marker): array
    {
        // Extract day codes after #w:
        if (!preg_match('/#w:(.+)/', $marker, $matches)) {
            return [];
        }

        $dayCodes = explode(':', $matches[1]);
        $dayMap = [
            'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3,
            'thu' => 4, 'fri' => 5, 'sat' => 6
        ];

        $days = [];
        foreach ($dayCodes as $code) {
            $code = strtolower(trim($code));
            if (isset($dayMap[$code])) {
                $days[] = $dayMap[$code];
            }
        }

        return array_unique($days);
    }

    /**
     * Parse monthly marker to array of day numbers
     * #m:1,11,21 -> [1, 11, 21]
     *
     * @param string $marker The monthly marker
     * @return array Array of day numbers (1-31)
     */
    private function parseMonthlyDays(string $marker): array
    {
        // Extract day numbers after #m:
        if (!preg_match('/#m:(.+)/', $marker, $matches)) {
            return [];
        }

        $dayNumbers = explode(',', $matches[1]);
        $days = [];
        foreach ($dayNumbers as $day) {
            $day = (int)trim($day);
            if ($day >= 1 && $day <= 31) {
                $days[] = $day;
            }
        }

        return array_unique($days);
    }

    /**
     * Extract time component from date string
     * "14:30:17 02-nov-2025" -> "14:30:17"
     * "02-nov-2025" -> "12:00:00"
     *
     * @param string $dateStr Date string
     * @return string Time in HH:MM:SS format
     */
    private function extractTimeComponent(string $dateStr): string
    {
        if (preg_match('/^(\d{2}:\d{2}:\d{2})\s+/', $dateStr, $matches)) {
            return $matches[1];
        }
        return '12:00:00';
    }

    /**
     * Format DateTime with time component
     *
     * @param \DateTime $dateTime The date/time
     * @param string $timeComponent Time in HH:MM:SS format
     * @return string Formatted as "HH:MM:SS DD-mon-YYYY"
     */
    private function formatDateWithTime(\DateTime $dateTime, string $timeComponent): string
    {
        [$hours, $minutes, $seconds] = explode(':', $timeComponent);
        $dateTime->setTime((int)$hours, (int)$minutes, (int)$seconds);

        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $monthName = $months[(int)$dateTime->format('n') - 1];

        return sprintf(
            '%s %02d-%s-%d',
            $timeComponent,
            (int)$dateTime->format('d'),
            $monthName,
            (int)$dateTime->format('Y')
        );
    }

    /**
     * Parse date string to DateTime object
     * Supports: "HH:MM:SS DD-mon-YYYY" or "DD-mon-YYYY"
     *
     * @param string $dateStr Date string
     * @param string $timezone Timezone string
     * @return \DateTime|null DateTime object or null if parsing fails
     */
    private function parseDate(string $dateStr, string $timezone): ?\DateTime
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        try {
            $timezoneObj = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $timezoneObj = new \DateTimeZone('UTC');
        }

        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

        // Check if it has time component
        if (preg_match('/^(\d{2}:\d{2}:\d{2})\s+(\d{2})-([a-z]{3})-(\d{4})$/i', $dateStr, $matches)) {
            $timePart = $matches[1];
            $day = $matches[2];
            $mon = strtolower($matches[3]);
            $year = $matches[4];

            $monIndex = array_search($mon, $months);
            if ($monIndex !== false) {
                $monNum = str_pad($monIndex + 1, 2, '0', STR_PAD_LEFT);
                $phpDateStr = "$year-$monNum-$day $timePart";
                try {
                    return new \DateTime($phpDateStr, $timezoneObj);
                } catch (\Exception $e) {
                    return null;
                }
            }
        } else {
            // Just date part
            if (preg_match('/^(\d{2})-([a-z]{3})-(\d{4})$/i', $dateStr, $matches)) {
                $day = $matches[1];
                $mon = strtolower($matches[2]);
                $year = $matches[3];

                $monIndex = array_search($mon, $months);
                if ($monIndex !== false) {
                    $monNum = str_pad($monIndex + 1, 2, '0', STR_PAD_LEFT);
                    $phpDateStr = "$year-$monNum-$day 12:00:00";
                    try {
                        return new \DateTime($phpDateStr, $timezoneObj);
                    } catch (\Exception $e) {
                        return null;
                    }
                }
            }
        }

        return null;
    }
}

