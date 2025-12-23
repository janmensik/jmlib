<?php

namespace Janmensik\Jmlib;

use Janmensik\Jmlib\Database;

// --- Mocks for mysqli functions ---
// These functions override the global PHP functions within this namespace to allow testing without a real database.

$mock_query_results = [];
$mock_last_query = null;
$mock_affected_rows = 0;
$mock_insert_id = 0;

function mysqli_query($link, $query) {
    global $mock_query_results, $mock_last_query;
    $mock_last_query = $query;
    if (!empty($mock_query_results)) {
        return array_shift($mock_query_results);
    }
    return true;
}

function mysqli_num_rows($result) {
    if ($result instanceof MockResult) {
        return $result->num_rows;
    }
    return 0;
}

function mysqli_affected_rows($link) {
    global $mock_affected_rows;
    return $mock_affected_rows;
}

function mysqli_fetch_assoc($result) {
    if ($result instanceof MockResult) {
        return $result->fetch_assoc();
    }
    return null;
}

function mysqli_fetch_array($result) {
    if ($result instanceof MockResult) {
        return $result->fetch_array();
    }
    return null;
}

function mysqli_insert_id($link) {
    global $mock_insert_id;
    return $mock_insert_id;
}

function mysqli_free_result($result) {
    return true;
}

// Helper class to simulate a mysqli_result object
class MockResult {
    private $rows;
    public $num_rows;

    public function __construct(array $rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        $row = current($this->rows);
        next($this->rows);
        return $row ?: null;
    }

    public function fetch_array() {
        $row = current($this->rows);
        next($this->rows);
        if ($row) {
             // mysqli_fetch_array returns both numeric and assoc indices
             return array_merge(array_values($row), $row);
        }
        return null;
    }
}

// --- Tests ---

beforeEach(function () {
    global $mock_query_results, $mock_last_query, $mock_affected_rows, $mock_insert_id;
    $mock_query_results = [];
    $mock_last_query = null;
    $mock_affected_rows = 0;
    $mock_insert_id = 0;
});

test('constructor initializes properties', function () {
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    expect($db->server)->toBe('localhost');
    expect($db->database)->toBe('mydb');
    expect($db->user)->toBe('user');
    expect($db->password)->toBe('pass');
    expect($db->messages['total_queries'])->toBe(0);
});

test('query executes and logs query', function () {
    global $mock_last_query;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass(); // Inject mock connection

    $db->query('SELECT * FROM users', 'get users');

    expect($mock_last_query)->toBe('SELECT * FROM users');
    expect($db->messages['total_queries'])->toBe(1);
    expect($db->messages['queries'][0]['name'])->toBe('get users');
});

test('getRow returns associative array', function () {
    global $mock_query_results;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    $mock_query_results[] = new MockResult([
        ['id' => 1, 'name' => 'Alice']
    ]);

    $db->query('SELECT * FROM users');
    $row = $db->getRow();

    expect($row)->toBe(['id' => 1, 'name' => 'Alice']);
});

test('getAllRows returns all rows', function () {
    global $mock_query_results;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    $mock_query_results[] = new MockResult([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ]);

    $db->query('SELECT * FROM users');
    $rows = $db->getAllRows();

    expect($rows)->toBe([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ]);
});

test('getAllRows with index_by returns indexed array', function () {
    global $mock_query_results;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    $mock_query_results[] = new MockResult([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ]);

    $db->query('SELECT * FROM users');
    $rows = $db->getAllRows(null, 'id');

    expect($rows)->toBe([
        1 => ['id' => 1, 'name' => 'Alice'],
        2 => ['id' => 2, 'name' => 'Bob']
    ]);
});

test('getResult returns first column of first row', function () {
    global $mock_query_results;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    // getResult uses mysqli_fetch_array and accesses index 0
    $mock_query_results[] = new MockResult([
        ['COUNT(*)' => 42]
    ]);

    $db->query('SELECT COUNT(*) FROM users');
    $result = $db->getResult();

    expect($result)->toBe(42);
});

test('getRowsCount returns FOUND_ROWS', function () {
    global $mock_query_results;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    // getRowsCount calls query('SELECT FOUND_ROWS();')
    // We need to queue the result for that query
    $mock_query_results[] = new MockResult([
        [0 => 100] // FOUND_ROWS returns a single column
    ]);

    $count = $db->getRowsCount();

    expect($count)->toBe(100);
});

test('getNumAffected returns affected rows', function () {
    global $mock_affected_rows;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    $mock_affected_rows = 5;

    expect($db->getNumAffected())->toBe(5);
});

test('getId returns insert id', function () {
    global $mock_insert_id;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    $mock_insert_id = 123;

    expect($db->getId())->toBe(123);
});

test('numRows returns number of rows from result', function () {
    global $mock_query_results;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    $mock_query_results[] = new MockResult([
        ['id' => 1], ['id' => 2], ['id' => 3]
    ]);

    $db->query('SELECT * FROM users');
    expect($db->numRows())->toBe(3);
});

test('numRows falls back to affected rows if no result rows', function () {
    global $mock_query_results, $mock_affected_rows;
    $db = new Database('localhost', 'mydb', 'user', 'pass');
    $db->db = new \stdClass();

    // Empty result set (e.g. from UPDATE)
    $mock_query_results[] = new MockResult([]);

    $mock_affected_rows = 10;

    $db->query('UPDATE users SET x=1');
    expect($db->numRows())->toBe(10);
});