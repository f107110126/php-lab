<?php

// refs: https://tutorialsclass.com/faq/how-can-we-set-infinite-execution-time-in-php-script/
set_time_limit(0);

require_once 'src/ChatHandler.php';
require_once 'src/config.php';
require_once 'src/functions.php';

header("Cache-Control: no-store");
header('Content-Type: text/event-stream; charset=utf-8');

recordLog('Server.php has been executed.');

$null = null;

if (isset($_GET['enable'])) {
    if ($_GET['enable'] === 'true') writeStatus('enable', true);
    if ($_GET['enable'] === 'false') writeStatus('enable', false);
    return;
}

if (isset($_GET['cmd'])) {
    if ($_GET['cmd'] === 'clear') clearLog();
    return;
}

if (is_resource(@fsockopen(HOST_NAME, PORT))) {
    recordLog('Previous Service is running, exit.');
    sendEvent('Previous Service is running, exit.', true);
    return;
}

print ": normal execution\n";
$start = new DateTime();
$first_date = new DateTime($start->format('Y-m-d H:i:s'));
// about AF_INET: https://spyker729.blogspot.com/2010/07/afinetpfinet.html
$socketResource = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socketResource, SOL_SOCKET, SO_REUSEADDR, true);
socket_bind($socketResource, 0, PORT); // address 0 means listen every incoming
socket_listen($socketResource);
recordLog('socket server is started.');
sendEvent('socket server is started.', true);

$chatHandler = new ChatHandler();

$clientSocketArray = [$socketResource];
// recordLog('before loop, now Resources are: ' . print_r($clientSocketArray, true));
while (readStatus('enable')) {
    $newClientSocketArray = $clientSocketArray;
    $oldClientSocketArray = $clientSocketArray;
    // here new client array will include itself created peer
    // recordLog('before socket_select, now newResources are: ' . print_r($newClientSocketArray, true));
    socket_select($newClientSocketArray, $oldClientSocketArray, $null, 0, 10);
    // after socket_select new client will remain others, since it can't read itself
    // recordLog('after socket_select, now newResources are: ' . print_r($newClientSocketArray, true));

    if (connection_aborted()) recordLog('Connection aborted');

    $diff = (new DateTime())->diff($first_date);
    if (($diff->i * 60 + $diff->s) > 5) {
        $first_date = new DateTime();
        recordLog('Server is executing');
        // recordLog('Server Status: ' . print_r([
        //         'clientSocketArray' => $clientSocketArray,
        //         'newClientSocketArray' => $newClientSocketArray,
        //         'oldClientSocketArray' => $oldClientSocketArray,
        //     ], true));
        sendEvent('Service is executing', true);
        // print "data: Service is executing\n";
        // if (ob_get_length()) ob_flush();
        // flush(); // not really work
    }

    if (in_array($socketResource, $newClientSocketArray)) {
        $newSocket = socket_accept($socketResource);
        recordLog('Accept a Resource, now Resources are: ' . print_r($newClientSocketArray, true));

        $header = socket_read($newSocket, 1024);
        $valid = $chatHandler->doHandshake($header, $newSocket, HOST_NAME, PORT);
        recordLog("header:\n" . ($header === false ? 'false' : $header) . "\n");

        if ($valid) {
            $clientSocketArray[] = $newSocket;
            $oldClientSocketArray[] = $newSocket;

            socket_getpeername($newSocket, $clientIPAddress);
            $connectionACK = $chatHandler->newConnectionACK($clientIPAddress);

            recordLog('New connect Server Status: ' . print_r([
                    'clientSocketArray' => $clientSocketArray,
                    'newClientSocketArray' => $newClientSocketArray,
                    'oldClientSocketArray' => $oldClientSocketArray,
                ], true));
            $chatHandler->send($connectionACK, $oldClientSocketArray);

        } else {
            recordLog('Invalid connection, abandon.');
            sendEvent('Invalid connection, abandon.', true);
            socket_close($newSocket);
        }

        $newClientSocketArrayIndex = array_search($socketResource, $newClientSocketArray);
        unset($newClientSocketArray[$newClientSocketArrayIndex]);
    }

    foreach ($newClientSocketArray as $clientResource) {
        while (socket_recv($clientResource, $socketData, 1024, 0) >= 1) {
            // recordLog(print_r(['socketData' => $socketData], true));
            $socketMessage = $chatHandler->unseal($socketData);
            // recordLog(print_r(['socketMessage' => $socketMessage], true));
            $messageObject = json_decode($socketMessage);

            if ($messageObject->user === null || $messageObject->message === null) break 2;

            $chatBoxMessage = $chatHandler->createChatBoxMessage($messageObject->user, $messageObject->message);
            recordLog(print_r([
                'messageObject' => $messageObject,
                'chatBoxMessage' => $chatBoxMessage
            ], true));
            $chatHandler->send($chatBoxMessage, $oldClientSocketArray);

            // refs: https://www.php.net/manual/en/control-structures.break.php
            // optional numeric argument which tells it how many nested enclosing structures are to be broken out of.
            break 2;
        }

        // there are variety situation will cause warning
        // like client close the connect
        // and there is no way to prevent it, even we can predicate it.
        // so we hidden the message, just handle if that was going wrong
        $socketData = @socket_read($clientResource, 1024, PHP_NORMAL_READ);
        if ($socketData === false) {
            socket_getpeername($clientResource, $clientIPAddress);

            $disconnectionACK = $chatHandler->disConnectionACK($clientIPAddress);
            $chatHandler->send($disconnectionACK);

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
