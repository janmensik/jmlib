<?php

namespace Janmensik\Jmlib;

class AppData {
    private static ?AppData $instance = null;
    public array $data = [];
    public array $MESSAGES = [];
    public array $FILTERS = [];
    private array $FILTERS_REGISTERED = [];

    private function __construct() {
    }
    public function __clone() {
        trigger_error('Cloning of singleton instances is forbidden', E_USER_ERROR);
    }

    public static function getInstance(): AppData {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setData(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function getData(?string $key = null): mixed {
        if (!$key) {
            return $this->data;
        }

        if (!isset($this->data[$key])) {
            return null;
        }
        return $this->data[$key];
    }

    public function getMessages(): ?array {
        if (!isset($this->MESSAGES) || !is_array($this->MESSAGES)) {
            return null;
        }
        return $this->MESSAGES;
    }

    public function loadMessages(): ?bool {
        if (isset($this->MESSAGES) && is_array($this->MESSAGES) && count($this->MESSAGES)) {
            return false;
        }

        if (!isset($_SESSION['messages']) || !is_array($_SESSION['messages'])) {
            return null;
        }

        $this->MESSAGES = $_SESSION['messages'];

        $this->loadFilters();

        return true;
    }

    public function hibernateMessages(): ?bool {
        if (!isset($this->MESSAGES) || !is_array($this->MESSAGES)) {
            return null;
        }

        $_SESSION['messages'] = $this->MESSAGES;

        $this->hibernateFilters();

        session_write_close();

        return true;
    }

    public function clearMessages(bool $force_clear_messages = false): ?array {
        $output = [];

        if (!isset($this->MESSAGES) || !is_array($this->MESSAGES)) {
            return null;
        }

        unset($_SESSION['messages']);

        $output = $this->MESSAGES;

        if ($force_clear_messages) {
            $this->MESSAGES = [];
        }

        return $output;
    }


    public function initiateFilters(?string $page = null): bool|array {
        if (!$page) {
            return true;
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
        return ($this->FILTERS[$page] ?? []);
    }

    public function registerFilters(?string $page = null, ?array $filters = null): bool {
        if (!$page || !$filters) {
            return true;
        }

        if (!is_array($filters) || !count($filters)) {
            return false;
        }

        $this->FILTERS_REGISTERED = $filters;

        unset($this->FILTERS[$page]);

        foreach ($this->FILTERS_REGISTERED as $filter) {
            $this->FILTERS[$page][$filter] = null;
        }

        return true;
    }

    public function clearFilters(?string $page = null): bool {
        if (!$page) {
            return true;
        }

        unset($this->FILTERS[$page]);

        return true;
    }

    public function getFilters(?string $page = null): ?array {
        if (!$page) {
            return null;
        }

        if (!isset($this->FILTERS[$page]) || !is_array($this->FILTERS[$page])) {
            return null;
        }
        return $this->FILTERS[$page];
    }

    public function loadFilters(): bool|null {
        if (isset($this->FILTERS) && is_array($this->FILTERS) && count($this->FILTERS)) {
            return false;
        }

        if (!isset($_SESSION['FILTERS']) || !is_array($_SESSION['FILTERS'])) {
            return null;
        }

        $this->FILTERS = $_SESSION['FILTERS'];

        return true;
    }

    public function hibernateFilters(): ?bool {
        if (!isset($this->FILTERS) || !is_array($this->FILTERS)) {
            return null;
        }

        $_SESSION['FILTERS'] = $this->FILTERS;
        // session_write_close();

        return true;
    }
}
