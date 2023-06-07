<?php 
 
function encrypt($msg) 
{
    $key = "v6jM4s0hYqa52m8Bt5c1z7Celv6hFbnF";
    $nonce = "mf5cOb6g19QRbX93bD6Fpj5d";
    return sodium_crypto_secretbox($msg, $nonce, $key);
}
 
$chunkSize = 1024;
$src = fopen('vid.mp4', 'rb');
$dst = fopen('file', 'wb');
 
while (!feof($src)) {
    $str = fread($src, $chunkSize); 
    $str = encrypt($str); 
    fwrite($dst, $str);
}
 
fclose($src);
fclose($dst);
 
?>
