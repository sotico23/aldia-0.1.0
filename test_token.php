<?php

// Test with invalid token to see Telegram's response format
$ch = curl_init('https://api.telegram.org/bot123456789:ABCdef/getMe');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo 'HTTP: '.$code.PHP_EOL;
echo 'Error: '.($err ?: 'none').PHP_EOL;
echo 'Result: '.$result.PHP_EOL;
