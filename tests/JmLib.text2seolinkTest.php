<?php

use Janmensik\Jmlib\JmLib;

test('converts basic strings to SEO links', function () {
    expect(JmLib::text2seolink('Hello World'))->toBe('hello-world');
});

test('handles strings with diacritics', function () {
    expect(JmLib::text2seolink('Příliš žluťoučký kůň úpěl ďábelské ódy'))->toBe('prilis-zlutoucky-kun-upel-dabelske-ody');
});

test('removes special characters', function () {
    expect(JmLib::text2seolink('A string with!@#$%^&*() special chars'))->toBe('a-string-with-special-chars');
});

test('collapses multiple hyphens', function () {
    expect(JmLib::text2seolink('multiple---spaces and --- hyphens'))->toBe('multiple-spaces-and-hyphens');
});

test('trims leading and trailing hyphens', function () {
    expect(JmLib::text2seolink('---leading and trailing---'))->toBe('leading-and-trailing');
    expect(JmLib::text2seolink('  spaces and hyphens  -- '))->toBe('spaces-and-hyphens');
});

test('handles mixed case strings', function () {
    expect(JmLib::text2seolink('This Is a MiXeD CaSe String'))->toBe('this-is-a-mixed-case-string');
});

test('handles strings with numbers', function () {
    expect(JmLib::text2seolink('Test 123 with 456 numbers'))->toBe('test-123-with-456-numbers');
});

test('returns an empty string for input with only special characters', function () {
    expect(JmLib::text2seolink('!@#$%^&*()_=+'))->toBe('');
});

test('returns an empty string for empty input', function () {
    expect(JmLib::text2seolink(''))->toBe('');
});

test('does not change an already SEO-friendly string', function () {
    expect(JmLib::text2seolink('this-is-already-a-seo-link'))->toBe('this-is-already-a-seo-link');
});