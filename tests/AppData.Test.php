<?php

namespace Janmensik\Jmlib\Tests;

use Janmensik\Jmlib\AppData;

beforeEach(function () {
    // Reset instance using reflection because it's a singleton
    $reflectionClass = new \ReflectionClass(AppData::class);
    $reflectionProperty = $reflectionClass->getProperty('instance');
    $reflectionProperty->setAccessible(true);
    // Fixing deprecation warning in PHP 8.3+: use two arguments or just pass null to null instance
    $reflectionProperty->setValue(null, null);

    $this->appData = AppData::getInstance();
    $this->appData->FILTERS = [];
    $_GET = [];
});

test('initiateFilters returns true when page is null', function () {
    expect($this->appData->initiateFilters())->toBeTrue();
});

test('initiateFilters returns empty array when page is not in filters', function () {
    expect($this->appData->initiateFilters('home'))->toBeArray()->toBeEmpty();
});

test('initiateFilters sets $_GET from filters when missing from $_GET', function () {
    $this->appData->FILTERS['home'] = [
        'sort' => 'desc',
        'limit' => 10
    ];

    $result = $this->appData->initiateFilters('home');

    expect($_GET['sort'])->toBe('desc');
    expect($_GET['limit'])->toBe(10);
    expect($result)->toBe([
        'sort' => 'desc',
        'limit' => 10
    ]);
});

test('initiateFilters updates filters from $_GET when present and not empty', function () {
    $this->appData->FILTERS['home'] = [
        'sort' => 'desc',
        'limit' => 10
    ];

    $_GET['sort'] = 'asc';
    $_GET['limit'] = 20;

    $result = $this->appData->initiateFilters('home');

    expect($this->appData->FILTERS['home']['sort'])->toBe('asc');
    expect($this->appData->FILTERS['home']['limit'])->toBe(20);
    expect($result)->toBe([
        'sort' => 'asc',
        'limit' => 20
    ]);
});

test('initiateFilters ignores empty string values in $_GET', function () {
    $this->appData->FILTERS['home'] = [
        'sort' => 'desc'
    ];

    $_GET['sort'] = '';

    $result = $this->appData->initiateFilters('home');

    // Filter value should remain unchanged because $_GET['sort'] is empty string
    expect($this->appData->FILTERS['home']['sort'])->toBe('desc');
    expect($result)->toBe([
        'sort' => 'desc'
    ]);
});
