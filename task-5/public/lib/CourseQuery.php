<?php
/**
 * Task 5 — shared course query builder.
 *
 * The catalogue renders server-side and the AJAX endpoint uses this same
 * class, so the filtered list a JavaScript user sees and the one a
 * JavaScript-less user sees are produced by identical SQL. That is the point:
 * one query builder, two transports.
 */

declare(strict_types=1);

final class CourseQuery
{
    public const PER_PAGE = 9;

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Validate and normalise request parameters.
     *
     * Every value is checked against an allow-list before it can reach SQL.
     * The numeric values are cast, and the free text is escaped for LIKE so a
     * % cannot widen the search.
     */
    public static function fromRequest(): array
    {
        $get = static function (string $k, string $d = ''): string {
            $v = $_GET[$k] ?? $d;
            return is_string($v) ? trim($v) : $d;
        };

        $q = mb_substr($get('q'), 0, 80);

        $category = ctype_digit($get('category')) ? (int) $get('category') : null;

        $level = $get('level');
        if (!in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
            $level = '';
        }

        $maxRaw = $get('max_price');
        $maxPrice = ($maxRaw !== '' && ctype_digit($maxRaw)) ? (int) $maxRaw : null;

        $page = (int) $get('page', '1');
        if ($page < 1) {
            $page = 1;
        }

        return compact('q', 'category', 'level', 'maxPrice', 'page') + ['max_price' => $maxPrice];
    }

    private function where(array $c, array &$args): string
    {
        $clauses = ['co.is_published = 1'];

        if (($c['q'] ?? '') !== '') {
            $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $c['q']) . '%';
            $clauses[] = '(co.title LIKE ? OR co.summary LIKE ?)';
            $args[] = $pattern;
            $args[] = $pattern;
        }
        if (!empty($c['category'])) {
            $clauses[] = 'co.category_id = ?';
            $args[] = (int) $c['category'];
        }
        if (($c['level'] ?? '') !== '') {
            $clauses[] = 'co.level = ?';
            $args[] = (string) $c['level'];
        }
        if (($c['max_price'] ?? null) !== null) {
            $clauses[] = 'co.price_cents <= ?';
            $args[] = (int) $c['max_price'];
        }

        return implode(' AND ', $clauses);
    }

    public function find(array $c): array
    {
        $args = [];
        $sql  = $this->where($c, $args);

        $total = (int) $this->db->value("SELECT COUNT(*) FROM courses co WHERE {$sql}", $args);

        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min(max(1, (int) $c['page']), $pages);
        $limit = self::PER_PAGE;
        $offset = ($page - 1) * $limit;

        $rows = $this->db->all(
            "SELECT co.id, co.code, co.title, co.summary, co.level, co.price_cents, co.duration_min,
                    cat.name AS category,
                    (SELECT COUNT(*) FROM lessons l WHERE l.course_id = co.id) AS lesson_count,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = co.id AND e.status = 'active') AS enrolled
             FROM courses co
             JOIN categories cat ON cat.id = co.category_id
             WHERE {$sql}
             ORDER BY co.created_at DESC, co.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Build a catalogue URL that preserves the current filters. */
    public static function url(array $c): string
    {
        $params = [];
        if (($c['q'] ?? '') !== '')              { $params['q'] = $c['q']; }
        if (!empty($c['category']))              { $params['category'] = $c['category']; }
        if (($c['level'] ?? '') !== '')          { $params['level'] = $c['level']; }
        if (($c['max_price'] ?? null) !== null)  { $params['max_price'] = $c['max_price']; }
        if ((int) ($c['page'] ?? 1) > 1)         { $params['page'] = $c['page']; }

        return 'index.php' . ($params ? '?' . http_build_query($params) : '');
    }
}
