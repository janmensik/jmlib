<?php
namespace Janmensik\Jmlib;

test('it sets url and parameters correctly via constructor', function () {
    $urlParams = new UrlParameters('http://example.com/test?param1=value1', 'param2=value2');

    expect($urlParams->getBasename())->toBe('http://example.com/test');
    expect($urlParams->getParameter('param1'))->toBe('value1');
    expect($urlParams->getParameter('param2'))->toBe('value2');
});

test('it sets url and parameters correctly via setUrl', function () {
    $urlParams = new UrlParameters();
    $urlParams->setUrl('http://example.com/test?param1=value1', 'param2=value2');

    expect($urlParams->getBasename())->toBe('http://example.com/test');
    expect($urlParams->getParameter('param1'))->toBe('value1');
    expect($urlParams->getParameter('param2'))->toBe('value2');
});

test('it handles parameters ending in [] as arrays', function () {
    $urlParams = new UrlParameters('http://example.com/test?arr[]=value1&arr[]=value2');

    expect($urlParams->getParameter('arr[]'))->toBeArray();
    expect($urlParams->getParameter('arr[]'))->toHaveCount(2);
    expect($urlParams->getParameter('arr[]')[0])->toBe('value1');
    expect($urlParams->getParameter('arr[]')[1])->toBe('value2');
});

test('it can set a parameter', function () {
    $urlParams = new UrlParameters('http://example.com/test');
    $urlParams->setParameter('testParam', 'testValue');

    expect($urlParams->getParameter('testParam'))->toBe('testValue');
    expect($urlParams->hasParameter('testParam'))->toBeTrue();
});

test('it can remove a parameter by setting value to false', function () {
    $urlParams = new UrlParameters('http://example.com/test?testParam=testValue');
    expect($urlParams->hasParameter('testParam'))->toBeTrue();

    $urlParams->setParameter('testParam', false);

    expect($urlParams->hasParameter('testParam'))->toBeFalse();
    expect($urlParams->getParameter('testParam'))->toBeNull();
});

test('it can add an item to an array parameter', function () {
    $urlParams = new UrlParameters('http://example.com/test?arr[]=value1');
    $urlParams->setParameter('arr[]', 'value2');

    $arr = $urlParams->getParameter('arr[]');
    expect($arr)->toBeArray();
    expect($arr)->toHaveCount(2);
    expect($arr[0])->toBe('value1');
    expect($arr[1])->toBe('value2');
});

test('it prevents duplicate items in array parameters', function () {
    $urlParams = new UrlParameters('http://example.com/test?arr[]=value1');
    $urlParams->setParameter('arr[]', 'value1'); // Duplicate

    $arr = $urlParams->getParameter('arr[]');
    expect($arr)->toBeArray();
    expect($arr)->toHaveCount(1);
    expect($arr[0])->toBe('value1');
});

test('it builds the correct URL', function () {
    $urlParams = new UrlParameters('http://example.com/test?b=2&a=1');
    $urlParams->setParameter('c[]', '3');
    $urlParams->setParameter('c[]', '4');

    // the parameters should be sorted alphabetically by key
    // a=1, b=2, c[]=3, c[]=4
    $expectedUrl = 'http://example.com/test?a=1&b=2&c[]=3&c[]=4';
    expect($urlParams->getUrl())->toBe($expectedUrl);
});

test('it handles getLink correctly', function () {
    $urlParams = new UrlParameters('http://example.com/test?param1=value1');

    $link = $urlParams->getLink('Click Here');
    expect($link)->toBe('<a href="http://example.com/test?param1=value1">Click Here</a>');

    $linkWithOptions = $urlParams->getLink('Click Here', 'class="my-class"');
    expect($linkWithOptions)->toBe('<a href="http://example.com/test?param1=value1" class="my-class">Click Here</a>');
});

test('it handles fromCurrent with full url', function () {
    $backupServer = $_SERVER;
    $_SERVER['PHP_SELF'] = '/script.php';
    $_SERVER['SCRIPT_NAME'] = '/script.php';
    $_SERVER['QUERY_STRING'] = 'a=1&b=2';

    $urlParams = new UrlParameters();
    $urlParams->fromCurrent(true);

    expect($urlParams->getBasename())->toBe('/script.php');
    expect($urlParams->getParameter('a'))->toBe('1');
    expect($urlParams->getParameter('b'))->toBe('2');
    $_SERVER = $backupServer;
});

test('it handles fromCurrent without full url', function () {
    $backupServer = $_SERVER;
    $_SERVER['PHP_SELF'] = '/script.php';
    $_SERVER['SCRIPT_NAME'] = '/script.php';
    $_SERVER['QUERY_STRING'] = 'a=1&b=2';

    $urlParams = new UrlParameters();
    $urlParams->fromCurrent(false);

    expect($urlParams->getBasename())->toBe('/script.php');
    expect($urlParams->hasParameter('a'))->toBeFalse();
    expect($urlParams->hasParameter('b'))->toBeFalse();
    $_SERVER = $backupServer;
});

test('it handles fromCurrentCoolUri with query string in REQUEST_URI', function () {
    $backupServer = $_SERVER;
    $_SERVER['REQUEST_URI'] = '/cool/uri?c=3';
    $_SERVER['QUERY_STRING'] = 'a=1&b=2';

    $urlParams = new UrlParameters();
    $urlParams->fromCurrentCoolUri(true);

    expect($urlParams->getBasename())->toBe('/cool/uri');
    expect($urlParams->getParameter('a'))->toBe('1');
    expect($urlParams->getParameter('b'))->toBe('2');
    expect($urlParams->hasParameter('c'))->toBeFalse(); // parsed from QUERY_STRING, not REQUEST_URI query part
    $_SERVER = $backupServer;
});

test('it handles setBasename', function () {
    $urlParams = new UrlParameters('http://example.com/test?a=1');
    $urlParams->setBasename('http://new.com/path');

    expect($urlParams->getBasename())->toBe('http://new.com/path');
    expect($urlParams->getUrl())->toBe('http://new.com/path?a=1');
});
