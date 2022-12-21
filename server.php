<?php

define('HOST_NAME', '127.0.0.1');
define('PORT', '8090');
$null = null;

// refs: https://tutorialsclass.com/faq/how-can-we-set-infinite-execution-time-in-php-script/
set_time_limit(0);

require_once 'src/functions.php';

header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['enable'])) {
    if ($_GET['enable'] === 'true') writeStatus('enable', true);
    if ($_GET['enable'] === 'false') writeStatus('enable', false);
    return;
}

if (isset($_GET['cmd'])) {
    if ($_GET['cmd'] === 'clear') clearLog();
    return;
}

print "normal execution\n";
$start = new DateTime();
recordLog('socket server is started.');
// about AF_INET: https://spyker729.blogspot.com/2010/07/afinetpfinet.html
$socketResource = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socketResource, SOL_SOCKET, SO_REUSEADDR, true);
socket_bind($socketResource, 0, PORT); // address 0 means listen every incoming
socket_listen($socketResource);

$clientSocketArray = [$socketResource];
// recordLog('before loop, now Resources are: ' . print_r($clientSocketArray, true));
while (readStatus('enable')) {
    $newClientSocketArray = $clientSocketArray;
    // here new client array will include itself created peer
    // recordLog('before socket_select, now newResources are: ' . print_r($newClientSocketArray, true));
    socket_select($newClientSocketArray, $null, $null, 0, 10);
    // after socket_select new client will remain others, since it can't read itself
    // recordLog('after socket_select, now newResources are: ' . print_r($newClientSocketArray, true));

    if (in_array($socketResource, $newClientSocketArray)) {
        $newSocket = socket_accept($socketResource);
        recordLog('Accept a Resource, now Resources are: ' . print_r($newClientSocketArray, true));
        $clientSocketArray[] = $newSocket;

        $header = socket_read($newSocket, 1024);

        socket_getpeername($newSocket, $clientIPAddress);

        $newClientSocketArrayIndex = array_search($socketResource, $newClientSocketArray);
        unset($newClientSocketArray[$newClientSocketArrayIndex]);
    }

    foreach ($newClientSocketArray as $clientResource) {
        while (socket_recv($clientResource, $socketData, 1024, 0) >= 1) {
            // do something
            // refs: https://www.php.net/manual/en/control-structures.break.php
            // optional numeric argument which tells it how many nested enclosing structures are to be broken out of.
            break 2;
        }

        $socketData = socket_read($clientResource, 1024, PHP_NORMAL_READ);
        if ($socketData === false) {
            socket_getpeername($clientResource, $clientIPAddress);
            $clientSocketArrayIndex = array_search($clientResource, $clientSocketArray);
            unset($clientSocketArray[$clientSocketArrayIndex]);
        }
    }
}

socket_close($socketResource);
recordLog('socket server is closed.');
$end = new DateTime();
print "Start at: {$start->format('Y-m-d H:i:s')}\n";
print "End at: {$end->format('Y-m-d H:i:s')}\n";

if (false) {
    clearLog();
    $ob_st = null;
    $ob_f = null;
    // $ob_st = ob_start() === true ? 'true' : 'false';
    for ($i = 0; $i < 25; $i++) {
        $rad = rand() * rand();
        // print partial output, refs: https://stackoverflow.com/questions/20718531/
        print "{$i} {$rad}\n"; // adding a newline may be important here,
        // a lot of io routines use some variant of get_line()
        // failed to delete, refs: https://stackoverflow.com/questions/14549110/
        if (ob_get_length()) ob_end_flush(); // to get php's internal buffers out into the operating system
        else print "output buffer is empty.\n";

        // the content printed by php will saved at output_buffer in normal situation
        // ob_flush(): print everything stored at php output_buffer, but not to client
        // flush(): print everything that server prepared to print, but not output_buffer of php
        print 'before ob_flush.' . "\n";
        if (ob_get_length()) $ob_f = ob_flush() === true ? 'true' : 'false';
        print 'before flush' . "\n";
        flush(); // not really work
        usleep(200000);
        recordLog("obs: {$ob_st} / obf: {$ob_f}");
    }
    $enable = readStatus('enable') === true ? 'true' : null;
    $enable = readStatus('enable') === false ? 'false' : $enable;
    // print "{$i} enable = ${enable}\n";
    // sleep(1);
}
