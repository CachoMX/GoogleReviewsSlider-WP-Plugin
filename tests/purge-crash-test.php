<?php
/**
 * Reproduces the easthollandvet.net fatal: a half-loaded WP Rocket whose
 * rocket_clean_domain() calls a function that does not exist yet.
 */

error_reporting(E_ALL);

define('GRS_PLUGIN_PATH', dirname(__DIR__) . '/');
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);

$GLOBALS['fired_actions'] = array('wp_loaded' => 0);
$GLOBALS['deferred'] = array();

function did_action($hook) { return $GLOBALS['fired_actions'][$hook] ?? 0; }
function add_action($hook, $cb = null, ...$a) { if ($cb) { $GLOBALS['deferred'][$hook][] = $cb; } }
function has_action($hook) { return false; }
function do_action(...$a) {}
function get_option($k, $d = false) { return $d; }
function update_option(...$a) { return true; }
function delete_option(...$a) { return true; }
function __($s, $d = null) { return $s; }
function wp_cache_delete(...$a) {}

// Half-loaded WP Rocket: the exact failure from the production stack trace.
function rocket_clean_domain() {
    rocket_is_importing(); // undefined -> throws Error
}

require GRS_PLUGIN_PATH . 'includes/sync-handler.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS  $label\n"; } else { $fail++; echo "FAIL  $label\n"; }
}

// Scenario 1: plugins_loaded-time call (wp_loaded not fired) -> must defer, not execute.
GRS_Sync::purge_page_caches();
check('early call defers to wp_loaded instead of executing', isset($GLOBALS['deferred']['wp_loaded']) && count($GLOBALS['deferred']['wp_loaded']) === 1);

// Scenario 2: wp_loaded fires later in the same request -> deferred purge runs
// against the broken rocket_clean_domain and must survive.
$GLOBALS['fired_actions']['wp_loaded'] = 1;
$survived = true;
try {
    foreach ($GLOBALS['deferred']['wp_loaded'] as $cb) { call_user_func($cb); }
} catch (\Throwable $e) {
    $survived = false;
}
check('deferred purge survives broken rocket_clean_domain (no fatal)', $survived);

// Scenario 3: direct call after load (normal sync path) with broken cache plugin.
$survived = true;
try {
    GRS_Sync::purge_page_caches();
} catch (\Throwable $e) {
    $survived = false;
}
check('post-load purge survives broken cache plugin', $survived);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
