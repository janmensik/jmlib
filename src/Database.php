<?php

namespace Janmensik\Jmlib;

use mysqli;
use mysqli_result;

class Database {
    public $user;
    public $password;
    public $database;
    public $server;
    public $result;
    public $db;
    public $messages = array(); # debug informace

    # ...................................................................
    /**
     * Database constructor.
     * @param string $server The database server hostname or IP address.
     * @param string $database The name of the database.
     * @param string $user The username for the database connection.
     * @param string $password The password for the database connection.
     */
    public function __construct($server, $database, $user, $password) {
        # kontrola predanych hodnot
        if (!$server || !$database || !$user) {
            return;
        }
        $this->server = $server;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;


        # iniciace promennych
        $this->messages['total_time'] = 0;
        $this->messages['total_queries'] = 0;

        return;
    }

    # ...................................................................
    /**
     * Connects to the database.
     * @return mysqli|false The mysqli connection object on success, false on failure.
     */
    private function connect() {
        # pripojeni MySQL databaze
        $this->db = new mysqli($this->server, $this->user, $this->password, $this->database);

        if (!$this->db) {
            $this->messages['system'] = 'DB not connected!';
            return (false);
        }
        $this->messages['system'] = 'DB connected.';

        $this->messages['total_time'] = 0;
        $this->messages['total_queries'] = 0;

        return ($this->db);
    }

    # ...................................................................
    /**
     * Executes a query against the database.
     * @param string $query The SQL query to execute.
     * @param string $query_name An optional name for the query for debugging purposes.
     * @return mysqli_result|bool For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries, mysqli_query will return a mysqli_result object. For other successful queries mysqli_query will return true. Returns false on failure.
     */
    public function query($query, $query_name = '') {
        unset($this->result);

        # pokud neni pripojena databaze, pripoj
        if (!$this->db) {
            $this->connect();
        }

        if ($this->db) {
            # spusteni mereni
            list($usec, $sec) = explode(" ", microtime());
            $time_start = (float) $usec + (float) $sec;

            # vlastni dotaz
            $this->result = mysqli_query($this->db, $query);

            #konec mereni a zapsani do $messages
            list($usec, $sec) = explode(" ", microtime());
            $elapsed = round(((float) $usec + (float) $sec) - $time_start, 5);
            $this->messages['total_time'] += $elapsed;
            $this->messages['total_queries']++;
            $this->messages['queries_summary']['undefined'] = 0;
            if ($query_name && $query_name != '') {
                @$this->messages['queries_summary'][$query_name]++;
                $this->messages['queries'][] = array('name' => $query_name, 'time' => (string) $elapsed, 'query' => $query);
            } else {
                $this->messages['queries_summary']['undefined']++;
                $this->messages['queries'][] = array('time' => (string) $elapsed, 'query' => $query);
            }
        }
        return ($this->result);
    }

    # ...................................................................
    /**
     * Returns the number of rows in the result set or the number of affected rows.
     * @return int|false The number of rows, or false on error.
     */
    public function numRows() {
        if (!$this->db) {
            return (false);
        }

        $output = @mysqli_num_rows($this->result);
        if ($output) {
            return ($output);
        } else {
            return (mysqli_affected_rows($this->db));
        }
    }

    # ...................................................................
    /**
     * Fetches one row from the result set as an associative array.
     * @param mysqli_result|null $result Optional result to fetch from. If null, the last query result is used.
     * @return array|false|null An associative array representing the fetched row, null if there are no more rows, or false on error.
     */
    public function getRow($result = null) {
        if (!$this->db) {
            return (false);
        }

        if ($result) {
            $radka = mysqli_fetch_assoc($result);
        } else {
            $radka = mysqli_fetch_assoc($this->result);
        }

        if (is_array($radka)) {
            return ($radka);
        } else {
            return (false);
        }
    }

    # ...................................................................
    /**
     * Fetches all rows from the result set as an array of associative arrays.
     * @param mysqli_result|null $result Optional result to fetch from. If null, the last query result is used.
     * @param string|null $index_by The column name to use as the index for the output array.
     * @return array|null An array of all result rows, or null if no rows.
     */
    public function getAllRows($result = null, $index_by = null) {
        if (!$this->db) {
            return (false);
        }

        $output = null;

        while ($row = $this->getRow($result)) {
            if ($index_by) {
                $output[$row[$index_by]] = $row;
            } else {
                $output[] = $row;
            }
        }
        return ($output);
    }

    # ...................................................................
    /**
     * Returns the first column of the first row from the result set.
     * @param mysqli_result|null $result Optional result to fetch from. If null, the last query result is used.
     * @return mixed|false The value of the first column, or false on error.
     */
    public function getResult($result = null) {
        if (!$this->db) {
            return (false);
        }

        $row = mysqli_fetch_array($result ? $result : $this->result); // fetch fata
        return ($row[0]);

        /*
        if ($result)
            return (mysql_result ($result, 0, 0));
        else
            return (mysql_result ($this->result, 0, 0));
        */
    }

    # ...................................................................
    /**
     * Returns the total number of rows for a query that used SQL_CALC_FOUND_ROWS, ignoring the LIMIT clause.
     * @return int|false The total number of rows, or false on error.
     */
    public function getRowsCount() {
        if (!$this->db) {
            return (false);
        }
        return ($this->getResult($this->query('SELECT FOUND_ROWS();', 'FOUND ROWS')));
    }

    # ...................................................................
    /**
     * Returns the number of rows affected by the last INSERT, UPDATE, REPLACE or DELETE query.
     * @return int The number of affected rows.
     */
    public function getNumAffected() {
        if (!$this->db) {
            return (false);
        }
        return (mysqli_affected_rows($this->db));
    }

    # ...................................................................
    /**
     * Returns the ID generated by the last INSERT query.
     * @return int|string|false The ID generated for an AUTO_INCREMENT column by the previous query on success, 0 if the previous query does not generate an AUTO_INCREMENT value, or false if no MySQL connection was established.
     */
    public function getId() {
        if (!$this->db) {
            return (false);
        }

        return (mysqli_insert_id($this->db));
    }

    # ...................................................................
    /**
     * Frees the memory associated with a result.
     * @param mysqli_result|null $result Optional result to free. If null, the last query result is freed.
     * @return bool True on success, false on failure.
     */
    public function freeResult($result = null) {
        if (!$this->db) {
            return (false);
        }

        if ($result) {
            return (mysqli_free_result($result));
        } else {
            return (mysqli_free_result($this->result));
        }
    }
}
