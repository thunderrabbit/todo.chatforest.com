<?php

namespace Todo;

/**
 * TodoReader - reads and parses markdown todo files
 *
 * Handles reading todo markdown files and parsing individual todo items
 * including support for wiki-style links [[...]]
 */
class TodoReader
{
    public function __construct(
        private \Config $config,
    ) {
    }

    /**
     * Read the raw markdown content from a todo file
     *
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @param string $project The project name (without .md extension)
     * @return string The raw markdown content
     * @throws \Exception If file doesn't exist or can't be read
     */
    public function readRawContent(string $username, int $year, string $project): string
    {
        $filePath = $this->getFilePath($username, $year, $project);

        if (!file_exists($filePath)) {
            throw new \Exception("Todo file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Could not read todo file: $filePath");
        }

        return $content;
    }

    /**
     * Get the file path for a todo project
     *
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @param string $project The project name (without .md extension)
     * @return string The full file path
     */
    public function getFilePath(string $username, int $year, string $project): string
    {
        $sanitizedProject = $this->sanitizeProjectName($project);
        return $this->config->todos_path . "/$username/$year/$sanitizedProject.md";
    }

    /**
     * Sanitize project name for use in file path
     * Only alphanumeric, underscores, and hyphens allowed
     *
     * @param string $project The project name
     * @return string Sanitized project name
     */
    private function sanitizeProjectName(string $project): string
    {
        // Allow alphanumeric, underscores, hyphens only
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project);
        // Remove leading/trailing underscores
        $sanitized = trim($sanitized, '_');
        // Ensure it's not empty
        return !empty($sanitized) ? $sanitized : 'default';
    }

    /**
     * Parse markdown content into structured todo items
     *
     * @param string $markdown The raw markdown content
     * @return array Array of todo items, each with: isComplete, createDate, description, completeDate, hasLink, linkText, linkFile, originalIndex
     */
    public function parseTodos(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $todos = [];
        $originalIndex = 0;

        foreach ($lines as $line) {
            $todo = $this->parseTodoLine(trim($line));
            if ($todo !== null) {
                $todo['originalIndex'] = $originalIndex;
                $todos[] = $todo;
                $originalIndex++;
            }
        }

        return $todos;
    }

    /**
     * Parse a single todo line
     *
     * Format: - [ ] DD-mon-YYYY description or - [x] DD-mon-YYYY description DD-mon-YYYY
     * Linked: - [ ] DD-mon-YYYY [[link text]] or - [x] DD-mon-YYYY [[link text]] DD-mon-YYYY
     *
     * @param string $line The todo line
     * @return array|null Parsed todo data or null if not a valid todo line
     */
    private function parseTodoLine(string $line): ?array
    {
        // Check if it's a todo line (starts with - [ ] or - [x])
        if (!preg_match('/^-\s*\[([ x])\]\s*(.+)$/', $line, $matches)) {
            return null;
        }

        $isComplete = $matches[1] === 'x';
        $remainder = trim($matches[2]);

        // Try to extract dates and content
        // Date format: [HH:MM:SS ]DD-mon-YYYY (case insensitive for month abbreviation)
        $datePattern = '((?:\d{2}:\d{2}:\d{2}\s+)?\d{2}-[a-z]{3}-\d{4})';

        // Check if it has a link (wiki-style [[...]])
        $hasLink = preg_match('/\[\[([^\]]+)\]\]/', $remainder, $linkMatches);

        $createDate = null;
        $completeDate = null;
        $description = '';
        $linkText = '';
        $linkFile = '';

        if ($hasLink) {
            $linkText = $linkMatches[1];
            $linkFile = $this->linkTextToFileName($linkText);

            // Replace the link with placeholder for date parsing
            $remainderWithPlaceholder = preg_replace('/\[\[([^\]]+)\]\]/', 'LINK_PLACEHOLDER', $remainder);

            // Extract dates
            if (preg_match_all("/$datePattern/i", $remainderWithPlaceholder, $dateMatches)) {
                $dates = $dateMatches[1];
                $createDate = $dates[0];
                if ($isComplete && isset($dates[1])) {
                    $completeDate = $dates[1];
                }

                // Extract description (everything except dates and link placeholder)
                $description = $remainderWithPlaceholder;
                foreach ($dates as $date) {
                    $description = str_replace($date, '', $description);
                }
                $description = str_replace('LINK_PLACEHOLDER', '[[' . $linkText . ']]', trim($description));
            }
        } else {
            // No link, just dates and description
            if (preg_match_all("/$datePattern/i", $remainder, $dateMatches)) {
                $dates = $dateMatches[1];
                $createDate = $dates[0];
                if ($isComplete && isset($dates[1])) {
                    $completeDate = $dates[1];
                }

                // Extract description (everything except dates)
                $description = $remainder;
                foreach ($dates as $date) {
                    $description = str_replace($date, '', $description);
                }
                $description = trim($description);
            }
        }

        // Extract recurring marker if present
        $recurringMarker = $this->extractRecurringMarker($description);

        return [
            'isComplete' => $isComplete,
            'createDate' => $createDate,
            'description' => $description,
            'completeDate' => $completeDate,
            'hasLink' => $hasLink,
            'linkText' => $linkText,
            'linkFile' => $linkFile,
            'recurringMarker' => $recurringMarker,
        ];
    }

    /**
     * Extract recurring marker from description
     *
     * Returns: null, '#d', '#w:mon,tue,fri', or '#m:1,11,21'
     *
     * @param string $description The todo description
     * @return string|null The recurring marker or null
     */
    private function extractRecurringMarker(string $description): ?string
    {
        // Match #d for daily
        if (preg_match('/#d\b/', $description)) {
            return '#d';
        }

        // Match #w:mon:tue:fri for weekly (3-letter day codes, colon-separated)
        if (preg_match('/#w:([a-z]{3}(?::[a-z]{3})*)/i', $description, $matches)) {
            return '#w:' . strtolower($matches[1]);
        }

        // Match #m:1,11,21 for monthly (comma-separated day numbers)
        if (preg_match('/#m:(\d+(?:,\d+)*)/', $description, $matches)) {
            return '#m:' . $matches[1];
        }

        return null;
    }

    /**
     * Convert link text to filename
     * Lowercase, spaces to underscores
     *
     * @param string $linkText The link text
     * @return string The filename (without .md extension)
     */
    public function linkTextToFileName(string $linkText): string
    {
        $filename = strtolower($linkText);
        $filename = str_replace(' ', '_', $filename);
        return $filename;
    }

    /**
     * Check if a todo file exists
     *
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @param string $project The project name (without .md extension)
     * @return bool True if file exists
     */
    public function fileExists(string $username, int $year, string $project): bool
    {
        $filePath = $this->getFilePath($username, $year, $project);
        return file_exists($filePath);
    }

    /**
     * List all todo files for a user in a given year
     *
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @return array Array of project names (without .md extension)
     */
    public function listProjects(string $username, int $year): array
    {
        $yearDir = $this->config->todos_path . "/$username/$year";

        if (!is_dir($yearDir)) {
            return [];
        }

        $files = scandir($yearDir);
        $projects = [];

        foreach ($files as $file) {
            if (preg_match('/^(.+)\.md$/', $file, $matches)) {
                $projects[] = $matches[1];
            }
        }

        sort($projects);
        return $projects;
    }
}
