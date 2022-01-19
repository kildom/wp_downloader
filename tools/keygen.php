<?php

$new_key_pair = openssl_pkey_new(array(
    "private_key_type" => OPENSSL_KEYTYPE_EC,
    "curve_name" => 'prime256v1'
));
openssl_pkey_export($new_key_pair, $private_key_pem);
$private_key_pem = str_replace("\r\n", "~", $private_key_pem);
$private_key_pem = str_replace("\n", "~", $private_key_pem);
echo("\n$private_key_pem\n");
$details = openssl_pkey_get_details($new_key_pair);
$public_key_pem = $details['key'];
$public_key_pem = str_replace("\r\n", '\n', $public_key_pem);
$public_key_pem = str_replace("\n", '\n', $public_key_pem);
echo("\n$public_key_pem\n\n");
