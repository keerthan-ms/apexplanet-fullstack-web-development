<?php
/**
 * Task 3 bootstrap.
 *
 * Loads the shared library and declares the extra stylesheet this task needs
 * on top of the base sheet, so no page has to remember to do it.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/bootstrap.php';
apex_register_base(__DIR__);

$GLOBALS['APEX_EXTRA_CSS'] = ['assets/css/task3.css'];
