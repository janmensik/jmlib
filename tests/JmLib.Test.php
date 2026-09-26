<?php

namespace Janmensik\Jmlib;

// --- Mocks for curl functions ---
$mock_curl_exec_result = false;
$mock_curl_info_filetime = -1;
$mock_curl_url = '';

function curl_init($url = null) {
    global $mock_curl_url;
    $mock_curl_url = $url;
    return new \stdClass();
}

function curl_setopt($handle, $option, $value) {
    return true;
}

function curl_exec($handle) {
    global $mock_curl_exec_result;
    return $mock_curl_exec_result;
}

function curl_getinfo($handle, $option = 0) {
    global $mock_curl_info_filetime;
    // Check for both constants as the code handles both
    $check_t = defined('CURLINFO_FILETIME_T') ? CURLINFO_FILETIME_T : -999;
    if ($option === CURLINFO_FILETIME || $option === $check_t) {
        return $mock_curl_info_filetime;
    }
    return -1;
}

function curl_close($handle) {
    return;
}
// --------------------------------

# utf2ascii()
test('utf2ascii conversion', function (string $utf, string $ascii) {
    expect(JmLib::utf2ascii($utf))->toBe($ascii);
})->with([
    'czech diacritics' => [
        'příliš žluťoučký kůň úpěl ďábelské ódy',
        'prilis zlutoucky kun upel dabelske ody',
    ],
    'empty string' => ['', ''],
    'ascii string' => ['hello world', 'hello world'],
    'other languages' => [
        'crème brûlée, façade, café, résumé, über',
        'creme brulee, facade, cafe, resume, uber',
    ],
]);

# text2seolink()
test('text2seolink conversion', function (string $input, string $expected) {
    expect(JmLib::text2seolink($input))->toBe($expected);
})->with([
    'basic' => ['Hello World', 'hello-world'],
    'diacritics' => ['Příliš žluťoučký kůň úpěl ďábelské ódy', 'prilis-zlutoucky-kun-upel-dabelske-ody'],
    'special chars' => ['A string with!@#$%^&*() special chars', 'a-string-with-special-chars'],
    'collapse hyphens' => ['multiple---spaces   and --- hyphens', 'multiple-spaces-and-hyphens'],
    'trim hyphens 1' => ['---leading and trailing---', 'leading-and-trailing'],
    'trim hyphens 2' => ['  spaces and hyphens  -- ', 'spaces-and-hyphens'],
    'mixed case' => ['This Is a MiXeD CaSe String', 'this-is-a-mixed-case-string'],
    'numbers' => ['Test 123 with 456 numbers', 'test-123-with-456-numbers'],
    'only special' => ['!@#$%^&*()_=+', ''],
    'empty' => ['', ''],
    'already seo' => ['this-is-already-a-seo-link', 'this-is-already-a-seo-link'],
]);

# parseFloat()
test('parseFloat parses numbers with commas and spaces and returns null for null', function () {
    expect(JmLib::parseFloat(' 1 234,56'))->toBe(1234.56);
    expect(JmLib::parseFloat('42'))->toBe(42.0);
    expect(JmLib::parseFloat(null))->toBeNull();
});

# parseDate()
test('parseDate handles unix-timestamp-like input and strtotime formats', function () {
    $ts = '1609459200'; // 2021-01-01 00:00:00
    expect(JmLib::parseDate($ts))->toBe((int)$ts);

    $date = '2020-01-02';
    expect(JmLib::parseDate($date))->toBe(strtotime($date));
});

test('parseDate handles various date formats and force option', function () {
    // dd. mm. yyyy hh:mm:ss
    expect(JmLib::parseDate('01. 02. 2023 14:30:15'))->toBe(mktime(14, 30, 15, 2, 1, 2023));

    // dd. mm. yyyy (defaults to noon)
    expect(JmLib::parseDate('01. 02. 2023'))->toBe(mktime(12, 0, 0, 2, 1, 2023));

    // Invalid date, force=false
    expect(JmLib::parseDate('not a date', false))->toBeNull();

    // Invalid date, force=true (should return today at noon)
    expect(JmLib::parseDate('not a date', true))->toBe(mktime(12, 0, 0));

    // dd. mm. hh:mm (uses current year)
    $expected = mktime(10, 20, 0, 2, 1); // 1st Feb of current year at 10:20
    expect(JmLib::parseDate('01. 02. 10:20'))->toBe($expected);
});

# stripos and strripos()
test('stripos and strripos behave case-insensitively', function () {
    expect(JmLib::stripos('Hello World', 'w'))->toBe(6);
    expect(JmLib::strripos('ababa', 'a'))->toBe(4);
});

# pagination()
test('pagination returns expected structure and pages include first and last', function () {
    $out = JmLib::pagination(10, 95, 5, 7);
    expect(is_array($out))->toBe(true);
    expect($out['total_pages'])->toBe(10);
    expect($out['previous'])->toBe(4);
    expect($out['next'])->toBe(6);
    expect(in_array(1, $out['pages']))->toBe(true);
    expect(in_array(10, $out['pages']))->toBe(true);
});
test('pagination handles edge cases', function () {
    // Not enough items for pagination
    expect(JmLib::pagination(10, 5))->toBe(false);

    // Zero on_page must not divide by zero
    expect(JmLib::pagination(0, 10))->toBe(false);

    // All pages fit within max_links_to_show
    $out = JmLib::pagination(10, 50, 1, 7);
    expect($out['pages'])->toBe([1, 2, 3, 4, 5]);

    // Ellipsis check
    $out = JmLib::pagination(10, 200, 10, 7); // 20 pages total
    // Expected: [1, null, 8, 9, 10, 11, 12, null, 20] - let's check for the nulls
    expect(in_array(null, $out['pages']))->toBe(true);
});

# createPassword()
test('createPassword returns hex substring of requested length', function () {
    $pw = JmLib::createPassword(6, 'testsalt');
    expect(is_string($pw))->toBe(true);
    expect(strlen($pw))->toBe(6);
    // sha1 produces hex chars -> check hex
    expect((bool)preg_match('/^[0-9a-fA-F]{6}$/', $pw))->toBe(true);
});

# createToken()
test('createToken returns a lowercase hex string of the correct length', function () {
    $token = JmLib::createToken(32);
    expect(is_string($token))->toBe(true);
    expect(strlen($token))->toBe(64);  // 32 bytes = 64 hex chars
    expect((bool)preg_match('/^[0-9a-f]{64}$/', $token))->toBe(true);

    $short = JmLib::createToken(8);
    expect(strlen($short))->toBe(16);
});

test('createToken clamps invalid byte counts to 32', function () {
    expect(strlen(JmLib::createToken(0)))->toBe(64);
    expect(strlen(JmLib::createToken(-5)))->toBe(64);
});

# getUrl()
test('getUrl handles various parameter options', function () {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'example.com';

    // Remove existing params
    $_SERVER['REQUEST_URI'] = '/path?x=1';
    $url = JmLib::getUrl(true, true);
    expect($url)->toBe('https://example.com/path?');

    // No existing params
    $_SERVER['REQUEST_URI'] = '/path';
    $url = JmLib::getUrl(true, false);
    expect($url)->toBe('https://example.com/path?');
});

# getip()
test('getip returns REMOTE_ADDR and ignores potentially spoofed headers', function () {
    // 1. REMOTE_ADDR as baseline
    $_SERVER['HTTP_CLIENT_IP'] = '';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    expect(JmLib::getip())->toBe('127.0.0.1');

    // 2. HTTP_X_FORWARDED_FOR should be ignored
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';
    expect(JmLib::getip())->toBe('127.0.0.1');

    // 3. HTTP_CLIENT_IP should be ignored
    $_SERVER['HTTP_CLIENT_IP'] = '10.0.0.1';
    expect(JmLib::getip())->toBe('127.0.0.1');
});

# rmdirr()
test('rmdirr removes a directory tree', function () {
    $tmp = sys_get_temp_dir() . '/jmlib_test_' . uniqid();
    mkdir($tmp);
    file_put_contents($tmp . '/file.txt', 'x');
    mkdir($tmp . '/sub');
    file_put_contents($tmp . '/sub/file2.txt', 'y');

    expect(is_dir($tmp))->toBe(true);
    $removed = JmLib::rmdirr($tmp);
    expect($removed)->toBe(true);
    expect(is_dir($tmp))->toBe(false);
});

# getInterval()
test('getInterval returns correct timestamps for predefined text names', function () {
    $now = strtotime('2023-10-26 15:00:00');

    // today
    $today = JmLib::getInterval('today', $now);
    expect($today['from'])->toBe(strtotime('2023-10-26 00:00:00'));
    expect($today['till'])->toBe(strtotime('2023-10-26 23:59:59'));

    // yesterday
    $yesterday = JmLib::getInterval('yesterday', $now);
    expect($yesterday['from'])->toBe(strtotime('2023-10-25 00:00:00'));
    expect($yesterday['till'])->toBe(strtotime('2023-10-25 23:59:59'));

    // last7days
    $last7 = JmLib::getInterval('last7days', $now);
    expect($last7['from'])->toBe(strtotime('2023-10-20 00:00:00'));
    expect($last7['till'])->toBe(strtotime('2023-10-26 23:59:59'));

    // this month
    $month = JmLib::getInterval('month', $now);
    expect($month['from'])->toBe(strtotime('2023-10-01 00:00:00'));
    expect($month['till'])->toBe(strtotime('2023-10-31 23:59:59'));

    // last month
    $lastMonth = JmLib::getInterval('lastmonth', $now);
    expect($lastMonth['from'])->toBe(strtotime('2023-09-01 00:00:00'));
    expect($lastMonth['till'])->toBe(strtotime('2023-09-30 23:59:59'));

    // this year - note: implementation's 'till' is end of current month, not year
    $thisYear = JmLib::getInterval('thisyear', $now);
    expect($thisYear['from'])->toBe(strtotime('2023-01-01 00:00:00'));
    expect($thisYear['till'])->toBe(strtotime('2023-10-31 23:59:59'));
});

test('getInterval returns null for unknown names and handles $return_only safely', function () {
    $now = strtotime('2023-10-26 15:00:00');

    expect(JmLib::getInterval('unknown_interval', $now))->toBeNull();
    expect(JmLib::getInterval('all', $now))->toBeNull();

    // Valid name + valid return_only key
    expect(JmLib::getInterval('today', $now, 'from'))->toBe(strtotime('2023-10-26 00:00:00'));

    // Valid name + unknown return_only key returns the full array
    $out = JmLib::getInterval('today', $now, 'nonexistent');
    expect($out)->toBeArray();
    expect($out['from'])->toBe(strtotime('2023-10-26 00:00:00'));
});

test('getInterval nextmonth does not roll over on month-end dates', function () {
    // Jan 31: strtotime('+1 month') gives Mar 3 (bug); anchored on Jan 1 -> Feb 1 (fix)
    $nowJan31 = strtotime('2024-01-31 15:00:00');
    $next = JmLib::getInterval('nextmonth', $nowJan31);
    expect($next['from'])->toBe(strtotime('2024-02-01 00:00:00'));
    expect($next['till'])->toBe(strtotime('2024-02-29 23:59:59')); // 2024 is a leap year

    // Mar 31: anchored on Mar 1 -> Apr 1
    $nowMar31 = strtotime('2024-03-31 15:00:00');
    $next2 = JmLib::getInterval('nextmonth', $nowMar31);
    expect($next2['from'])->toBe(strtotime('2024-04-01 00:00:00'));
    expect($next2['till'])->toBe(strtotime('2024-04-30 23:59:59'));
});

test('getInterval lastmonth does not roll over and uses $now not today', function () {
    // Mar 31: strtotime('-1 month') gives Mar 3 (bug); anchored on Mar 1 -> Feb 1 (fix)
    $nowMar31 = strtotime('2024-03-31 15:00:00');
    $last = JmLib::getInterval('lastmonth', $nowMar31);
    expect($last['from'])->toBe(strtotime('2024-02-01 00:00:00'));
    expect($last['till'])->toBe(strtotime('2024-02-29 23:59:59')); // 2024 is a leap year

    // Explicit past $now must not consult today's date
    $nowOct = strtotime('2023-10-26 15:00:00');
    $lastOct = JmLib::getInterval('lastmonth', $nowOct);
    expect($lastOct['from'])->toBe(strtotime('2023-09-01 00:00:00'));
    expect($lastOct['till'])->toBe(strtotime('2023-09-30 23:59:59'));
});

# countdays()
test('countdays returns expected number of days between 2 unix timestamps', function () {
    expect(JmLib::countdays(strtotime('2023-10-01 10:00:00'), strtotime('2023-10-05 09:00:00')))->toBe(3);
    expect(JmLib::countdays(strtotime('2023-10-01 23:59:59'), strtotime('2023-10-02 00:00:01')))->toBe(0);
    expect(JmLib::countdays(strtotime('2023-10-01 20:00:00'), strtotime('2023-10-02 21:00:00')))->toBe(1);
    expect(JmLib::countdays(strtotime('2023-10-01 00:00:00'), strtotime('2023-10-01 23:59:59')))->toBe(0);
    expect(JmLib::countdays(strtotime('2023-10-05 00:00:00'), strtotime('2023-10-01 00:00:00')))->toBe(4);
    expect(JmLib::countdays(0, strtotime('2023-10-01 00:00:00')))->toBe(0);
    expect(JmLib::countdays(strtotime('2023-10-01 00:00:00'), 0))->toBe(0);
});

# filemtimeRemote()
test('filemtimeRemote returns timestamp on success', function () {
    global $mock_curl_exec_result, $mock_curl_info_filetime, $mock_curl_url;

    $mock_curl_exec_result = true;
    $mock_curl_info_filetime = 1234567890;

    $url = 'http://example.com/image.jpg';
    $result = JmLib::filemtimeRemote($url);

    expect($result)->toBe(1234567890);
    expect($mock_curl_url)->toBe($url);

    // Test caching (mock would return failure, but cache should return success)
    $mock_curl_exec_result = false;
    expect(JmLib::filemtimeRemote($url))->toBe(1234567890);
});

test('filemtimeRemote returns false on failure', function () {
    global $mock_curl_exec_result;
    $mock_curl_exec_result = false;

    expect(JmLib::filemtimeRemote('http://fail.com'))->toBe(false);
});

test('filemtimeRemote prevents SSRF by rejecting non-http(s) schemes', function () {
    expect(JmLib::filemtimeRemote('file:///etc/passwd'))->toBe(false);
    expect(JmLib::filemtimeRemote('ftp://example.com/file.txt'))->toBe(false);
    expect(JmLib::filemtimeRemote('gopher://example.com'))->toBe(false);
    expect(JmLib::filemtimeRemote('dict://example.com'))->toBe(false);
    expect(JmLib::filemtimeRemote('ldap://example.com'))->toBe(false);
});

test('filemtimeRemote prevents SSRF by rejecting local network requests', function () {
    expect(JmLib::filemtimeRemote('http://localhost'))->toBe(false);
    expect(JmLib::filemtimeRemote('http://127.0.0.1'))->toBe(false);
    expect(JmLib::filemtimeRemote('http://192.168.1.1'))->toBe(false);
    expect(JmLib::filemtimeRemote('http://10.0.0.1'))->toBe(false);
    expect(JmLib::filemtimeRemote('http://169.254.169.254'))->toBe(false);
});

# createCalendar()
test('createCalendar builds a week grid with correct offsets', function () {
    // November 2023 starts on a Wednesday (weekday 3)
    $calendar = JmLib::createCalendar(11, 2023);

    expect($calendar[0][1])->toBe(false);
    expect($calendar[0][2])->toBe(false);
    expect($calendar[0][3])->toBe(mktime(12, 0, 0, 11, 1, 2023));
    expect($calendar[1][1])->toBe(mktime(12, 0, 0, 11, 6, 2023));
});

test('createCalendar fills leading/trailing days when requested', function () {
    $calendar = JmLib::createCalendar(11, 2023, true);

    // Leading: Nov 1 is Wed (slot 3), so Mon/Tue = Oct 30/31
    expect($calendar[0][1])->toBe(strtotime('-2 days', mktime(12, 0, 0, 11, 1, 2023)));
    expect($calendar[0][2])->toBe(strtotime('-1 days', mktime(12, 0, 0, 11, 1, 2023)));

    // Trailing: Nov 30 is Thu (slot 4); Fri/Sat/Sun must be Dec 1/2/3
    $lastWeek = array_key_last($calendar);
    expect($calendar[$lastWeek][5])->toBe(mktime(12, 0, 0, 12, 1, 2023));
    expect($calendar[$lastWeek][6])->toBe(mktime(12, 0, 0, 12, 2, 2023));
    expect($calendar[$lastWeek][7])->toBe(mktime(12, 0, 0, 12, 3, 2023));
});

test('createCalendar returns day numbers when return format is day', function () {
    $calendar = JmLib::createCalendar(11, 2023, false, 'day');

    expect($calendar[0][1])->toBe(false);
    expect($calendar[0][3])->toBe('1');
});

# datetimeBoundary()
test('datetimeBoundary returns correct start/end boundaries per level', function () {
    $ts = strtotime('2023-06-15 14:35:20');

    expect(JmLib::datetimeBoundary('hour', $ts, false))->toBe(mktime(14, 0, 0, 6, 15, 2023));
    expect(JmLib::datetimeBoundary('hour', $ts, true))->toBe(mktime(14, 59, 59, 6, 15, 2023));

    expect(JmLib::datetimeBoundary('day', $ts, false))->toBe(mktime(0, 0, 0, 6, 15, 2023));
    expect(JmLib::datetimeBoundary('day', $ts, true))->toBe(mktime(23, 59, 59, 6, 15, 2023));

    expect(JmLib::datetimeBoundary('month', $ts, false))->toBe(mktime(0, 0, 0, 6, 1, 2023));
    expect(JmLib::datetimeBoundary('month', $ts, true))->toBe(mktime(23, 59, 59, 6, 30, 2023));

    expect(JmLib::datetimeBoundary('year', $ts, false))->toBe(mktime(0, 0, 0, 1, 1, 2023));
    expect(JmLib::datetimeBoundary('year', $ts, true))->toBe(mktime(23, 59, 59, 12, 31, 2023));

    expect(JmLib::datetimeBoundary('unknown', $ts))->toBeNull();
});

# doubleImplode()
test('doubleImplode joins flat and nested arrays with the right separators', function () {
    expect(JmLib::doubleImplode(',', ';', ['a', 'b', 'c']))->toBe('a,b,c');
    expect(JmLib::doubleImplode(',', ';', [['a', 'b'], ['c', 'd']]))->toBe('a,b;c,d');
    expect(JmLib::doubleImplode(',', ';', ['x', ['a', 'b']]))->toBe('x;a,b');
    expect(JmLib::doubleImplode(',', ';', 'hello'))->toBe('hello');
});

# getDir()
test('getDir lists directory entries and returns false for missing dirs', function () {
    $tmp = sys_get_temp_dir() . '/jmlib_getdir_' . uniqid();
    mkdir($tmp);
    file_put_contents($tmp . '/a.txt', 'x');
    file_put_contents($tmp . '/b.txt', 'y');

    $entries = JmLib::getDir($tmp);
    expect($entries)->toBeArray();
    expect(in_array('a.txt', $entries))->toBe(true);
    expect(in_array('b.txt', $entries))->toBe(true);

    JmLib::rmdirr($tmp);

    // opendir() emits E_WARNING for missing paths; install a no-op handler so
    // Xdebug cannot intercept the warning before @ suppression in getDir() does.
    set_error_handler(fn() => true, E_WARNING);
    $result = JmLib::getDir($tmp . '_missing');
    restore_error_handler();

    expect($result)->toBe(false);
});


# leastSquaresFittingLogarithmic()
test('leastSquaresFittingLogarithmic reconstructs an exact logarithmic model', function () {
    $a = 2.0;
    $b = 3.0;
    $input = [];
    for ($i = 1; $i <= 6; $i++) {
        $input[] = $a + $b * log($i);
    }

    $fitted = JmLib::leastSquaresFittingLogarithmic($input);

    expect($fitted)->toBeArray();
    foreach ($input as $key => $value) {
        expect(round($fitted[$key], 6))->toBe(round($value, 6));
    }
});

test('leastSquaresFittingLogarithmic returns null for invalid input', function () {
    expect(JmLib::leastSquaresFittingLogarithmic('not-an-array'))->toBeNull();
    expect(JmLib::leastSquaresFittingLogarithmic([]))->toBeNull();
    expect(JmLib::leastSquaresFittingLogarithmic([5]))->toBeNull();
});

# movingAverage()
test('movingAverage computes a trailing average with the same element count', function () {
    $data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $result = JmLib::movingAverage($data, 3, true);

    expect($result)->toBe([1.0, 1.5, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0]);
});

test('movingAverage chunks data into averaged buckets when sameCount is false', function () {
    $data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $result = JmLib::movingAverage($data, 3, false);

    expect($result)->toBe([2, 5, 8, 10]);
});

test('movingAverage returns input unchanged or null for edge cases', function () {
    expect(JmLib::movingAverage('not-an-array'))->toBeNull();
    expect(JmLib::movingAverage(['a' => 1, 'b' => 2], 5))->toBe(['a' => 1, 'b' => 2]);
});

# oneFromArray()
test('oneFromArray plucks a column while preserving original keys', function () {
    $data = [
        'first' => ['id' => 1, 'name' => 'Alice'],
        'second' => ['id' => 2, 'name' => 'Bob'],
    ];

    expect(JmLib::oneFromArray($data, 'name'))->toBe(['first' => 'Alice', 'second' => 'Bob']);
});

test('oneFromArray returns null for a missing key on an entry and for invalid input', function () {
    $data = [['id' => 1], ['id' => 2, 'name' => 'Bob']];

    expect(JmLib::oneFromArray($data, 'name'))->toBe([null, 'Bob']);
    expect(JmLib::oneFromArray('not-an-array', 'name'))->toBeNull();
    expect(JmLib::oneFromArray($data, ''))->toBeNull();
});

test('oneFromArray accepts integer key 0 as a valid column key', function () {
    $data = [['Alice', 'admin'], ['Bob', 'user']];

    expect(JmLib::oneFromArray($data, 0))->toBe(['Alice', 'Bob']);
    expect(JmLib::oneFromArray($data, null))->toBeNull();
});
