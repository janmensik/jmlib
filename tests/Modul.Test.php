<?php

namespace Janmensik\Jmlib;

use Janmensik\Jmlib\Modul;
use Janmensik\Jmlib\Database;

// --- Mocks ---

// Mock mysqli_real_escape_string for sanitize() within this namespace
function mysqli_real_escape_string($link, $string) {
    return "escaped_" . $string;
}

// Mock Database class
class MockDatabase extends Database {
    public $queries = [];
    public $rows = []; // Array of arrays to return in getRow
    public $affected_rows = 0;
    public $insert_id = 0;
    public $rows_count = 0;

    public function __construct() {
        // Skip parent constructor to avoid connection logic
        $this->db = new \stdClass();
        $this->messages = [];
    }

    public function query($query, $query_name = '') {
        $this->queries[] = $query;
        return true;
    }

    public function getRow($result = null) {
        if (!empty($this->rows)) {
            return array_shift($this->rows);
        }
        return false;
    }

    public function getRowsCount() {
        return $this->rows_count;
    }

    public function getNumAffected() {
        return $this->affected_rows;
    }

    public function getId() {
        return $this->insert_id;
    }
}

// Helper class to expose protected properties of Modul
class TestModul extends Modul {
    public function setSqlBase($sql) { $this->sql_base = $sql; }
    public function setSqlTable($table) { $this->sql_table = $table; }
    public function setSqlInsert($sql) { $this->sql_insert = $sql; }
    public function setSqlUpdate($sql) { $this->sql_update = $sql; }
    public function setIdFormat($id) { $this->id_format = $id; }
    public function setFulltextColumns($cols) { $this->fulltext_columns = $cols; }
    public function setOrder($order) { $this->order = $order; }
    public function setSqlGroupTotal($sql) { $this->sql_group_total = $sql; }
}

// --- Tests ---

test('constructor assigns database', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);
    expect($modul->DB)->toBe($db);
});

test('getLimit and setLimit', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    expect($modul->getLimit())->toBe(20); // Default

    $modul->setLimit(50);
    expect($modul->getLimit())->toBe(50);

    $modul->setLimit(-5); // Invalid
    expect($modul->getLimit())->toBe(50); // Should not change
});

test('get executes query and returns data', function () {
    $db = new MockDatabase();
    $db->rows = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');

    $data = $modul->get();

    expect($data)->toBe([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ]);

    expect($db->queries[0])->toContain('SELECT * FROM users');

    // Check cache population
    expect($modul->cache)->toHaveKey(1);
    expect($modul->cache[1])->toBe(['id' => 1, 'name' => 'Alice']);
});

test('get handles SQL_CALC_FOUND_ROWS', function () {
    $db = new MockDatabase();
    $db->rows_count = 42;

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT SQL_CALC_FOUND_ROWS * FROM users');

    $modul->get();

    expect($modul->cache_total)->toBe(42);
});

test('get applies where, order and limit', function () {
    $db = new MockDatabase();

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');

    $modul->get('active=1', '1', 5);

    $sql = $db->queries[0];
    expect($sql)->toContain('WHERE active=1');
    expect($sql)->toContain('ORDER BY 1');
    expect($sql)->toContain('LIMIT 5');
});

test('set performs insert', function () {
    $db = new MockDatabase();
    $db->affected_rows = 1;
    $db->insert_id = 123;

    $modul = new TestModul($db);
    $modul->setSqlInsert('INSERT INTO users');
    $modul->setSqlTable('users');

    $id = $modul->set(['name' => 'Alice']);

    expect($id)->toBe(123);

    $sql = $db->queries[0];
    expect($sql)->toContain('INSERT INTO users');
    expect($sql)->toContain('name');
    expect($sql)->toContain('Alice');
});

test('set performs update', function () {
    $db = new MockDatabase();
    $db->affected_rows = 1;

    $modul = new TestModul($db);
    $modul->setSqlUpdate('UPDATE users');
    $modul->setSqlTable('users');
    $modul->setIdFormat('id');

    $result = $modul->set(['name' => 'Bob'], 10);

    expect($result)->toBe(10);

    $sql = $db->queries[0];
    expect($sql)->toContain('UPDATE users SET');
    expect($sql)->toContain('name = Bob');
    expect($sql)->toContain('WHERE users.id = "10"');
});

test('sanitize uses mysqli_real_escape_string', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    $result = $modul->sanitize("test'string");

    expect($result)->toBe("escaped_test'string");
});

test('sanitize handles types', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    expect($modul->sanitize('123', 'int'))->toBe('123');
    expect($modul->sanitize('12.34', 'float'))->toBe(12.34);
    expect($modul->sanitize('123,4', 'float'))->toBe(123.4);
    expect($modul->sanitize('test@example.com', 'email'))->toBe('test@example.com');
    expect($modul->sanitize('invalid-email', 'email'))->toBe(false);
});

test('createFulltextSubquery generates correct SQL', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);
    $modul->setFulltextColumns(['col1', 'col2']);

    $sql = $modul->createFulltextSubquery('hello world');

    // Expected: (CONCAT_WS(" ",CAST(col1 AS CHAR),CAST(col2 AS CHAR)) LIKE "%hello%" AND CONCAT_WS(" ",CAST(col1 AS CHAR),CAST(col2 AS CHAR)) LIKE "%world%")
    expect($sql)->toContain('CONCAT_WS');
    expect($sql)->toContain('LIKE "%hello%"');
    expect($sql)->toContain('LIKE "%world%"');
    expect($sql)->toContain('AND');
});

test('findId returns id', function () {
    $db = new MockDatabase();
    $db->rows = [['id' => 99]];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');

    $id = $modul->findId('email="test@test.com"');

    expect($id)->toBe(99);
});

test('getGroupTotal returns row', function () {
    $db = new MockDatabase();
    $db->rows = [['total' => 100]];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');
    $modul->setSqlGroupTotal('SELECT COUNT(*) as total');
    $modul->setSqlTable('users');

    $result = $modul->getGroupTotal();

    expect($result)->toBe(['total' => 100]);
    expect($db->queries[0])->toContain('SELECT COUNT(*) as total');
});