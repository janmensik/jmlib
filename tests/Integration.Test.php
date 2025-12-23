<?php
namespace Janmensik\Jmlib;

use Janmensik\Jmlib\Modul;
use Janmensik\Jmlib\Database;

// Skip these tests if DB credentials aren't provided in phpunit.xml or .env
if (!getenv('DB_HOST')) {
    return;
}

beforeEach(function () {
    // Setup: Create a fresh instance with real credentials
    $this->db = new Database(
        getenv('DB_HOST'),
        getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS')
    );

    // Optional: Clean/Seed table before each test
    $this->db->query("CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))");
    $this->db->query("TRUNCATE TABLE test_users");
});

afterAll(function () {
    // Cleanup
    $db = new Database(getenv('DB_HOST'), getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));
    $db->query("DROP TABLE IF EXISTS test_users");
});

test('real database connection is successful', function () {
    // This actually tests _connect()
    $result = $this->db->query('SELECT 1');

    expect($this->db->messages['system'])->toBe('DB connected.');
    expect($this->db->getResult($result))->toBe(1);
});

test('insert and retrieve real data', function () {
    $this->db->query("INSERT INTO test_users (name) VALUES ('Alice')");
    $id = $this->db->getId();

    expect($id)->toBeGreaterThan(0);

    $row = $this->db->getRow($this->db->query("SELECT * FROM test_users WHERE id = $id"));

    expect($row['name'])->toBe('Alice');
});

test('real escape string works', function () {
    // This tests the actual mysqli driver escaping
    $modul = new Modul($this->db);
    $unsafe = "O'Reilly";

    // Note: We are testing the Modul::sanitize wrapper which calls mysqli_real_escape_string
    $safe = $modul->sanitize($unsafe);

    // The driver should escape the quote
    expect($safe)->toBe("O\\'Reilly");
});
