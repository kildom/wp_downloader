<?php include('geturl.php') ?>
<?php

$version = /*BUILDVAR:version*/'v9999.99.99'/**/;
$cacert_url = 'https://curl.se/ca/cacert.pem';
$github_cacert_url = 'https://raw.githubusercontent.com/kildom/wp_downloader/releases/cacert.pem';
$update_url = 'https://raw.githubusercontent.com/kildom/wp_downloader/releases';
$public_key = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEIk7ZCuaV8jp+A5MxdivJM+LCqXiv\nKVQJijYssSjx5L5cvLofKa74tpdY4UF4Dfcb/8Bu6ZUN39KIj4YNHVb1KA==\n-----END PUBLIC KEY-----\n";
$backup_public_key = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEuc9S0sE8ANEFnsOCOlUZRI1jV/C1\nvUzJkwieSBzv3I4X6aHbl6YaBXwXtDeZLFW+dEMdu2HikrxOQYi6SSAqaQ==\n-----END PUBLIC KEY-----\n";
$valid_period = 60 * 60 * 24 * 60;

function is_valid_url($url) {
    $res = preg_match_all('/^https:\/\/([a-z_0-9-.]+\.)?wordpress\.org\/.*$/', $url);
    if (!$res) {
        $res = preg_match_all('/^https:\/\/raw.githubusercontent.com\/kildom\/wp_downloader\/.*$/', $url);
        if (!$res) {
            echo("URL not allowed: $url");
        }
    }
    return $res;
}

function decode_and_verify($file) {
    global $public_key, $backup_public_key, $valid_period;
    $file = trim(str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $file))));
    $info = json_decode($file, false);
    $sig64 = $info->signature;
    $signature = base64_decode(strtr($sig64, '-_', '+/'));
    $file = str_replace($sig64, '###SIGNATURE###', $file);
    $res = openssl_verify($file, $signature, $public_key, OPENSSL_ALGO_SHA256);
    echo("Signature verification result: $res\n");
    if ($res !== 1) {
        $res = openssl_verify($file, $signature, $backup_public_key, OPENSSL_ALGO_SHA256);
        echo("Signature verification result with backup key: $res\n");
    }
    if ($res !== 1) {
        echo("\nInvalid signature\nError");
        return false;
    }
    if (time() - $info->timestamp > $valid_period) {
        echo("\nRelease JSON too old\nError");
        return false;
    }
    return $info;
}

function parse_size($value) {
    $value = trim($value);
    switch(strtolower($value[strlen($value) - 1])) 
    {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
        default: $val *= 1;
    }
    return $val;
}

function max_upload() {
    $max_upload = parse_size(ini_get('upload_max_filesize'));
    $max_post = parse_size(ini_get('post_max_size'));
    $memory_limit = parse_size(ini_get('memory_limit'));
    return min($max_upload, $max_post, $memory_limit);
}

function FUNC_auto_update() {
    global $version, $update_url;
    header('Content-type: text/plain');
    $update = isset($_REQUEST['update']) ? intval($_REQUEST['update']) : 0;
    $file = get_url_secure("$update_url/info.json");
    if (!$file) {
        echo("Reading release info failed. Trying connection without peer verification...\n");
        $file = get_url("$update_url/info.json", false);
        $info = decode_and_verify($file);
        if (!$info) {
            return;
        }
        echo("Applying temporary cainfo...\n");
        file_put_contents(cacert_file_level(0, false), $info->small_cert);
        $file = get_url_secure("$update_url/info.json");
    }
    if (!$file) {
        echo("\nCannot download release information file\nError");
        return;
    }
    echo("Release info content:\n$file\n--------------\n");
    $info = decode_and_verify($file);
    if (!$info) {
        return;
    }
    file_put_contents(cacert_file_level(0, false), $info->small_cert);
    $max_upload = max_upload();
    if (strnatcasecmp($version, $info->version) >= 0) {
        echo("max_upload: $max_upload\n");
        echo("current: $version\n");
        echo("new: $info->version\n");
        echo("update: 0\n");
        echo("OK");
        return;
    }
    if (!$update) {
        echo("max_upload: $max_upload\n");
        echo("current: $version\n");
        echo("new: $info->version\n");
        echo("update: 1\n");
        echo("OK");
        return;
    }

    $update_file = get_url_secure("$update_url/$info->name");
    if (!$update_file) {
        echo("\nCannot download update file\nError");
        return;
    }
    $update_hash = hash('sha256', $update_file);

    if (strtolower($update_hash) != strtolower($info->hash)) {
        echo("\nInvalid hash of the update file\nError");
        return;
    }

    file_put_contents('wp_downloader.php', $update_file); // TODO: different name
    echo("<<<<<=====$update_file=====>>>>>");
    echo("\nOK");
}

function FUNC_download_page() {
    header('Content-type: text/plain');
    $url = stripslashes($_REQUEST['url']);
    if (!is_valid_url($url)) {
        echo("\nInvalid URL\nError");
        return;
    }
    $cnt = get_url_secure($url);
    if ($cnt === false) {
        echo("\nCannot download $url\nError");
    } else {
        echo("<<<<<=====$cnt=====>>>>>");
        echo("\nOK");
    }
}

function progress($url, $a, $b, $c, $d)
{
    global $progress_last_value;
    if (!isset($progress_last_value) || $b > $progress_last_value + 128 * 1024) {
        echo("=>$b/$a" . str_repeat(' ', 1024) . "\n");
        flush();
        $progress_last_value = $b;
    }
}

function FUNC_download_release() {
    header('Content-type: text/plain');
    $url = stripslashes($_REQUEST['url']);
    if (!is_valid_url($url)) return;
    $ch = get_url_secure($url, true);
    $fp = fopen('_wp_dwnl_rel.zip', 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    @curl_exec($ch);
    if(curl_error($ch)) {
        echo("\n");
        echo(curl_error($ch));
        $output = "\nError";
    } else {
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            $output = "\nHTTP status code: $status\nError";
        } else {
            $output = "\nOK";
        }
    }
    curl_close($ch);
    fclose($fp);
    echo($output);
}

function FUNC_get_hash() {
    header('Content-type: text/plain');
    if (!file_exists('_wp_dwnl_rel.zip')) {
        $sha = '0';
    } else {
        $sha = @sha1_file('_wp_dwnl_rel.zip');
    }
    if ($sha === false) {
        echo("\nCannot read downloaded ZIP file\nError");
    } else {
        echo($sha);
        echo("\nOK");
    }
}

function FUNC_get_crc32() {
    header('Content-type: text/plain');
    if (!file_exists('_wp_dwnl_rel.zip')) {
        $crc = '0';
    } else {
        $crc = @hash_file('crc32b', '_wp_dwnl_rel.zip', false);
    }
    if ($crc === false) {
        echo("\nCannot read uploaded ZIP file\nError");
    } else {
        echo("$crc\nOK");
    }
}

function FUNC_unpack() {
    header('Content-type: text/plain');
    if (isset($_REQUEST['chmod_php'])) {
        $chmod_php = intval($_REQUEST['chmod_php'], 0);
    } else {
        $chmod_php = -1;
    }
    if (isset($_REQUEST['chmod_others'])) {
        $chmod_others = intval($_REQUEST['chmod_others'], 0);
    } else {
        $chmod_others = -1;
    }
    if (isset($_REQUEST['dir']) && strlen(trim($_REQUEST['dir'])) > 0) {
        $dir = preg_replace('/[^a-z0-9_.,=+-]/i', '_', $_REQUEST['dir']);
    } else {
        $dir = '.';
    }
    $verify = isset($_REQUEST['verify']) ? intval($_REQUEST['verify']) : 0;
    $za = new ZipArchive();
    if ($za->open('_wp_dwnl_rel.zip') !== true) {
        echo("\nCannot open downloaded ZIP file\nError");
        return;
    }
    $common_prefix = false;
    for ($i=0; $i < $za->numFiles; $i++) {
        $name = $za->getNameIndex($i);
        if (substr($name, -1) == '/') continue;
        $pos = strrpos($name, '/');
        if ($pos === false) {
            $common_prefix = '';
            break;
        }
        $name = substr($name, 0, $pos + 1);
        if ($common_prefix === false) $common_prefix = $name;
        while (substr($name, 0, strlen($common_prefix)) != $common_prefix) {
            $common_prefix = substr($common_prefix, 0, -1);
        }
    }
    $common_prefix = strlen($common_prefix);
    $result = "\nOK";
    for ($i=0; $i < $za->numFiles; $i++) {
        if ($i % 20 == 0) {
            echo("=>$i/" . $za->numFiles . str_repeat(' ', 1024) . "\n");
            flush();
        }
        $name = $za->getNameIndex($i);
        if (substr($name, -1) == '/') continue;
        $name = substr($name, $common_prefix);
        $content = $za->getFromIndex($i);
        if ($za->status != ZipArchive::ER_OK) {
            $result = "\nZIP error: " . $za->getStatusString() . "\nError";
            break;
        }
        $path = $dir . '/' . $name;
        if (devel_mode()) {
            $path = "temp/$path";
        }
        if ($verify) {
            if (!file_exists($path)) {
                $result = "\nFile $path does not exists\nError";
                break;
            }
            $cnt2 = @file_get_contents($path);
            if ($cnt2 === false) {
                $result = "\nCannot read file $path\nError";
                break;
            }
            if ($cnt2 !== $content) {
                $result = "\nFile $path has invalid contents\nError";
                break;
            }
        } else {
            $dirpath = dirname($path);
            @mkdir($dirpath, 0777, true);
            if (@file_put_contents($path, $content) === false) {
                $result = "\nCannot write file $path\nError";
                break;
            }
            if (substr($name, -4) == '.php') {
                if ($chmod_php >= 0) {
                    chmod($path, $chmod_php);
                }
            } else {
                if ($chmod_others >= 0) {
                    chmod($path, $chmod_others);
                }
            }
        }
    }
    echo($result);
}

function FUNC_cleanup() {
    header('Content-type: text/plain');
    $keep_downloader = isset($_REQUEST['keep_downloader']) ? intval($_REQUEST['keep_downloader']) : 0;
    if (!devel_mode()) {
        if (!$keep_downloader) {
            @unlink(__FILE__);
        }
        @unlink(cacert_file_level(0, false));
        @unlink(cacert_file_level(1, false));
        @unlink(cacert_file_level(2, false));
        @unlink(cacert_file_level(3, false));
        @unlink(cacert_file_level(4, false));
    }
    @unlink('_wp_dwnl_rel.zip');
    echo("\nOK");
}

function FUNC_upload() {
    header('Content-type: text/plain');
    $crc1 = hash_file('crc32b', $_FILES['file']['tmp_name'], false);
    $crc2 = strrev(substr(strrev(dechex($_POST['crc32'])) . '00000000', 0, 8));
    if ($crc1 != $crc2) {
        echo("\nInvalid CRC\nError");
        return;
    }
    $src = fopen($_FILES['file']['tmp_name'], 'rb');
    $dst = fopen('_wp_dwnl_rel.zip', 'cb+');
    if (!$src || !$dst) {
        echo("\nFile IO error\nError");
        return;
    }
    flock($dst, LOCK_EX);
    fseek($dst, 0, SEEK_END);
    $file_size = ftell($dst);
    $expected_size = intval($_POST['total']);
    if ($file_size != $expected_size) {
        ftruncate($dst, $expected_size);
    }
    $start = intval($_POST['start']);
    $end = intval($_POST['end']);
    fseek($dst, $start);
    $len = $end - $start;
    while ($len > 0) {
        $buf = fread($src, min(65536, $len));
        $n = fwrite($dst, $buf);
        if ($buf === false || strlen($buf) == 0 || $n != strlen($buf)) {
            echo("\nFile IO error\nError");
            flock($dst, LOCK_UN);
            return;
        }
        $len -= $n;
    }
    flock($dst, LOCK_UN);
    fclose($dst);
    fclose($src);
    unlink($_FILES['file']['tmp_name']);
    echo("\nOK");
}

function FUNC_test_result() {
    header('Content-type: text/plain');
    if (!do_test()) return;
    file_put_contents('_wp_dwnl_result.txt', stripslashes($_REQUEST['result']));
    echo("\nOK");
}

function show_page() {
    ?><?php include('page.php') ?><?php
}

function do_test() {
    return /*BUILDVAR:test*/isset($_REQUEST['test']) && !!$_REQUEST['test']/**/;
}

function devel_mode() {
    return /*BUILDVAR:devel*/true/**/;
}

if (isset($_REQUEST['func'])) {
    $func = 'FUNC_' . $_REQUEST['func'];
    if (function_exists($func)) {
        $func();
    } else {
        header('Content-type: text/plain');
        echo("\nInvalid function\nError");
    }
} else {
    show_page();
}
