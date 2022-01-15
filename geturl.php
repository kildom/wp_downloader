<?php

function get_url($url, $secure = true, $prepare_only = false) {
    $ch = curl_init($url);
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, "WordPress Downloader PHP script");
    }
    if ($secure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        if (file_exists(__DIR__ . "/_wp_dwnl_cacert.pem")) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/_wp_dwnl_cacert.pem");
        }
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    if ($prepare_only) {
        return $ch;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        echo("\n" . curl_error($ch) . "\n");
        $res = false;
    } else {
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            echo("\nHTTP status code: $status\n");
            $res = false;
        }
    }
    curl_close($ch);
    return $res;
}

function cacert_exists()
{
    return file_exists(__DIR__ . "/_wp_dwnl_cacert.pem");
}

?>