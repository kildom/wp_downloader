<?php

function sign_and_write_json($file, $data) {
    if (!isset($_SERVER['BUILD_PRIVATE_KEY']) || $_SERVER['BUILD_PRIVATE_KEY'] == '') {
        echo("BUILD_PRIVATE_KEY environment variable not set\n");
        exit(1);
    }
    $key = str_replace('~', "\n", $_SERVER['BUILD_PRIVATE_KEY']);
    $data['signature'] = '###SIGNATURE###';
    $data = json_encode($data, JSON_PRETTY_PRINT);
    $data = str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $data))); // git may change new lines, so make sure that they are always the same
    openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
    $sig64 = strtr(base64_encode($signature), '+/', '-_');
    $data = str_replace('###SIGNATURE###', $sig64, $data);
    file_put_contents($file, $data);
}
