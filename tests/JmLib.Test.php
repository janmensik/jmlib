<?php

use Janmensik\Jmlib\JmLib;

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