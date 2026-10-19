<?php
/**
 * Task 5 bootstrap.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/bootstrap.php';
require_once __DIR__ . '/lib/Otp.php';

apex_register_base(__DIR__);

$GLOBALS['APEX_EXTRA_CSS'] = ['assets/css/lms.css'];

/** @return array<string,string> */
function lms_nav(): array
{
    $nav = [
        'index.php'     => 'Courses',
        'my-courses.php' => 'My learning',
    ];
    if (current_user_role() === 'admin') {
        $nav['admin/index.php']   = 'Dashboard';
        $nav['admin/courses.php'] = 'Manage courses';
    }
    return $nav;
}

function lms_money(int $cents): string
{
    return $cents === 0 ? 'Free' : '₹' . number_format($cents / 100, 2);
}

/** Shared OTP service instance for the current user. */
function otp_service(): Otp
{
    static $otp = null;
    if ($otp === null) {
        $db = Database::instance($GLOBALS['APEX_CONFIG'] ?? null, 'db_lms');
        $otp = new Otp(
            $db->pdo(),
            $GLOBALS['APEX_CONFIG']['security'] ?? [],
            (bool) ($GLOBALS['APEX_CONFIG']['app']['debug'] ?? false)
        );
    }
    return $otp;
}
