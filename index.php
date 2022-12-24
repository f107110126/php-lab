<?php

require_once 'src/config.php';
require_once 'src/functions.php';

$serviceStatus = is_resource(@fsockopen(HOST_NAME, PORT));
$html = '';

if ($serviceStatus) {
    $html = '<p id="serviceStatus">Service is running!</p>' .
        '<button id="serviceSwitch">go stop</button>';
} else {
    $html = '<p id="serviceStatus">Service is not running!</p>' .
        '<button id="serviceSwitch">go start</button>';
}

$parseData = json_encode([
    'host' => HOST_NAME,
    'port' => PORT
]);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>SocketDemo</title>
    <script src="jquery-3.6.2.min.js"></script>
    <style>
        .main-container {
            min-height: calc(100vh - 8px * 2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div>
        <?= $html ?>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <a href="client.php" target="_blank">Open Client</a>
    </div>
</div>
<script>
    let {host, port} = JSON.parse('<?=$parseData?>');
    const connectStatus = () => {
            return (new Promise((resolve, reject) => {
                let protocol = (new URL(document.URL)).protocol;
                fetch(`${protocol}//${host}:${port}`)
                    // fetch(`client.php`)
                    .then(response => {
                        //  console.log(response, response?.body?.getReader()?.read());
                        return response?.body?.getReader()?.read();
                    })
                    .then(({value, done}) => {
                        console.log((new TextDecoder()).decode(value))
                        resolve(true);
                    })
                    .catch((...args) => {
                        console.warn(args);
                        resolve(false);
                    });
            }));
        },
        updateHTML = (enable) => {
            console.log(enable);
            // window.pStatus = $('p#serviceStatus');
            // window.bSwitch = $('button#serviceSwitch');
            if (enable) {
                $('p#serviceStatus').html('Service is running!');
                $('button#serviceSwitch').html('go stop');
            } else {
                $('p#serviceStatus').html('Service is not running!');
                $('button#serviceSwitch').html('go start');
            }
        };
    $(document).on('click', 'button#serviceSwitch', (event) => {
        connectStatus().then(result => {
            if (result) {
                fetch('server.php?enable=false')
                    .then(response => connectStatus().then(updateHTML))
            } else {
                fetch('server.php?enable=true')
                    .then(response => {
                        let eventSource = new EventSource('server.php'),
                            checked = false;
                        eventSource.onopen = (...args) => console.log('es is open', args);
                        eventSource.onmessage = (...args) => {
                            if (checked === false) {
                                connectStatus().then(updateHTML);
                                checked = true;
                            }
                            return console.log('es receive message', args);
                        };
                        eventSource.onerror = (...args) => {
                            eventSource.close();
                            connectStatus().then(updateHTML);
                            return console.log('es encounter error', args);
                        };
                    })
            }
        });
    });
</script>
</body>
</html>
