<?php

namespace Todo;

/**
 * TodoWriter - writes markdown todo files
 *
 * Handles writing todo markdown files with proper formatting
 */
class TodoWriter
{
    public function __construct(
        private \Config $config,
    ) {
    }

    /**
     * Write todo items to a markdown file
     *
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @param string $project The project name (without .md extension)
     * @param array $todos Array of todo items (as returned by TodoReader)
     * @return bool True on success
     * @throws \Exception If writing fails
     */
    public function writeTodos(string $username, int $year, string $project, array $todos): bool
    {
        $filePath = $this->getFilePath($username, $year, $project);

        // Ensure the directory exists
        $dirPath = dirname($filePath);
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0755, true)) {
                throw new \Exception("Could not create directory: $dirPath");
            }
        }

        // Generate markdown content
        $markdown = $this->todosToMarkdown($todos);

        // Write to file
        $result = file_put_contents($filePath, $markdown);
        if ($result === false) {
            throw new \Exception("Could not write todo file: $filePath");
        }

        return true;
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
     * Convert todo items array to markdown format
     *
     * @param array $todos Array of todo items
     * @return string Markdown formatted content
     */
    private function todosToMarkdown(array $todos): string
    {
        $lines = [];

        foreach ($todos as $todo) {
            $lines[] = $this->todoToMarkdownLine($todo);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Convert a single todo item to markdown line
     *
     * @param array $todo The todo item
     * @return string Markdown formatted line
     */
    private function todoToMarkdownLine(array $todo): string
    {
        $checkbox = $todo['isComplete'] ? '[x]' : '[ ]';
        $createDate = $todo['createDate'] ?? $this->getTodayDate();
        $completeDate = $todo['completeDate'];

        // Build the line
        $line = "- $checkbox $createDate ";

        // Add description
        if ($todo['hasLink']) {
            // Has a wiki-style link
            $line .= "[[" . $todo['linkText'] . "]]";
        } else {
            // Plain description
            $line .= $todo['description'];
        }

        // Add completion date if completed
        if ($todo['isComplete'] && $completeDate) {
            $line .= " $completeDate";
        }

        return $line;
    }

    /**
     * Get today's date in DD-mon-YYYY format
     *
     * @return string Formatted date
     */
    private function getTodayDate(): string
    {
        return date('d-M-Y');
    }

    /**
     * Create an empty todo file
     *
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @param string $project The project name (without .md extension)
     * @return bool True on success
     * @throws \Exception If creation fails
     */
    public function createEmptyFile(string $username, int $year, string $project): bool
    {
        return $this->writeTodos($username, $year, $project, []);
    }
}

