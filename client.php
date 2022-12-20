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
        .chat-container {
            margin: auto;
            background-color: red;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
    </style>
</head>
<body>
<div class="chat-container">
    <!--this is the container of chat history and input-->
    <div id="chat-room">
        here to display the chat history
    </div>
    <div>
        <input id="messageInput" type="text" placeholder="Here to input the text">
        <button id="sendMessage">Send Message</button>
    </div>
</div>
<script>
    function showMessage(message) {
        $('#chat-room').append(message);
    }
    $(document).ready(function(){
        // ws://127.0.0.1:8090/demo/php-socket.php
        // ws://127.0.0.1:8090
        // above are totally same
        // server will create a service on the listened port, don't care the uri
        let websocket = new WebSocket('ws://127.0.0.1:8090/demo/php-socket.php');
        websocket.onopen = function(...params) {
            showMessage('socket is open.');
            console.log(params);
        };
        websocket.onmessage = function(...params) {
            showMessage('socket is received message.');
            console.log(params);
        };
        websocket.onclose= function(event) {
            // the event is CloseEvent
            showMessage('socket is closed.');
            console.log([event]);
        };
        $('#sendMessage').on('click', function(event) {
            // the event is E.Event
            event.preventDefault();
            let data = {
                user: 'someone',
                message: $('#messageInput').val()
            };
            websocket.send(JSON.stringify(data));
            console.log([event]);
        });
    })
</script>
</body>
</html>
