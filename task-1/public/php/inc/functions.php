<?php
/**
 * Shared helper functions for the Task 1 PHP demo.
 * Loaded with require() because the page cannot render without e().
 */

declare(strict_types=1);

/** Escape a value for safe output in HTML. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Return a class attribute for a badge, based on a status word. */
function badge_class(string $status): string
{
    return match (strtolower($status)) {
        'complete', 'ok', 'done' => 'badge badge-green',
        'progress', 'active'    => 'badge badge-blue',
        default                  => 'badge badge-amber',
    };
}
