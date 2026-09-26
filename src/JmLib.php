<?php

namespace Janmensik\Jmlib;

use DateTime;

class JmLib {
    /**
     * Convert utf-8 string to ascii string (no diacritics).
     * Only for Latin-1 and Czech (common) chars.
     * @param string $string Input string in utf-8 encoding.
     * @return string Converted string.
     */
    public static function utf2ascii(string $string): string {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower();');
        # Fallback if transliterator creation failed
        if ($transliterator === null) {
            return $string;
        }

        $result = $transliterator->transliterate($string);

        return $result !== false ? $result : $string;
    }

    /**
     * Create a simple password string using a cryptographically secure PRNG.
     * @param int $length Length of the generated password. Default is 5.
     * @param string|null $salt Optional salt (deprecated, kept for signature compatibility).
     * @return string Generated password string.
     */
    public static function createPassword(int $length = 5, ?string $salt = 'secret'): string {
        if ($length <= 0) {
            return '';
        }
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    /**
     * Convert utf-8 string to SEO-friendly string for links.
     * @param string $string Input string in utf-8 encoding.
     * @return string SEO-friendly string.
     */
    public static function text2seolink(string $string): string {
        $string = self::utf2ascii($string);
        $string = strtolower(preg_replace("/[^a-z0-9-]/i", "-", $string));
        $string = preg_replace("/-{2,}/", "-", $string);
        $string = ltrim($string, '-');
        $string = rtrim($string, '-');
        return $string;
    }

    /**
     * Parse a float from a string, handling commas and spaces.
     * @param string|null $str Input string to parse.
     * @return float|null Parsed float value or null if input is null.
     */
    public static function parseFloat(?string $str): ?float {
        if (!isset($str)) {
            return null;
        }
        $str = str_replace(" ", "", $str);
        $str = str_replace(",", ".", $str);
        if (preg_match("#-?([0-9]+\.?[0-9]{0,5})#", $str, $match)) {
            return floatval($match[0]);
        } else {
            return floatval($str);
        }
    }

    /**
     * Parse a date string into a timestamp (noon by default).
     * @param string|null $data Input date string.
     * @param bool $force If true, returns current date at noon if parsing fails. Default is false.
     * @return int|null Parsed timestamp or null if parsing fails and force is false.
     */
    public static function parseDate(?string $data, bool $force = false): ?int {
        if (!$data) {
            return null;
        } elseif (preg_match('/([0-9]{9,11})/', $data, $datum)) {
            $output = $datum[1];
        } elseif (preg_match('/([0-9]{1,2})\\. ?([0-9]{1,2})\\. ?([1-9][0-9]{3})( ?-? *([0-9]{1,2}):([0-9]{1,2})([:.]([0-9]{1,2}))?)?/', $data, $datum)) {
            $output = mktime(isset($datum[5]) ? $datum[5] : 12, isset($datum[6]) ? $datum[6] : 0, isset($datum[8]) ? $datum[8] : 0, $datum[2], $datum[1], $datum[3]);
        } elseif (preg_match('/([0-9]{1,2})\\. ?([0-9]{1,2})\\. ?( ?-? *([0-9]{1,2}):([0-9]{1,2})([:.]([0-9]{1,2}))?)?/', $data, $datum2)) {
            $output = mktime(isset($datum2[4]) ? $datum2[4] : 12, isset($datum2[5]) ? $datum2[5] : 0, isset($datum2[7]) ? $datum2[7] : 0, $datum2[2], $datum2[1]);
        } elseif (strtotime($data)) {
            $output = strtotime($data);
        } elseif ($force) {
            $output = mktime(12, 0, 0);
        } else {
            $output = null;
        }
        return $output;
    }

    /**
     * Case-insensitive version of strpos().
     * @param string $str The input string.
     * @param string $needle The substring to search for.
     * @param int $offset The position to start searching from. Default is 0.
     * @return int|false The position of the first occurrence of needle in str, or false if not found.
     */
    public static function stripos(string $str, string $needle, int $offset = 0): int|false {
        return strpos(strtolower($str), strtolower($needle), $offset);
    }

    /**
     * Case-insensitive version of strrpos().
     * @param string $haystack The input string.
     * @param string $needle The substring to search for.
     * @param int $offset The position to start searching from. Default is 0.
     * @return int|false The position of the last occurrence of needle in haystack, or false if not found.
     */
    public static function strripos(string $haystack, string $needle, int $offset = 0): int|false {
        if (!is_string($needle)) {
            $needle = chr(intval($needle));
        }
        if ($offset < 0) {
            $temp_cut = strrev(substr($haystack, 0, abs($offset)));
        } else {
            $temp_cut = strrev(substr($haystack, 0, max((strlen($haystack) - $offset), 0)));
        }
        $found = self::stripos($temp_cut, strrev($needle));
        if ($found === false) {
            return false;
        }
        $pos = (strlen($haystack) - ($found + $offset + strlen($needle)));
        return $pos;
    }

    /**
     * Delete a file, or a folder and its contents.
     * @param string $dirname Path to the file or directory to delete.
     * @return bool True on success, false on failure.
     */
    public static function rmdirr(string $dirname): bool {
        if (is_file($dirname)) {
            return unlink($dirname);
        }
        if (!is_dir($dirname)) {
            return false;
        }
        $dir = dir($dirname);
        while (false !== $entry = $dir->read()) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            if (is_dir("$dirname/$entry")) {
                self::rmdirr("$dirname/$entry");
            } else {
                unlink("$dirname/$entry");
            }
        }
        $dir->close();
        return rmdir($dirname);
    }

    /**
     * Retrieves the client's IP address as a string. IPv4 only
     *
     * @return string The IP address of the client.
     */
    public static function getip(): string {
        # Only trust REMOTE_ADDR for security reasons as HTTP headers can be spoofed.
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Reconstructs the current page's full URL.
     *
     * @param bool|null $for_params If true, the URL will be made ready for a new query parameter to be
     *                         appended by ensuring it ends with either '?' or '&'.
     * @param bool|null $remove_existing_params If true, any query params in the original URL will be removed.
     * @return string The current full URL.
     */
    public static function getUrl(?bool $for_params = true, ?bool $remove_existing_params = false): string {
        // Determine the protocol. A non-empty value for 'HTTPS' that isn't 'off' is considered secure.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // Get the host from the server variables.
        $host = $_SERVER['HTTP_HOST'];

        // Get the request URI. If requested, strip the existing query string.
        if ($remove_existing_params) {
            $uri = strtok($_SERVER['REQUEST_URI'], '?');
        } else {
            $uri = $_SERVER['REQUEST_URI'];
        }

        // Construct the full URL.
        $url = "{$protocol}://{$host}{$uri}";

        // If the goal is to add more parameters, ensure the URL ends with a proper separator.
        if ($for_params) {
            // If there's no query string yet, add a '?'
            if (strpos($url, '?') === false) {
                $url .= '?';
            } elseif (substr($url, -1) !== '&' && substr($url, -1) !== '?') {
                $url .= '&';
            }
        }

        return $url;
    }

    /**
     * Generates pagination links for navigating through a list of items.
     *
     * @param int $on_page Number of items to display per page. Default is 20.
     * @param int $total Total number of items.
     * @param int $current_page The current page number. Default is 1.
     * @param int $max_links_to_show Maximum number of pagination links to display. Default is 7.
     * @return array|false Returns an array containing the pagination structure or false if pagination is not needed.
     */
    public static function pagination(int $on_page = 20, int $total = 0, int $current_page = 1, int $max_links_to_show = 7): array|false {
        if ($total <= $on_page) {
            return false;
        }

        $total_pages = (int) ceil($total / $on_page);
        if ($current_page < 1) {
            $current_page = 1;
        }
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }

        $output = [
            'previous' => ($current_page > 1) ? $current_page - 1 : null,
            'next' => ($current_page < $total_pages) ? $current_page + 1 : null,
            'active_page' => $current_page,
            'first' => ($current_page > 1) ? 1 : null,
            'last' => ($current_page < $total_pages) ? $total_pages : null,
            'total_pages' => $total_pages,
            'total_items' => $total,
            'on_page' => $on_page,
            'pages' => []
        ];

        if ($total_pages <= $max_links_to_show) {
            $output['pages'] = range(1, $total_pages);
        } else {
            $pages = [];
            $pages[] = 1; // Always show first page

            $num_adjacent = $max_links_to_show - 2; // slots left after first and last
            $start = (int)max(2, $current_page - floor($num_adjacent / 2));
            $end = min($total_pages - 1, $start + $num_adjacent - 1);

            // Adjust start if we are at the end
            $start = (int)max(2, $end - $num_adjacent + 1);

            if ($start > 2) {
                $pages[] = null; // '...'
            }

            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }

            if ($end < $total_pages - 1) {
                $pages[] = null; // '...'
            }

            $pages[] = $total_pages; // Always show last page

            $output['pages'] = $pages;
        }

        return $output;
    }

    # ocekava text a vrati pole['from', 'till'] odpovidajici rozpeti v timestamp


    /*     * Get time interval based on predefined text names.
     *
     * @param string $textname The name of the time interval (e.g., "today", "yesterday", "last7days").
     * @param int|null $now Optional timestamp to base calculations on. Defaults to current time at noon.
     * @param string|null $return_only If specified, returns only 'from' or 'till' value.
     * @return array|int|null An array with 'from' and 'till' timestamps, or a single timestamp if $return_only is set.
     */
    public static function getInterval(string $textname, ?int $now = null, ?string $return_only = null): array|int|null {
        if (!$now) {
            $now = mktime(12, 0, 0);
        }

        switch ($textname) {
            case "today":
                $output['from'] = mktime(0, 0, 0, date('n', $now), date('j', $now), date('Y', $now));
                $output['till'] = mktime(23, 59, 59, date('n', $now), date('j', $now), date('Y', $now));
                break;
            case "yesterday":
                $vcera = strtotime('-1 day', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $vcera), date('j', $vcera), date('Y', $vcera));
                $output['till'] = mktime(23, 59, 59, date('n', $vcera), date('j', $vcera), date('Y', $vcera));
                break;
            case "last7":
            case "last7days":
                $from = strtotime('-6 day', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $from), date('j', $from), date('Y', $from));
                $output['till'] = mktime(23, 59, 59, date('n', $now), date('j', $now), date('Y', $now));
                break;
            case "last30":
            case "last30days":
                $from = strtotime('-29 day', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $from), date('j', $from), date('Y', $from));
                $output['till'] = mktime(23, 59, 59, date('n', $now), date('j', $now), date('Y', $now));
                break;
            case "lastweek":
            case 'last_week':
                $from = strtotime('-1 week last monday', $now);
                $till = strtotime('-1 week sunday', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $from), date('j', $from), date('Y', $from));
                $output['till'] = mktime(23, 59, 59, date('n', $till), date('j', $till), date('Y', $till));
                break;
            case "month":
                $output['from'] = mktime(0, 0, 0, date('n', $now), 1, date('Y', $now));
                $output['till'] = mktime(23, 59, 59, date('n', $now), date('t', $now), date('Y', $now));
                break;
            case "lastmonth":
            case 'last_month':
                if (date('m') == date('m', strtotime('-1 month', $now))) {
                    $now = strtotime('-1 day', $now);
                }
                $from = strtotime('-1 month', $now);
                $till = strtotime('-1 month', $now);

                $output['from'] = mktime(0, 0, 0, date('n', $from), 1, date('Y', $from));
                $output['till'] = mktime(23, 59, 59, date('n', $till), date('t', $till), date('Y', $till));
                break;
            case "tomorrow":
                $zitra = strtotime('+1 day', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $zitra), date('j', $zitra), date('Y', $zitra));
                $output['till'] = mktime(23, 59, 59, date('n', $zitra), date('j', $zitra), date('Y', $zitra));
                break;
            case "nextmonth":
            case 'next_month':
                $from = strtotime('+1 month', $now);
                $till = strtotime('+1 month', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $from), 1, date('Y', $from));
                $output['till'] = mktime(23, 59, 59, date('n', $till), date('t', $till), date('Y', $till));
                break;
            case "next7":
            case "next7days":
                $till = strtotime('+6 day', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $now), date('j', $now), date('Y', $now));
                $output['till'] = mktime(23, 59, 59, date('n', $till), date('j', $till), date('Y', $till));
                break;
            case "thisyear":
            case 'this_year':
            case 'year':
            case 'current_year':
                $output['from'] = mktime(0, 0, 0, 1, 1, date('Y', $now));
                $output['till'] = mktime(23, 59, 59, date('n', $now), date('t', $now), date('Y', $now));
                break;
            case "lastyear":
            case 'last_year':
            case 'previous_year':
                $output['from'] = mktime(0, 0, 0, 1, 1, date('Y', $now) - 1);
                $output['till'] = mktime(23, 59, 59, 12, 31, date('Y', $now) - 1);
                break;
            case "last6months":
                $from = strtotime('-6 months', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $from), date('j', $from), date('Y', $from));
                $output['till'] = mktime(23, 59, 59, date('n', $now), date('j', $now), date('Y', $now));
                break;
            case "last3months":
                $from = strtotime('-3 months', $now);
                $output['from'] = mktime(0, 0, 0, date('n', $from), date('j', $from), date('Y', $from));
                $output['till'] = mktime(23, 59, 59, date('n', $now), date('j', $now), date('Y', $now));
                break;
            case "all":
            default:
        }

        return ($return_only && $output[$return_only] ? $output[$return_only] : $output);
    }

    /**
     * Counts the number of days between two timestamps.
     *
     * Calculates the number of complete days between two given timestamps.
     * For example, the difference between '22.05.2013 11:30' and '21.05.2013 09:00' equals 1 day.
     *
     * @param int $startTimestamp Unix timestamp of the start date/time
     * @param int $endTimestamp Unix timestamp of the end date/time
     * @return int The number of days between the two timestamps
     */
    public static function countDays(?int $from = 0, ?int $till = 0): int {
        if (!$from || !$till) {
            return (0);
        }
        $dd = date_diff(new DateTime('@' . intval($from)), new DateTime('@' . intval($till)));

        return (round($dd->days));
    }

    /**
     * Gets the file modification time of a remote file
     *
     * @param string $remoteFile The URL or path of the remote file
     * @return int|false The time of the last modification as a Unix timestamp, or false on failure
     */
    public static function filemtimeRemote(string $remoteFile): int|false {
        static $cache = [];

        if (isset($cache[$remoteFile])) {
            return $cache[$remoteFile];
        }

        $scheme = parse_url($remoteFile, PHP_URL_SCHEME);
        if (!in_array(strtolower((string)$scheme), ['http', 'https'])) {
            return false;
        }

        $host = parse_url($remoteFile, PHP_URL_HOST);
        if ($host !== null && $host !== false) {
            $ip = gethostbyname((string)$host);
            // Check if IP is private or loopback
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        } else {
            return false;
        }

        $ch = curl_init($remoteFile);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Disable redirects to prevent SSRF via 3xx to internal IPs
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Mitigate IPv6 bypasses

        // Prevent TOCTOU DNS Rebinding attacks by forcing cURL to use the validated IP
        $port = parse_url($remoteFile, PHP_URL_PORT) ?: (strtolower((string)$scheme) === 'https' ? 443 : 80);
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$ip}"]);

        if (curl_exec($ch) !== false) {
            $info_opt = defined('CURLINFO_FILETIME_T') ? CURLINFO_FILETIME_T : CURLINFO_FILETIME;
            $timestamp = curl_getinfo($ch, $info_opt);
            if ($timestamp !== -1) {
                $cache[$remoteFile] = $timestamp;
                return $timestamp;
            }
        }
        return false;
    }

    /**
     * Builds a month calendar structure organized by weeks (1=Monday .. 7=Sunday).
     * @param int|null $month Month number (1-12). Defaults to the current month.
     * @param int|null $year Year (1970-2038). Defaults to the current year.
     * @param bool $fill If true, days outside the month are filled with adjacent timestamps instead of false.
     * @param string $returnFormat 'ts-noon' (default) returns timestamps at noon, 'day' returns day-of-month numbers.
     * @return array<int, array<int, int|false|string>> Calendar grid indexed by [week][weekday].
     */
    public static function createCalendar(?int $month = null, ?int $year = null, bool $fill = false, string $returnFormat = 'ts-noon'): array {
        $month = ($month !== null && $month >= 1 && $month <= 12) ? $month : (int) date('n');
        $year = ($year !== null && $year >= 1970 && $year <= 2038) ? $year : (int) date('Y');

        $startDate = mktime(12, 0, 0, $month, 1, $year);
        $endDate = mktime(12, 0, 0, $month, (int) date('t', $startDate), $year);

        $output = [];
        $week = 0;
        $dayOfWeek = 1; // 1-7 = Mon-Sun
        $startDay = (int) date('w', $startDate);
        if ($startDay === 0) {
            $startDay = 7;
        }

        for ($i = 1; $i < $startDay; $i++, $dayOfWeek++) {
            $output[$week][$i] = $fill ? strtotime('-' . ($startDay - $i) . ' days', $startDate) : false;
        }

        for ($day = $startDate; $day <= $endDate; $day = strtotime('+1 day', $day)) {
            if ($dayOfWeek === 8) {
                $week++;
                $dayOfWeek = 1;
            }
            $output[$week][$dayOfWeek] = $day;
            $dayOfWeek++;
        }

        if ($dayOfWeek <= 7) {
            for ($i = $dayOfWeek; $i < 8; $i++, $dayOfWeek++) {
                $output[$week][$i] = $fill ? strtotime('+' . ($i - 1) . ' days', $endDate) : false;
            }
        }

        if ($returnFormat === 'day') {
            foreach ($output as $weekKey => $weekValue) {
                foreach ($weekValue as $dayKey => $dayValue) {
                    $output[$weekKey][$dayKey] = $dayValue !== false ? date('j', $dayValue) : false;
                }
            }
        }

        return $output;
    }

    /**
     * Returns the start or end boundary timestamp of an hour/day/month/year that contains $ts.
     * @param string $level One of 'hour', 'day', 'month', 'year'.
     * @param int $ts Reference timestamp. Values <= 0 default to the current time.
     * @param bool $end If true, returns the end boundary, otherwise the start boundary.
     * @return int|null Boundary timestamp, or null for an unknown level.
     */
    public static function datetimeBoundary(string $level = 'day', int $ts = 1, bool $end = false): ?int {
        if ($ts === 1 || $ts < 1) {
            $ts = time();
        }

        $result = match ($level) {
            'hour' => mktime((int) date('H', $ts), $end ? 59 : 0, $end ? 59 : 0, (int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts)),
            'day' => mktime($end ? 23 : 0, $end ? 59 : 0, $end ? 59 : 0, (int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts)),
            'month' => mktime($end ? 23 : 0, $end ? 59 : 0, $end ? 59 : 0, (int) date('n', $ts), $end ? (int) date('t', $ts) : 1, (int) date('Y', $ts)),
            'year' => mktime($end ? 23 : 0, $end ? 59 : 0, $end ? 59 : 0, $end ? 12 : 1, $end ? 31 : 1, (int) date('Y', $ts)),
            default => null,
        };

        return $result === false ? null : $result;
    }

    /**
     * Implodes a (possibly nested) array, using $separator1 for nested arrays and $separator2 between top-level items.
     * If $data has no nested arrays, $separator1 is used for the whole (flat) implode.
     * @param string $separator1 Separator used within nested arrays (or the whole array if it is flat).
     * @param string $separator2 Separator used between top-level items when nested arrays are present.
     * @param mixed $data The array to implode. Non-array values are returned unchanged (cast to string).
     * @return string The imploded string.
     */
    public static function doubleImplode(string $separator1, string $separator2, mixed $data): string {
        if (!is_array($data)) {
            return (string) $data;
        }

        $output = [];
        $hasNestedArray = false;

        foreach ($data as $value) {
            if (is_array($value)) {
                $output[] = implode($separator1, $value);
                $hasNestedArray = true;
            } else {
                $output[] = $value;
            }
        }

        return implode($hasNestedArray ? $separator2 : $separator1, $output);
    }

    /**
     * Returns the contents of a directory (like readdir(), including '.' and '..').
     * @param string $dirname The directory to read.
     * @return array|false Array of entry names, or false on failure.
     */
    public static function getDir(string $dirname): array|false {
        $dir = @opendir($dirname);
        if ($dir === false) {
            return false;
        }

        $output = [];
        while (($file = readdir($dir)) !== false) {
            $output[] = $file;
        }
        closedir($dir);

        return $output;
    }

    /**
     * Fetches (with file caching) the daily exchange rate list published by the Czech National Bank.
     * @param string $cacheFile Path to the local cache file.
     * @param int $cacheDuration Cache validity in seconds. Default is 3600 (1 hour).
     * @return array|null Associative array keyed by lowercase currency code, or null on failure.
     */
    public static function kurzyCnb(string $cacheFile = './cache/kurzy_cnb.txt', int $cacheDuration = 3600): ?array {
        clearstatcache();

        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheDuration) {
            $cached = @file_get_contents($cacheFile);
            if ($cached !== false) {
                $data = @unserialize($cached, ['allowed_classes' => false]);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        $data = self::kurzyCnbFetch();

        if (is_array($data)) {
            @file_put_contents($cacheFile, serialize($data));
            return $data;
        }

        if (is_file($cacheFile)) {
            $cached = @file_get_contents($cacheFile);
            $data = $cached !== false ? @unserialize($cached, ['allowed_classes' => false]) : null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Downloads and parses the CNB daily exchange rate text file.
     * @return array|null Associative array keyed by lowercase currency code, or null on failure.
     */
    private static function kurzyCnbFetch(): ?array {
        $url = 'https://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $content = curl_exec($ch);
        curl_close($ch);

        if (!is_string($content) || $content === '') {
            return null;
        }

        $rates = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (preg_match('/^([^|]+)\|([^|]+)\|([0-9]+)\|([A-Za-z]{3})\|([0-9,]+)/u', trim($line), $match)) {
                $code = strtolower($match[4]);
                $rates[$code] = [
                    'zeme' => $match[1],
                    'mena' => $match[2],
                    'mnozstvi' => (int) $match[3],
                    'kod' => $match[4],
                    'kurz' => (float) str_replace(',', '.', $match[5]),
                ];
            }
        }

        return $rates;
    }

    /**
     * Fits y = a + b*log(x) to the given data (x implied as 1..n) using least squares.
     * @param mixed $data List of numeric y-values. Must be a non-empty array.
     * @return array|null Fitted y-values (same count as $data), or null on invalid input.
     */
    public static function leastSquaresFittingLogarithmic(mixed $data): ?array {
        if (!is_array($data) || empty($data)) {
            return null;
        }

        $x = [];
        $y = [];
        $i = 1;
        foreach ($data as $value) {
            $x[] = $i;
            $y[] = $value;
            $i++;
        }

        $logX = array_map('log', $x);
        $n = count($x);

        $sumY = array_sum($y);
        $sumLogX = array_sum($logX);
        $sumLogXSquared = array_sum(array_map(fn($v) => $v ** 2, $logX));
        $sumXy = array_sum(array_map(fn($a, $b) => $a * $b, $logX, $y));

        $denominator = $n * $sumLogXSquared - $sumLogX ** 2;
        if ($denominator == 0.0) {
            return null;
        }

        $b = ($n * $sumXy - $sumY * $sumLogX) / $denominator;
        $a = ($sumY - $b * $sumLogX) / $n;

        $fitted = [];
        foreach ($x as $value) {
            $fitted[] = $a + $b * log($value);
        }

        return $fitted;
    }

    /**
     * Calculates a trailing simple moving average, preserving array keys' order.
     *
     * When $sameCount is true, the result has the same number of elements as $data; the
     * window shrinks near the start so early elements average over fewer values (fixes the
     * legacy implementation, which read out-of-bounds array indices near the end of the set).
     * When $sameCount is false, $data is split into non-overlapping chunks of $subsetSize and
     * each chunk is averaged, yielding roughly count($data)/$subsetSize elements.
     *
     * @param mixed $data List of numeric values.
     * @param int $subsetSize Size of the averaging window/chunk. Default is 5.
     * @param bool $sameCount Whether to keep the same element count as $data. Default is true.
     * @return array|null The averaged values, the original $data if $subsetSize is too small/large, or null on invalid input.
     */
    public static function movingAverage(mixed $data, int $subsetSize = 5, bool $sameCount = true): mixed {
        if (!is_array($data)) {
            return null;
        }

        if ($subsetSize < 1 || count($data) < $subsetSize) {
            return $data;
        }

        $values = array_values($data);
        $output = [];

        if (!$sameCount) {
            foreach (array_chunk($values, $subsetSize) as $chunk) {
                $output[] = array_sum($chunk) / count($chunk);
            }
            return $output;
        }

        $sum = 0.0;
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            $sum += $values[$i];
            if ($i >= $subsetSize) {
                $sum -= $values[$i - $subsetSize];
            }
            $windowLength = min($subsetSize, $i + 1);
            $output[$i] = $sum / $windowLength;
        }

        return $output;
    }

    /**
     * Extracts a single column/key from each element of an array, preserving the original keys.
     * @param mixed $data Array of arrays/objects to pluck the value from.
     * @param mixed $key The array key or object property name to extract.
     * @return array|null Array (same keys as $data) of extracted values (null where missing), or null on invalid input.
     */
    public static function oneFromArray(mixed $data, mixed $key): ?array {
        if (!is_array($data) || !$key) {
            return null;
        }

        $output = [];
        foreach ($data as $k => $value) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $output[$k] = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $output[$k] = $value->$key;
            } else {
                $output[$k] = null;
            }
        }

        return $output;
    }
}
