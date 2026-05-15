<?php

use Janmensik\Jmlib\UrlParameters;

test('getLink prevents XSS vulnerability', function () {
    $url = new UrlParameters('https://example.com/"><script>alert(1)</script>');
    $link = $url->getLink('click here');

    expect($link)->toBe('<a href="https://example.com/&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">click here</a>');

    // ensure no unescaped tags
    expect(strpos($link, '<script>'))->toBeFalse();
});

test('getLink preserves valid query parameters', function () {
    $url = new UrlParameters('https://example.com/page?foo=bar&baz=qux');
    $link = $url->getLink('click here', 'class="btn"');

    expect($link)->toBe('<a href="https://example.com/page?baz=qux&amp;foo=bar" class="btn">click here</a>');
});
