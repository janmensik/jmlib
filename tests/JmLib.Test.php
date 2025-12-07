<?php

use Janmensik\Jmlib\JmLib;

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
test('getip returns the appropriate server IP sources', function () {
    $_SERVER['HTTP_CLIENT_IP'] = '';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    expect(JmLib::getip())->toBe('127.0.0.1');

    $_SERVER['HTTP_CLIENT_IP'] = '10.0.0.1';
    expect(JmLib::getip())->toBe('10.0.0.1');
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
});