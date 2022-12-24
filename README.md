# PHP Socket chat

This project is simply present the result of a socket chat based on php.

The project referenced from [here](https://phppot.com/php/simple-php-chat-using-websocket/).

So, this is not the original, the article author does.

## Conclusion

Finally, I can start a service at local, and use the `client.php` to use the service. But, when the page which trigger
the service was been closed. The service will been closed. As my
research [PHP will not detect the user has aborted until an attempt is made to send information to the client](https://www.php.net/manual/en/function.ignore-user-abort.php)
.

So, maybe next try, could try to remove any output of `server.php`, maybe, it will forever work.
