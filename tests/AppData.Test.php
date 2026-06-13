<?php

namespace Janmensik\Jmlib;

beforeEach(function () {
    // Backup and clear session
    $this->backupSession = $_SESSION ?? [];
    $_SESSION = [];

    // Reset singleton state (since it's a singleton, we need to reset its properties)
    $appData = AppData::getInstance();
    $appData->MESSAGES = [];
    $appData->FILTERS = [];
    $appData->data = [];
});

afterEach(function () {
    $_SESSION = $this->backupSession;
});

test('loadMessages returns false if MESSAGES is already populated', function () {
    $appData = AppData::getInstance();
    $appData->MESSAGES = ['some' => 'message'];

    $result = $appData->loadMessages();

    expect($result)->toBeFalse();
});

test('loadMessages returns null if $_SESSION[\'messages\'] is not set', function () {
    $appData = AppData::getInstance();

    // Ensure it's not set
    unset($_SESSION['messages']);

    $result = $appData->loadMessages();

    expect($result)->toBeNull();
});

test('loadMessages returns null if $_SESSION[\'messages\'] is not an array', function () {
    $appData = AppData::getInstance();

    $_SESSION['messages'] = 'not an array';

    $result = $appData->loadMessages();

    expect($result)->toBeNull();
});

test('loadMessages populates MESSAGES and returns true on success', function () {
    $appData = AppData::getInstance();

    $messages = ['success' => 'Operation completed'];
    $_SESSION['messages'] = $messages;

    $result = $appData->loadMessages();

    expect($result)->toBeTrue();
    expect($appData->MESSAGES)->toBe($messages);
});
