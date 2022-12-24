<?php

require_once 'functions.php';

class ChatHandler
{
    protected function generateKey()
    {
        $format = 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX';
        $charset = '0123456789ABCDEF';
        $result = '';
        foreach (str_split($format) as $ch) {
            if ($ch === 'X') $result .= $charset[rand() % 16];
            else $result .= $ch;
        }
        return $result;
    }

    public function doHandshake($receivedHeader, $clientSocketResource, $hostname, $port)
    {
        $headers = [];
        $lines = preg_split("/\r\n/", $receivedHeader);
        // recordLog('lines: ' . print_r($lines, true) . "\n");

        foreach ($lines as $line) {
            $line = chop($line);
            $test = preg_match('/\A(\S+): (.*)\z/', $line, $matches);
            // $test = preg_match('/\A((.*))\z/', $line, $matches);
            // $test = preg_match('/^((.*))$/', $line, $matches);
            // recordLog("line: {$line}\n" . "test: " . ($test === 1 ? 'true' : ($test === 0 ? 'false' : 'error')) . "\n");
            // recordLog("matches: " . print_r($matches, true) . "\n");
            if ($test) {
                $headers[$matches[1]] = $matches[2];
                // for 'Sec-WebSocket-Key' !== 'Sec-Websocket-Key
                // recordLog("{$matches[1]} === 'Sec-Websocket-Key': " . ($matches[1] === 'Sec-Websocket-Key' ? 'true' : 'false'));
                // $sample = 'Sec-Websocket-Key';
                // recordLog(strlen($sample));
                // if (strlen($matches[1]) === strlen($sample)) {
                //     foreach (str_split($matches[1]) as $i => $ch) {
                //         recordLog("{$matches[1]} - {$ch} === {$sample[$i]}: " . ($ch === $sample[$i] ? 'true' : 'false'));
                //     }
                // } else {
                //     recordLog("{$matches[1]}: " . strlen($matches[1]));
                // }
            }
        }

        // recordLog("headers: " . print_r($headers, true) . "\n");

        // if (!isset($headers['Sec-Websocket-Key'])) {
        //     recordLog("Error headers: " . print_r($headers, true) . "\n");
        //     return;
        // }

        $valid = key_exists('Sec-WebSocket-Key', $headers);
        if ($valid) {
            $secKey = $headers['Sec-WebSocket-Key'];
            // this is a magic string, refers: https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers
            $encryptKey = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
            $secAccept = base64_encode(pack('H*', sha1("{$secKey}{$encryptKey}")));
            $buffer = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "WebSocket-Origin: {$hostname}\r\n" .
                "WebSocket-Location: ws://{$hostname}:{$port}\r\n" .
                "Sec-WebSocket-Accept: {$secAccept}\r\n" .
                "\r\n";
        } else {
            $buffer = "HTTP/1.1 200 OK\r\n" .
                "Access-Control-Allow-Origin: *\r\n" .
                "Date: " . date('D, d M Y H:i:s') . "\r\n" .
                "\r\n";
        }
        socket_write($clientSocketResource, $buffer, strlen($buffer));
        return $valid;
    }

    public function newConnectionACK($clientIPAddress)
    {
        $message = 'New client ' . $clientIPAddress . ' connected';
        $messageArray = [
            'message' => $message,
            'message_type' => 'chat-connection-ack'
        ];
        $ACK = $this->seal(json_encode($messageArray));
        return $ACK;
    }

    public function disConnectionACK($clientIPAddress)
    {
        $message = 'Client ' . $clientIPAddress . ' disconnected';
        $messageArray = [
            'message' => $message,
            'message_type' => 'chat-connection-ack'
        ];
        $ACK = $this->seal(json_encode($messageArray));
        return $ACK;
    }

    public function createChatBoxMessage($chatUser, $chatMessage)
    {
        $message = "{$chatUser}: {$chatMessage}";
        $messageArray = ['message' => $message, 'message_type' => 'chat-box-message'];
        $chatBoxMessage = $this->seal(json_encode($messageArray));
        return $chatBoxMessage;
    }

    public function send($message, $targets = [])
    {
        $messageLength = strlen($message);
        $result = true;
        foreach ($targets as $client) {
            recordLog('send to: ' . print_r($client, true));
            // so if an connection is invalid, will cause an error
            // and still, we can establish that, but can't handle that.
            // so we hidden it with '@'.
            $result &= !!@socket_write($client, $message, $messageLength);
        }
        return $result;
    }

    public function seal($socketData)
    {
        $b1 = 0x80 | (0x01 & 0x0f);
        $header = null;
        $length = strlen($socketData);

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        }
        if ($length > 125 && $length <= 65535) {
            $header = pack('CCn', $b1, 126, $length);
        }
        if ($length >= 65536) {
            $header = pack('CCNN', $b1, 127, $length);
        }
        return $header . $socketData;
    }

    public function unseal($socketData)
    {
        $length = ord($socketData[1]) & 127;

        // recordLog(print_r([
        //     'socketData' => bin2hex($socketData),
        //     'index 1' => bin2hex($socketData[1]),
        //     'ord[1]' => ord($socketData[1]),
        //     'result' => ord($socketData[1]) & 127
        // ], true));

        if ($length === 126) {
            $masks = substr($socketData, 4, 4);
            $data = substr($socketData, 8);
        }

        if ($length === 127) {
            $masks = substr($socketData, 10, 4);
            $data = substr($socketData, 14);
        }

        if ($length !== 126 && $length !== 127) {
            $masks = substr($socketData, 2, 4);
            $data = substr($socketData, 6);
        }

        $result = "";
        for ($i = 0; $i < strlen($data); $i++) {
            $result .= $data[$i] ^ $masks[$i % 4];
        }

        return $result;
    }
}
