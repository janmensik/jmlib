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
     * Create a simple password string.
     * @param int $length Length of the generated password. Default is 5.
     * @param string|null $salt Optional salt to enhance uniqueness. Default is 'secret'.
     * @return string Generated password string.
     */
    public static function createPassword(int $length = 5, ?string $salt = 'secret'): string {
        return (substr(sha1(time() . $salt), 0, $length));
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
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            # check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            # to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            # regular ip
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
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
    public static function pagination($on_page = 20, $total = 0, $current_page = 1, $max_links_to_show = 7) {
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
            $start = max(2, $current_page - floor($num_adjacent / 2));
            $end = min($total_pages - 1, $start + $num_adjacent - 1);

            // Adjust start if we are at the end
            $start = max(2, $end - $num_adjacent + 1);

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
    public static function getInterval(string $textname, ?int $now = null, ?string $return_only = null) {
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

        $ch = curl_init($remoteFile);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

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
}
