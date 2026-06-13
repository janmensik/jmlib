<?php

namespace Janmensik\Jmlib;

beforeEach(function () {
    $this->appData = AppData::getInstance();
    $this->appData->FILTERS = [];

    // Backup GET
    if (!isset($this->backupGet)) {
        $this->backupGet = $_GET;
    }
    $_GET = [];
});

afterEach(function () {
    $_GET = $this->backupGet;
});

test('initiateFilters returns true when page is null', function () {
    $result = $this->appData->initiateFilters();
    expect($result)->toBeTrue();
});

test('initiateFilters returns empty array when FILTERS for page is not set', function () {
    $result = $this->appData->initiateFilters('users');
    expect($result)->toBeArray()->toBeEmpty();
});

test('initiateFilters sets missing GET parameters from FILTERS', function () {
    $this->appData->FILTERS['users'] = [
        'status' => 'active',
        'role' => 'admin'
    ];

    $_GET['status'] = 'pending';

    $result = $this->appData->initiateFilters('users');

    // Existing GET parameter is untouched
    expect($_GET['status'])->toBe('pending');

    // Missing GET parameter is set from FILTERS
    expect($_GET['role'])->toBe('admin');

    // FILTERS 'status' is updated with GET 'status'
    expect($result['status'])->toBe('pending');

    // FILTERS 'role' remains the same as there was no GET parameter to override it
    expect($result['role'])->toBe('admin');
});

test('initiateFilters updates FILTERS from GET parameters', function () {
    $this->appData->FILTERS['users'] = [
        'status' => 'active',
    ];

    $_GET['status'] = 'pending';

    $result = $this->appData->initiateFilters('users');

    expect($result['status'])->toBe('pending');
    expect($this->appData->FILTERS['users']['status'])->toBe('pending');
});

test('initiateFilters does not update FILTERS from empty GET parameters', function () {
    $this->appData->FILTERS['orders'] = [
        'status' => 'new'
    ];

    $_GET['status'] = '';

    $result = $this->appData->initiateFilters('orders');

    expect($result['status'])->toBe('new');
    expect($this->appData->FILTERS['orders']['status'])->toBe('new');
});
