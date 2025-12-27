<?php

namespace Janmensik\Jmlib;

class AppData {
    private static $instance;
    public $data = [];
    public $MESSAGES = [];
    public $FILTERS = [];
    private $FILTERS_REGISTERED = [];

    private function __construct() {
    }
    public function __clone() {
        trigger_error('Cloning of singleton instances is forbidden', E_USER_ERROR);
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setData($key, $value) {
        $this->data[$key] = $value;
    }

    public function getData($key = null) {
        if (!$key) {
            return $this->data;
        }

        if (!isset($this->data[$key])) {
            return null;
        }
        return $this->data[$key];
    }

    public function getMessages() {
        if (!isset($this->MESSAGES) || !is_array($this->MESSAGES)) {
            return null;
        }
        return $this->MESSAGES;
    }

    public function loadMessages() {
        if (isset($this->MESSAGES) && is_array($this->MESSAGES) && count($this->MESSAGES)) {
            return false;
        }

        if (!is_array(@$_SESSION['messages'])) {
            return (null);
        }

        $this->MESSAGES = $_SESSION['messages'];

        $this->loadFilters();

        return (true);
    }

    public function hibernateMessages() {
        if (!isset($this->MESSAGES) || !is_array($this->MESSAGES)) {
            return null;
        }

        $_SESSION['messages'] = $this->MESSAGES;

        $this->hibernateFilters();

        session_write_close();

        return (true);
    }

    public function clearMessages($force_clear_messages = false) {
        $output = [];

        if (!isset($this->MESSAGES) || !is_array($this->MESSAGES)) {
            return null;
        }

        unset($_SESSION['messages']);

        $output = $this->MESSAGES;

        if ($force_clear_messages) {
            $this->MESSAGES = [];
        }

        return ($output);
    }


    public function initiateFilters($page = null) {
        if (!$page) {
            return (true);
        }

        if (isset($this->FILTERS[$page]) && is_array($this->FILTERS[$page])) {
            foreach ($this->FILTERS[$page] as $key => $value) {
                if (isset($this->FILTERS[$page][$key]) && !isset($_GET[$key])) {
                    $_GET[$key] = $value;
                } elseif (isset($_GET[$key]) && $_GET[$key] !== '') {
                    $this->FILTERS[$page][$key] = $_GET[$key];
                }
            }
        }
        return ($this->FILTERS[$page]);
    }

    public function registerFilters($page = null, $filters = null) {
        if (!$page || !$filters) {
            return (true);
        }

        if (!is_array($filters) || !count($filters)) {
            return (false);
        }

        $this->FILTERS_REGISTERED = $filters;

        unset($this->FILTERS[$page]);

        foreach ($this->FILTERS_REGISTERED as $filter) {
            $this->FILTERS[$page][$filter] = null;
        }

        return (true);
    }

    public function clearFilters($page = null) {
        if (!$page) {
            return (true);
        }

        unset($this->FILTERS[$page]);

        return (true);
    }

    public function getFilters($page = null) {
        if (!$page) {
            return (null);
        }

        if (!isset($this->FILTERS[$page]) || !is_array($this->FILTERS[$page])) {
            return null;
        }
        return $this->FILTERS[$page];
    }

    public function loadFilters() {
        if (isset($this->FILTERS) && is_array($this->FILTERS) && count($this->FILTERS)) {
            return false;
        }

        if (!is_array(@$_SESSION['FILTERS'])) {
            return (null);
        }

        $this->FILTERS = $_SESSION['FILTERS'];

        return (true);
    }

    public function hibernateFilters() {
        if (!isset($this->FILTERS) || !is_array($this->FILTERS)) {
            return null;
        }

        $_SESSION['FILTERS'] = $this->FILTERS;
        // session_write_close();

        return (true);
    }
}
