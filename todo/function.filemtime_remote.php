<?php

/**
 * Retrieves the last modified time of a remote file.
 *
 * @param string $string URL of the remote file
 * @return int Unix timestamp of the last modified time, or 0 on failure
 */
function filemtime_remote($string) {
    static $cache;

    if ($cache[$string])
        return ($cache[$string]);

    $uri = parse_url($string);
    $uri['port'] = isset($uri['port']) ? $uri['port'] : 80;
    $handle = @fsockopen($uri['host'], $uri['port']);
    if (!$handle)
        return 0;

    fputs($handle, "HEAD $uri[path] HTTP/1.1\r\nHost: $uri[host]\r\n\r\n");
    $result = 0;
    while (!feof($handle)) {
        $line = fgets($handle, 1024);
        if (!trim($line))
            break;

        $col = strpos($line, ':');
        if ($col !== false) {
            $header = trim(substr($line, 0, $col));
            $value = trim(substr($line, $col + 1));
            if (strtolower($header) == 'last-modified') {
                $result = strtotime($value);
                $cache[$string] = $result;
                break;
            }
        }
    }
    fclose($handle);
    return $result;
}
