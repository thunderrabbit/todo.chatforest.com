<?php

namespace Todo;

/**
 * TodoSorter - sorts todo items according to the preferred order
 *
 * Sorts todos into three groups:
 * 1. Unfinished link-items sorted by name (alphabetically, case-insensitive)
 * 2. Finished items sorted by finished time (chronologically)
 * 3. Unfinished non-link items sorted by start time (chronologically)
 */
class TodoSorter
{
    /**
     * Sort todos according to the preferred order
     *
     * @param array $todos Array of todo items (as returned by TodoReader::parseTodos)
     * @return array Sorted array of todo items
     */
    public static function sortTodos(array $todos): array
    {
        // Separate todos into three groups
        $group1 = []; // Unfinished link-items
        $group2 = []; // Finished items
        $group3 = []; // Unfinished non-link items
        $other = [];  // Other lines (empty, comments, etc.)

        foreach ($todos as $todo) {
            $isComplete = $todo['isComplete'] ?? false;
            $hasLink = $todo['hasLink'] ?? false;

            if (!$isComplete && $hasLink) {
                // Group 1: Unfinished link-items
                $group1[] = $todo;
            } elseif ($isComplete) {
                // Group 2: Finished items
                $group2[] = $todo;
            } elseif (!$isComplete && !$hasLink) {
                // Group 3: Unfinished non-link items
                $group3[] = $todo;
            } else {
                // Other items (shouldn't happen, but just in case)
                $other[] = $todo;
            }
        }

        // Sort Group 1: Unfinished link-items by name (case-insensitive)
        usort($group1, function($a, $b) {
            $nameA = strtolower($a['linkText'] ?? '');
            $nameB = strtolower($b['linkText'] ?? '');
            return strcmp($nameA, $nameB);
        });

        // Sort Group 2: Finished items by finished time (chronologically)
        usort($group2, function($a, $b) {
            $timeA = self::parseDateTime($a['completeDate'] ?? '');
            $timeB = self::parseDateTime($b['completeDate'] ?? '');
            return $timeA <=> $timeB;
        });

        // Sort Group 3: Unfinished items by start time (chronologically)
        usort($group3, function($a, $b) {
            $timeA = self::parseDateTime($a['createDate'] ?? '');
            $timeB = self::parseDateTime($b['createDate'] ?? '');
            return $timeA <=> $timeB;
        });

        // Combine groups in order: Group 1, Group 2, Group 3, Other
        return array_merge($group1, $group2, $group3, $other);
    }

    /**
     * Parse a datetime string to a sortable timestamp
     *
     * Handles formats:
     * - "HH:MM:SS DD-MMM-YYYY" (e.g., "06:38:12 03-Nov-2025")
     * - "DD-MMM-YYYY" (e.g., "03-Nov-2025")
     *
     * @param string $dateTimeString The date/time string
     * @return int Unix timestamp for comparison (0 if parsing fails)
     */
    private static function parseDateTime(string $dateTimeString): int
    {
        if (empty($dateTimeString)) {
            return 0;
        }

        // Check if it has a time component first (before normalization)
        $hasTime = preg_match('/^(\d{2}:\d{2}:\d{2})\s+(.+)$/', $dateTimeString, $timeMatches);

        if ($hasTime) {
            $timePart = $timeMatches[1];
            $dateOnly = $timeMatches[2];
        } else {
            $timePart = '12:00:00';
            $dateOnly = $dateTimeString;
        }

        // Normalize the month abbreviation to standard case in the date part
        // Handle both "03-Nov-2025" and "03-nov-2025"
        $datePart = preg_replace_callback(
            '/(\d{2})-([a-z]{3})-(\d{4})/i',
            function($matches) {
                return $matches[1] . '-' . ucfirst(strtolower($matches[2])) . '-' . $matches[3];
            },
            $dateOnly
        );

        // Parse the date (DD-MMM-YYYY format)
        // Create a DateTime object
        try {
            // Convert DD-MMM-YYYY to YYYY-MM-DD for parsing
            if (preg_match('/^(\d{2})-([A-Z][a-z]{2})-(\d{4})$/', $datePart, $dateMatches)) {
                $day = $dateMatches[1];
                $month = $dateMatches[2];
                $year = $dateMatches[3];

                // Map month abbreviation to number
                $monthMap = [
                    'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
                    'May' => '05', 'Jun' => '06', 'Jul' => '07', 'Aug' => '08',
                    'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12'
                ];

                $monthNum = $monthMap[$month] ?? '01';
                $isoDate = "$year-$monthNum-$day";
                $dateTime = $isoDate . ' ' . $timePart;

                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTime);
                if ($dt) {
                    return $dt->getTimestamp();
                }
            }
        } catch (\Exception $e) {
            // If parsing fails, return 0
        }

        return 0;
    }
}

