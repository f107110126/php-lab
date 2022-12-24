<?php

if (!defined('DEBUG')) {
    define('DEBUG', false);
}

if (!function_exists('sendEvent')) {
    function sendEvent($message = null, $final = false)
    {
        if ($message) print "data: {$message}\n";
        if ($final) print "\n\n";
        if (ob_get_length()) ob_flush();
        flush();
    }
}

if (!function_exists('clearLog')) {
    function clearLog()
    {
        $statusDir = 'storage';
        $statusFile = 'log';
        $statusPath = ($statusDir ?: '') . DIRECTORY_SEPARATOR . $statusFile;
        checkDir("{$statusDir}", true);
        $statusPath = __DIR__ . DIRECTORY_SEPARATOR . $statusPath;
        // since 'w' mode will truncate the file to zero length, that is not what we want
        // we want keep it content
        fclose(fopen("{$statusPath}", "w")); // to ensure the file exists
    }
}

if (!function_exists('recordLog')) {
    function recordLog($message)
    {
        $statusDir = 'storage';
        $statusFile = 'log';
        $statusPath = ($statusDir ?: '') . DIRECTORY_SEPARATOR . $statusFile;
        checkDir("{$statusDir}", true);
        $statusPath = __DIR__ . DIRECTORY_SEPARATOR . $statusPath;
        // since 'w' mode will truncate the file to zero length, that is not what we want
        // we want keep it content
        $handler = fopen("{$statusPath}", "a"); // to ensure the file exists
        $now = new DateTime();
        fwrite($handler, "[{$now->format('Y-m-d H:i:s')}] {$message}\n");
        fclose($handler);
    }
}

if (!function_exists('allStatus')) {
    function allStatus()
    {
        $status = [];
        $statusDir = 'storage';
        $statusFile = 'status';
        $statusPath = ($statusDir ?: '') . DIRECTORY_SEPARATOR . $statusFile;
        checkDir("{$statusDir}", true);
        $statusPath = __DIR__ . DIRECTORY_SEPARATOR . $statusPath;
        // since 'w' mode will truncate the file to zero length, that is not what we want
        // we want keep it content
        fclose(fopen("{$statusPath}", "a")); // to ensure the file exists
        $format = isset($format) ? $format : "/^([^=]+)=(.+)$/";
        $lines = file($statusPath);
        if (DEBUG) print_r($lines);
        foreach ($lines as $line) {
            $subResults = [];
            $testResult = preg_match($format, $line, $subResults);
            if ($testResult) {
                $key = $subResults[1];
                $value = $subResults[2];
                $value = preg_match('/^true$/i', "{$value}") ? true : $value;
                $value = preg_match('/^false$/i', "{$value}") ? false : $value;
                $status[$key] = $value;
            }
        }
        if (DEBUG) print_r($status);
        return $status;
    }
}

if (!function_exists('readStatus')) {
    function readStatus($key = '')
    {
        $status = allStatus();
        return array_key_exists($key, $status) ? $status[$key] : null;
    }
}

if (!function_exists('writeStatus')) {
    function writeStatus($key, $value = null)
    {
        $status = allStatus();
        if ($value !== null) $status[$key] = $value;
        $statusDir = 'storage';
        $statusFile = 'status';
        $statusPath = ($statusDir ?: '') . DIRECTORY_SEPARATOR . $statusFile;
        $statusPath = __DIR__ . DIRECTORY_SEPARATOR . $statusPath;
        $handler = fopen("{$statusPath}", 'w');
        if (DEBUG) print "Open file " . ($handler === false ? 'failure' : 'success') . "\r";
        foreach ($status as $key => $value) {
            $value = $value === true ? 'true' : $value;
            $value = $value === false ? 'false' : $value;
            $written = "{$key}={$value}\n";
            $result = fwrite($handler, "{$written}");
            if (DEBUG) print "result: " . ($result === false ? 'false' : $result) . "\r";
        }
        fclose($handler);
    }
}

if (!function_exists('clearStatus')) {
    function clearStatus()
    {
        $statusDir = 'storage';
        $statusFile = 'status';
        $statusPath = ($statusDir ?: '') . DIRECTORY_SEPARATOR . $statusFile;
        checkDir("{$statusDir}", true);
        $statusPath = __DIR__ . DIRECTORY_SEPARATOR . $statusPath;
        // since 'w' mode will truncate the file to zero length, that is not what we want
        // we want keep it content
        fclose(fopen("{$statusPath}", "w")); // to ensure the file exists
    }
}

if (!function_exists('checkDir')) {
    function checkDir($dir = '', $forceCreate = false)
    {
        $internalDir = __DIR__ . DIRECTORY_SEPARATOR . $dir;
        if ($forceCreate && !file_exists($internalDir)) {
            $result = mkdir($internalDir, 0755, true);
        } else {
            $result = file_exists($internalDir) && is_dir($internalDir);
        }
        return $result;
    }
}

if (!function_exists('storeVariable')) {
    function storeVariable($filename, $variable = null)
    {
        $statusDir = 'storage';
        $storePath = ($statusDir ?: '') . DIRECTORY_SEPARATOR . $filename;
        $storePath = __DIR__ . DIRECTORY_SEPARATOR . $storePath;
        if ($filename && $variable) {
            return file_put_contents($storePath, serialize($variable));
        }
        if ($filename && file_exists("{$storePath}") && $variable === null) {
            return unlink("{$storePath}");
        }
    }
}

if (!function_exists('retrieveVariable')) {
    function retrieveVariable($filename)
    {
        if ($filename) {
            $statusDir = 'storage';
            $storePath = ($statusDir ?: '') . DIRECTORY_SEPARATOR . $filename;
            $storePath = __DIR__ . DIRECTORY_SEPARATOR . $storePath;
            if (file_exists($storePath)) return unserialize(file_get_contents($storePath));
        }
    }
}
