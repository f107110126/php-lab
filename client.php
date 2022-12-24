<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Chat Room</title>
    <script src="jquery-3.6.2.min.js"></script>
    <style>
        .main-container {
            background-color: #00FFFF2F;
            display: flex;
            align-items: center;
            justify-content: center;
            height: calc(100vh - 8px * 2);
        }

        .chat-container {
            max-height: 800px;
            max-width: 600px;
            display: flex;
            flex-direction: column;
        }

        textarea#chat-room {
            background-color: #FFFFFF7F;
            resize: none;
            flex-grow: 1;
            height: 450px;
            width: 550px;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="chat-container">
        <!--this is the container of chat history and input-->
        <textarea readonly id="chat-room">here to display the chat history
</textarea>
        <div>
            <input id="userName" type="text" placeholder="Here to input username">
            <input id="messageInput" type="text" placeholder="Here to input the text">
            <button id="sendMessage">Send Message</button>
        </div>
    </div>
</div>
<script>
    function showMessage(message) {
        $('textarea#chat-room').html(`${$('textarea#chat-room').html()}${message}\n`);
    }

    $(document).ready(function () {
        // ws://127.0.0.1:8090/demo/php-socket.php
        // ws://127.0.0.1:8090
        // above are totally same
        // server will create a service on the listened port, don't care the uri
        let websocket = new WebSocket('ws://127.0.0.1:8090/demo/php-socket.php');
        websocket.onopen = function (...params) {
            showMessage('socket is open.');
            console.log(params);
        };
        websocket.onmessage = function (event) {
            // event is MessageEvent
            if (event instanceof MessageEvent) {
                let {message, message_type} = JSON.parse(event.data);
                if (message_type === 'chat-connection-ack') showMessage(`system: ${message}`);
                if (message_type === 'chat-box-message') showMessage(message);
            }
            // showMessage('socket is received message.');
            console.log(event);
        };
        websocket.onclose = function (event) {
            // the event is CloseEvent
            showMessage('socket is closed.');
            console.log([event]);
        };
        $('#sendMessage').on('click', function (event) {
            // the event is E.Event
            event.preventDefault();
            let data = {
                user: $('input#userName').val(),
                message: $('#messageInput').val()
            };
            websocket.send(JSON.stringify(data));
            console.log([event]);
        });
    })
</script>
</body>
</html>
