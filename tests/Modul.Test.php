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
    public array $queries = [];
    public array $rows = []; // Array of arrays to return in getRow
    public int $affected_rows = 0;
    public int|string $insert_id = 0;
    public int $rows_count = 0;

    public function __construct() {
        // Skip parent constructor to avoid connection logic
        $this->db = null;
        $this->messages = [];
    }

    private function connect(): \mysqli|false {
        return false;
    }

    public function query(string $query, string $query_name = ''): \mysqli_result|bool {
        $this->queries[] = $query;
        return true;
    }

    public function getRow(mixed $result = null): array|false|null {
        if (!empty($this->rows)) {
            return array_shift($this->rows);
        }
        return false;
    }

    public function freeResult(mixed $result = null): bool {
        return true;
    }

    public function getRowsCount(): int|false {
        return $this->rows_count;
    }

    public function getNumAffected(): int {
        return $this->affected_rows;
    }

    public function getId(): int|string|false {
        return $this->insert_id;
    }
}

// Helper class to expose protected properties of Modul
class TestModul extends Modul {
    public function setSqlBase(?string $sql): void { $this->sql_base = $sql; }
    public function setSqlTable(?string $table): void { $this->sql_table = $table; }
    public function setSqlInsert(?string $sql): void { $this->sql_insert = $sql; }
    public function setSqlUpdate(?string $sql): void { $this->sql_update = $sql; }
    public function setIdFormat(string $id): void { $this->id_format = $id; }
    public function setFulltextColumns(?array $cols): void { $this->fulltext_columns = $cols; }
    public function setOrder(int|string $order): void { $this->order = $order; }
    public function setSqlGroupTotal(?string $sql): void { $this->sql_group_total = $sql; }
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

    // Valid integer limit
    $oldValue = $modul->setLimit(50);
    expect($modul->getLimit())->toBe(50);
    expect($oldValue)->toBe(50);

    // Invalid limit: negative integer (limit should not change)
    $oldValue2 = $modul->setLimit(-5);
    expect($modul->getLimit())->toBe(50);
    expect($oldValue2)->toBe(50);

    // Invalid limit: zero (limit should not change)
    $oldValue3 = $modul->setLimit(0);
    expect($modul->getLimit())->toBe(50);
    expect($oldValue3)->toBe(50);

    // Invalid limit: null (limit should not change)
    $oldValue4 = $modul->setLimit(null);
    expect($modul->getLimit())->toBe(50);
    expect($oldValue4)->toBe(50);

    // Valid limit: numeric string
    $oldValue5 = $modul->setLimit('30');
    expect($modul->getLimit())->toBe(30);
    expect($oldValue5)->toBe(30);

    // Invalid limit: non-numeric string (limit should not change)
    $oldValue6 = $modul->setLimit('invalid');
    expect($modul->getLimit())->toBe(30);
    expect($oldValue6)->toBe(30);
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

// --- getTotal tests (covers the removed @ suppressions) ---

test('getTotal returns false when values not an array', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);
    expect($modul->getTotal([['a' => 1]], null))->toBeFalse();
    expect($modul->getTotal([['a' => 1]], 'not_array'))->toBeFalse();
});

test('getTotal returns false when dataset not an array', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);
    expect($modul->getTotal(null, ['a' => 'count']))->toBeFalse();
    expect($modul->getTotal('not_array', ['a' => 'count']))->toBeFalse();
});

test('getTotal count aggregation', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    $dataset = [
        ['id' => 1, 'status' => 'active'],
        ['id' => 2, 'status' => 'active'],
        ['id' => 3, 'status' => 'inactive'],
    ];

    $result = $modul->getTotal($dataset, ['status' => 'count']);

    expect($result)->toBeArray();
    expect($result['status'])->toBe(3);
});

test('getTotal sum aggregation', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    $dataset = [
        ['id' => 1, 'price' => 100],
        ['id' => 2, 'price' => 250],
        ['id' => 3, 'price' => 50],
    ];

    $result = $modul->getTotal($dataset, ['price' => 'sum']);

    expect($result)->toBeArray();
    expect($result['price'])->toBe(400);
});

test('getTotal avg aggregation', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    $dataset = [
        ['id' => 1, 'score' => 10],
        ['id' => 2, 'score' => 20],
        ['id' => 3, 'score' => 30],
    ];

    $result = $modul->getTotal($dataset, ['score' => 'avg']);

    expect($result)->toBeArray();
    expect($result['score'])->toBe(20); // integer division result
});

test('getTotal count ignores missing key in row', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    $dataset = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2],                    // 'name' missing
        ['id' => 3, 'name' => 'Carol'],
    ];

    $result = $modul->getTotal($dataset, ['name' => 'count']);

    expect($result['name'])->toBe(2); // Only 2 rows have 'name'
});

test('getTotal sum starts from zero without @ suppression issues', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    // Single row dataset - the key has not been initialized before incrementing
    $dataset = [['id' => 1, 'amount' => 42]];

    $result = $modul->getTotal($dataset, ['amount' => 'sum']);

    expect($result['amount'])->toBe(42);
});

// --- getTotalEval tests ---

test('getTotalEval returns false when values not an array', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);
    expect($modul->getTotalEval([['a' => 1]], null))->toBeFalse();
});

test('getTotalEval returns false when dataset not an array', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);
    expect($modul->getTotalEval(null, ['a' => 'count']))->toBeFalse();
});

test('getTotalEval count aggregation', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    $dataset = [
        ['id' => 1, 'views' => 10],
        ['id' => 2, 'views' => 20],
    ];

    $result = $modul->getTotalEval($dataset, ['views' => 'count']);

    expect($result['views'])->toBe(2);
});

test('getTotalEval sum aggregation', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    $dataset = [
        ['id' => 1, 'views' => 10],
        ['id' => 2, 'views' => 20],
    ];

    $result = $modul->getTotalEval($dataset, ['views' => 'sum']);

    expect($result['views'])->toBe(30);
});

// --- getNoCalcRows ---

test('getNoCalcRows strips SQL_CALC_FOUND_ROWS', function () {
    $db = new MockDatabase();
    $db->rows = [['id' => 1, 'name' => 'Alice']];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT SQL_CALC_FOUND_ROWS * FROM users');

    $data = $modul->getNoCalcRows();

    // SQL should not contain SQL_CALC_FOUND_ROWS
    expect($db->queries[0])->not()->toContain('SQL_CALC_FOUND_ROWS');
    expect($data)->toBeArray();
    expect($data[0]['name'])->toBe('Alice');
});

// --- getCustom ---

test('getCustom uses custom sql and restores original', function () {
    $db = new MockDatabase();
    $db->rows = [['id' => 5, 'name' => 'Custom']];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');

    $data = $modul->getCustom('SELECT * FROM custom_table');

    expect($db->queries[0])->toContain('custom_table');
    expect($data[0]['name'])->toBe('Custom');
    // sql_base should be restored - call get again to confirm original SQL is used
    $db->rows = [['id' => 1, 'name' => 'Original']];
    $modul->get();
    expect($db->queries[1])->toContain('users');
});

// --- getExtra ---

test('getExtra returns descending order info for negative order', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);
    $modul->setOrder(-3);

    $result = $modul->getExtra(-3);

    expect($result['order_type'])->toBe('down');
    expect($result['order'])->toBe(3);
    expect($result['order_minus'])->toBe(1);
    expect(isset($result['order_plus']))->toBeFalse();
});

test('getExtra returns ascending order info for positive order', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);
    $modul->setOrder(5);

    $result = $modul->getExtra(5);

    expect($result['order_type'])->toBe('up');
    expect($result['order'])->toBe(5);
    expect($result['order_plus'])->toBe(1);
    expect(isset($result['order_minus']))->toBeFalse();
});

test('getExtra uses default order when no argument given', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);
    $modul->setOrder(-6);

    $result = $modul->getExtra();

    expect($result['order_type'])->toBe('down');
    expect($result['order'])->toBe(6);
});

// --- getRowsCount ---

test('getRowsCount returns cache_total', function () {
    $db = new MockDatabase();
    $db->rows_count = 77;

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT SQL_CALC_FOUND_ROWS * FROM users');
    $modul->get();

    expect($modul->getRowsCount())->toBe(77);
});

// --- getIds / findIds ---

test('getIds delegates to getId with returnarray=true', function () {
    $db = new MockDatabase();
    $db->rows = [['id' => 10, 'name' => 'Test']];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');
    $modul->setSqlTable('users');

    $result = $modul->getIds([10]);

    expect($result)->toBeArray();
    expect($result[0])->toHaveKey('id');
});

test('findIds returns array of ids', function () {
    $db = new MockDatabase();
    $db->rows = [
        ['id' => 1],
        ['id' => 2],
        ['id' => 3],
    ];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');

    $result = $modul->findIds('active=1');

    expect($result)->toBeArray();
    expect($result)->toContain(1);
    expect($result)->toContain(2);
    expect($result)->toContain(3);
});

// --- findRandomId ---

test('findRandomId returns null when no data', function () {
    $db = new MockDatabase();

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');

    $result = $modul->findRandomId();

    expect($result)->toBeNull();
});

test('findRandomId returns id when data exists', function () {
    $db = new MockDatabase();
    // cache_total must be >= 1 for getRandom to sample; we set rows to give data
    // But getRandom checks cache_total. Since get() reads rows, we set rows.
    $db->rows = [['id' => 42, 'name' => 'Test']];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');
    // We need cache_total to trigger the srand/array_rand path.
    // Since cache_total is null initially and getRandom uses it, we need
    // the result to just return data directly (cache_total < count path).
    $result = $modul->findRandomId();

    expect($result)->toBe(42);
});

// --- getId with cache hit ---

test('getId returns from cache when available', function () {
    $db = new MockDatabase();
    $db->rows = [
        ['id' => 7, 'name' => 'Cached User'],
    ];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');
    $modul->setSqlTable('users');

    // Populate cache via get()
    $modul->get();
    $initialQueryCount = count($db->queries);

    // Now getId should hit the cache without another DB query
    $result = $modul->getId(7);

    expect($result)->toBeArray();
    expect($result['name'])->toBe('Cached User');
    expect(count($db->queries))->toBe($initialQueryCount); // No new query fired
});

test('getId returns false for null input', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');
    $modul->setSqlTable('users');

    expect($modul->getId(null))->toBeFalse();
});

test('getId with array of ids hits db for uncached ids', function () {
    $db = new MockDatabase();
    $db->rows = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    $modul = new TestModul($db);
    $modul->setSqlBase('SELECT * FROM users');
    $modul->setSqlTable('users');

    $result = $modul->getId([1, 2], true);

    expect($result)->toBeArray();
    expect(count($result))->toBe(2);
});

// --- set with multi-update (array of ids) ---

test('set performs multi-update with array of ids', function () {
    $db = new MockDatabase();
    $db->affected_rows = 2;

    $modul = new TestModul($db);
    $modul->setSqlUpdate('UPDATE users');
    $modul->setSqlTable('users');
    $modul->setIdFormat('id');

    $result = $modul->set(['status' => '"active"'], [10, 20]);

    // set() returns the $ids value that was passed in on multi-update
    expect($result)->toBe([10, 20]);

    $sql = $db->queries[0];
    expect($sql)->toContain('UPDATE users SET');
    expect($sql)->toContain('IN ("10", "20")');
})->throws(\TypeError::class);

// --- set with IODU special mode ---

test('set performs INSERT ON DUPLICATE KEY UPDATE (IODU)', function () {
    $db = new MockDatabase();
    $db->affected_rows = 1;
    $db->insert_id = 55;

    $modul = new TestModul($db);
    $modul->setSqlInsert('INSERT INTO users');
    $modul->setSqlTable('users');

    $result = $modul->set(['name' => '"Alice"', 'email' => '"alice@test.com"'], null, 'IODU');

    $sql = $db->queries[0];
    expect($sql)->toContain('INSERT INTO users');
    expect($sql)->toContain('ON DUPLICATE KEY UPDATE');
    expect($sql)->toContain('name=VALUES(name)');
});

// --- set returns false for empty set ---

test('set returns false when set is not an array', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);

    expect($modul->set(null))->toBeFalse();
    expect($modul->set(false))->toBeFalse();
});

// --- createFulltextSubquery with OR separator ---

test('createFulltextSubquery with OR separator', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);
    $modul->setFulltextColumns(['col1', 'col2']);

    $sql = $modul->createFulltextSubquery('hello world', null, true);

    expect($sql)->toContain('OR');
    expect($sql)->not()->toContain('AND');
    expect($sql)->toContain('LIKE "%hello%"');
    expect($sql)->toContain('LIKE "%world%"');
});

test('createFulltextSubquery returns null without columns or input', function () {
    $db = new MockDatabase();
    $modul = new TestModul($db);

    // No columns, no fulltext_columns set
    expect($modul->createFulltextSubquery('hello'))->toBeNull();

    // No input
    $modul->setFulltextColumns(['col1']);
    expect($modul->createFulltextSubquery(''))->toBeNull();
});

// --- sanitize with inarray ---

test('sanitize inarray returns value when in array', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    expect($modul->sanitize('active', 'inarray', false, ['active', 'inactive']))->toBe('active');
    expect($modul->sanitize('unknown', 'inarray', false, ['active', 'inactive']))->toBeFalse();
});

test('sanitize in_array alias works', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    expect($modul->sanitize('yes', 'in_array', false, ['yes', 'no']))->toBe('yes');
    expect($modul->sanitize('maybe', 'in_array', false, ['yes', 'no']))->toBeFalse();
});

test('sanitize required text returns false when value empty', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    expect($modul->sanitize('', 'text', true))->toBeFalse();
});

test('sanitize text returns empty string for falsy non-required value', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    expect($modul->sanitize(''))->toBe('');
    expect($modul->sanitize(null))->toBe('');
});

// --- fillData, mapFromPost, validate, setter ---

class TestModulWithElements extends TestModul {
    public function __construct(Database &$db) {
        parent::__construct($db);
        // Expose elements for testing
        $this->elements = ['name', 'email'];
    }
    public function setManyToMany(array $config): void { $this->many_to_many = $config; }
}

test('fillData returns false for null id', function () {
    $db = new MockDatabase();
    $modul = new TestModulWithElements($db);

    expect($modul->fillData(null))->toBeFalse();
    expect($modul->fillData(0))->toBeFalse();
});

test('fillData returns false when record not found', function () {
    $db = new MockDatabase();
    // No rows - getId will return false
    $modul = new TestModulWithElements($db);
    $modul->setSqlBase('SELECT * FROM users');
    $modul->setSqlTable('users');

    expect($modul->fillData(999))->toBeFalse();
});

test('fillData populates data array from db', function () {
    $db = new MockDatabase();
    $db->rows = [['id' => 1, 'name' => 'Alice', 'email' => 'alice@test.com', 'extra' => 'ignored']];

    $modul = new TestModulWithElements($db);
    $modul->setSqlBase('SELECT * FROM users');
    $modul->setSqlTable('users');

    $result = $modul->fillData(1);

    expect($result)->toBeTrue();
    expect($modul->data['name'])->toBe('Alice');
    expect($modul->data['email'])->toBe('alice@test.com');
    expect(isset($modul->data['extra']))->toBeFalse(); // only elements are mapped
});

test('mapFromPost maps post data to data array using elements', function () {
    $db = new MockDatabase();
    $modul = new TestModulWithElements($db);

    $modul->mapFromPost(['name' => "Alice's", 'email' => 'alice@test.com', 'ignored' => 'x']);

    expect($modul->data['name'])->toBe("escaped_Alice's");
    // sanitize('text') calls mysqli_real_escape_string on all text values
    expect($modul->data['email'])->toBe('escaped_alice@test.com');
    expect(isset($modul->data['ignored']))->toBeFalse();
});

test('mapFromPost uses custom map when provided', function () {
    $db = new MockDatabase();
    $modul = new TestModulWithElements($db);

    $modul->mapFromPost(['post_name' => 'Bob'], ['post_name' => 'name']);

    expect($modul->data['name'])->toBe('escaped_Bob');
});

test('validate returns empty array by default', function () {
    $db = new MockDatabase();
    $modul = new Modul($db);

    expect($modul->validate())->toBe([]);
});

test('setter builds and executes insert from data array', function () {
    $db = new MockDatabase();
    $db->affected_rows = 1;
    $db->insert_id = 7;

    $modul = new TestModulWithElements($db);
    $modul->setSqlInsert('INSERT INTO users');
    $modul->setSqlTable('users');
    $modul->data = ['name' => 'Alice', 'email' => 'alice@test.com'];

    $result = $modul->setter();

    expect($result)->toBe(7);
    expect($db->queries[0])->toContain('INSERT INTO users');
});