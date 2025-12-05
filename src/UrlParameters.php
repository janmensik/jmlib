<?php

namespace Janmensik\Jmlib;

/**
 * Modernized version of legacy Url_Parameters class for URL manipulation.
 */
class UrlParameters {
    private string $basename = '';
    private array $parameters = [];
    private string $representation = '';
    private bool $valid = false;
    private array $seenArrays = [];

    public function __construct(string $url = '', string $parameters = '') {
        $this->seenArrays = [];
        $this->setUrl($url, $parameters);
    }

    private function parseParameters(string $parameters): void {
        $list = explode('&', $parameters);
        foreach ($list as $item) {
            $pair = explode('=', $item, 2);
            if (count($pair) === 2) {
                if (preg_match('/^(.*)\[\]$/', urldecode($pair[0]))) {
                    if (!isset($this->parameters[$pair[0]]) || !is_array($this->parameters[$pair[0]])) {
                        $this->parameters[$pair[0]] = [];
                    }
                    $this->parameters[$pair[0]][] = urldecode($pair[1]);
                } else {
                    $this->parameters[$pair[0]] = urldecode($pair[1]);
                }
            }
        }
        $this->valid = false;
    }

    public function setUrl(string $url, string $parameters = ''): void {
        $this->parameters = [];
        $this->valid = false;
        $parts = explode('?', $url);
        $this->basename = $parts[0];
        if (count($parts) === 2) {
            $this->parseParameters($parts[1]);
        }
        $this->parseParameters($parameters);
    }

    public function fromCurrent(bool $completeUrl = true): void {
        $url = $_SERVER['PHP_SELF'] ?? '';
        if (isset($_SERVER['SCRIPT_NAME']) && $_SERVER['PHP_SELF'] !== $_SERVER['SCRIPT_NAME']) {
            $url = $_SERVER['SCRIPT_NAME'] . $_SERVER['PHP_SELF'];
        }
        $params = ($completeUrl && isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
        $this->setUrl($url, $params);
    }

    public function fromCurrentCoolUri(bool $completeUrl = true): void {
        $url = $_SERVER['REQUEST_URI'] ?? '';
        if (($pos = strpos($url, '?')) !== false) {
            $url = substr($url, 0, $pos);
        }
        $params = ($completeUrl && isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
        $this->setUrl($url, $params);
    }

    public function setBasename(string $basename): void {
        $this->basename = $basename;
        $this->valid = false;
    }

    public function setParameter(string $parameter, $value = false): void {
        if ($value === false) {
            unset($this->parameters[$parameter]);
        } else {
            if (preg_match('/^(.*)\[\]$/', urldecode($parameter))) {
                if (!isset($this->parameters[urldecode($parameter)]) || !is_array($this->parameters[urldecode($parameter)])) {
                    $this->parameters[urldecode($parameter)] = [];
                }
                if (!in_array($value, $this->parameters[urldecode($parameter)], true)) {
                    $this->parameters[urldecode($parameter)][] = $value;
                }
            } else {
                $this->parameters[$parameter] = $value;
            }
        }
        $this->valid = false;
    }

    public function getUrl(): string {
        if ($this->valid) {
            return $this->representation;
        }
        ksort($this->parameters);
        $result = $this->basename;
        $parameters = [];
        foreach ($this->parameters as $key => $value) {
            if ($key !== '' && isset($key)) {
                if (is_array($value)) {
                    foreach ($value as $_val) {
                        $parameters[] = $key . '=' . urlencode($_val);
                    }
                } else {
                    $parameters[] = $key . '=' . urlencode($value);
                }
            }
        }
        if (count($parameters)) {
            $result .= '?' . implode('&', $parameters);
        }
        $this->representation = $result;
        $this->valid = true;
        return $result;
    }

    public function getLink(string $string, string $options = ''): string {
        return '<a href="' . $this->getUrl() . '"' . ($options !== '' ? ' ' . trim($options) : '') . '>' . $string . '</a>';
    }

    public function getBasename(): string {
        return $this->basename;
    }

    public function &getParameters(): array {
        return $this->parameters;
    }

    public function getParameter(string $name) {
        return $this->parameters[$name] ?? null;
    }

    public function hasParameter(string $name): bool {
        return isset($this->parameters[$name]);
    }
}
