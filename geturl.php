<?php

function get_url($url, $secure = true, $prepare_only = false) {
    echo("get_url $url\n");
    $ch = curl_init($url);
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, "WordPress Downloader PHP script");
    }
    if ($secure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        if (gettype($secure) == 'string') {
            echo("    cainfo $secure\n");
            curl_setopt($ch, CURLOPT_CAINFO, $secure);
        } else {
            echo("    cainfo from system\n");
        }
    } else {
        echo("    do not verify\n");
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
    echo("    " . strlen($res) . "\n");
    curl_close($ch);
    return $res;
}

function cacert_file_level($level, $check = true) {
    $file = __DIR__ . "/_wp_dwnl_cacert.$level.pem";
    if (file_exists($file) || !$check) {
        return $file;
    }
    return false;
}

function get_url_secure($url, $prepare_only = false) {
    global $cacert_url, $github_cacert_url;
    $expect_level = -1;
    while (true) {
        if (cacert_file_level(3) || $expect_level == 3) {
            return get_url($url, cacert_file_level(3), $prepare_only);
        } else if (cacert_file_level(2) || $expect_level == 2) {
            $res = get_url($url, cacert_file_level(2), $prepare_only);
            if ($res !== false) {
                return $res;
            }
            $cacert = get_url($cacert_url, cacert_file_level(2));
            if ($cacert === false) {
                return false;
            }
            file_put_contents(cacert_file_level(3, false), $cacert);
            unlink(cacert_file_level(2, false));
            $expect_level = 3;
            continue;
        } else if (cacert_file_level(1) || $expect_level == 1) {
            $res = get_url($url, cacert_file_level(1), $prepare_only);
            if ($res !== false) {
                return $res;
            }
            $cacert = get_url($github_cacert_url, cacert_file_level(1));
            if ($cacert === false) {
                $cacert = get_url($cacert_url, cacert_file_level(1));
                if ($cacert === false) {
                    return false;
                }
            }
            file_put_contents(cacert_file_level(2, false), $cacert);
            unlink(cacert_file_level(1, false));
            $expect_level = 2;
            continue;
        } else if (cacert_file_level(0)) {
            $res = get_url($url, true, $prepare_only);
            if ($res !== false) {
                return $res;
            }
            rename(cacert_file_level(0, false), cacert_file_level(1, false));
            $expect_level = 1;
            continue;
        } else {
            return get_url($url, true, $prepare_only);
        }
    }
}

?>