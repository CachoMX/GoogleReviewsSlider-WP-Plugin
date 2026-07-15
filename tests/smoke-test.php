<?php
/**
 * WP-less smoke test: exercises GRS_SerpAPI::map_review()/parse dates and
 * GRS_Database::replace_reviews() swap invariants against a fake wpdb.
 */

error_reporting(E_ALL);

define('GRS_PLUGIN_PATH', dirname(__DIR__) . '/');
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);

class WP_Error {
    private $code; private $message;
    public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
function add_action(...$a) {}
function add_shortcode(...$a) {}
function get_option($k, $d = false) { return $d; }
function wp_json_encode($v) { return json_encode($v); }
function esc_sql($s) { return addslashes($s); }
function __($s, $d = null) { return $s; }

class FakeWPDB {
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $last_error = '';
    public $rows = array();
    public $fail_inserts = false;
    private $next_id = 1;

    public function insert($table, $data, $fmt = null) {
        if ($this->fail_inserts) { $this->last_error = 'forced failure'; return false; }
        foreach ($this->rows as $r) {
            if ($r['place_id'] === $data['place_id'] && $r['review_id'] === $data['review_id']) {
                $this->last_error = 'Duplicate entry';
                return false;
            }
        }
        $data['id'] = $this->next_id++;
        $this->rows[] = $data;
        return 1;
    }
    public function delete($table, $where, $fmt = null) {
        $n = 0;
        foreach ($this->rows as $k => $r) {
            if ($r['place_id'] === $where['place_id']) { unset($this->rows[$k]); $n++; }
        }
        $this->rows = array_values($this->rows);
        return $n;
    }
    public function update($table, $data, $where, $f1 = null, $f2 = null) {
        $n = 0;
        foreach ($this->rows as $k => $r) {
            if ($r['place_id'] === $where['place_id']) { $this->rows[$k]['place_id'] = $data['place_id']; $n++; }
        }
        return $n;
    }
    public function query($sql) { return 0; }
    public function prepare($q, ...$args) { return $q . '|' . json_encode($args); }
    public function get_var($q) {
        if (strpos($q, 'SHOW TABLES') !== false) {
            // pretend every table exists so create_tables is skipped
            preg_match("/LIKE '([^']+)'/", $q, $m);
            return isset($m[1]) ? stripslashes($m[1]) : null;
        }
        return null;
    }
    public function count_for($place) {
        $n = 0;
        foreach ($this->rows as $r) if ($r['place_id'] === $place) $n++;
        return $n;
    }
    public function times_for($place) {
        $t = array();
        foreach ($this->rows as $r) if ($r['place_id'] === $place) $t[] = $r['time'];
        rsort($t);
        return $t;
    }
}

$GLOBALS['wpdb'] = new FakeWPDB();

require GRS_PLUGIN_PATH . 'includes/database-handler.php';
require GRS_PLUGIN_PATH . 'includes/serpapi-handler.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS  $label\n"; }
    else { $fail++; echo "FAIL  $label\n"; }
}

// --- map_review matrix ---
$api = new GRS_SerpAPI('test-key');

$valid = array(
    'review_id' => 'r1',
    'rating' => 5,
    'snippet' => 'Great service!',
    'iso_date' => '2026-06-01T15:30:00Z',
    'date' => 'a month ago',
    'user' => array('name' => 'Ana', 'link' => 'https://x', 'thumbnail' => 'https://y', 'local_guide' => true, 'reviews' => 12),
    'likes' => 3,
    'response' => array('snippet' => 'Thanks!', 'date' => 'a week ago'),
);
$m = $api->map_review($valid);
check('map_review: valid review maps', is_array($m));
check('map_review: iso_date -> exact UTC epoch', $m['time'] === strtotime('2026-06-01T15:30:00Z'));
check('map_review: review_id preserved', $m['review_id'] === 'r1');
check('map_review: owner response relative date parsed to int', is_int($m['response_from_owner_time']));

$no_text = $valid; unset($no_text['snippet']);
check('map_review: no text -> rejected (null)', $api->map_review($no_text) === null);

$no_date = $valid; unset($no_date['iso_date']);
check('map_review: missing iso_date -> rejected, never time()', $api->map_review($no_date) === null);

$bad_date = $valid; $bad_date['iso_date'] = 'not-a-date-!!!';
check('map_review: garbage iso_date -> rejected', $api->map_review($bad_date) === null);

$fallback_snip = $valid; unset($fallback_snip['snippet']);
$fallback_snip['extracted_snippet'] = array('original' => 'Texto original');
$m2 = $api->map_review($fallback_snip);
check('map_review: extracted_snippet fallback works', is_array($m2) && $m2['text'] === 'Texto original');

$no_id = $valid; unset($no_id['review_id']);
$m3 = $api->map_review($no_id);
check('map_review: missing review_id -> md5 fallback', is_array($m3) && $m3['review_id'] === md5('Ana|2026-06-01T15:30:00Z'));

// --- replace_reviews swap invariants ---
$wpdb = $GLOBALS['wpdb'];

// seed 3 old rows for place P (one shares review_id with the new set)
$base = strtotime('2026-01-01T00:00:00Z');
foreach (array('old-a', 'old-b', 'shared-1') as $i => $rid) {
    $wpdb->rows[] = array('id' => 900 + $i, 'place_id' => 'P', 'review_id' => $rid, 'time' => $base + $i, 'author_name' => 'Old');
}

// 12 new reviews, newest-last input order (worst case), one sharing review_id
$new = array();
for ($i = 1; $i <= 12; $i++) {
    $new[] = array(
        'review_id' => $i === 12 ? 'shared-1' : "new-$i",
        'author_name' => "User $i",
        'rating' => 5,
        'text' => "Review $i",
        'time' => strtotime('2026-06-01T00:00:00Z') + $i * 3600,
    );
}

$saved = GRS_Database::replace_reviews('P', $new);
check('replace: returns 10 (cap applied to 12 inputs)', $saved === 10);
check('replace: table holds exactly 10 rows for P', $wpdb->count_for('P') === 10);
$expected_times = array();
for ($i = 12; $i >= 3; $i--) { $expected_times[] = strtotime('2026-06-01T00:00:00Z') + $i * 3600; }
check('replace: kept the 10 NEWEST by time', $wpdb->times_for('P') === $expected_times);
check('replace: no ::staging rows remain', $wpdb->count_for('P::staging') === 0);
check('replace: no ::retiring rows remain', $wpdb->count_for('P::retiring') === 0);
check('replace: old rows fully replaced (shared id no dup)', $wpdb->count_for('P') === 10);

// empty set refused
$before = $wpdb->rows;
$r = GRS_Database::replace_reviews('P', array());
check('replace: empty set -> WP_Error', is_wp_error($r));
check('replace: empty set leaves table untouched', $wpdb->rows === $before);

// all inserts failing -> old data preserved
$wpdb->fail_inserts = true;
$before = $wpdb->rows;
$r = GRS_Database::replace_reviews('P', $new);
$wpdb->fail_inserts = false;
check('replace: total insert failure -> WP_Error', is_wp_error($r));
check('replace: total insert failure preserves ALL old rows', $wpdb->rows === $before && $wpdb->count_for('P') === 10);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
