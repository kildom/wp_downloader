<?php include('version.php') ?><?php

$update_url = 'https://api.github.com/repos/kildom/wp_downloader/releases/latest';
$html_update_url = 'https://github.com/kildom/wp_downloader/releases/latest';
$html_update_regex = '/href=".*\/wp_downloader\/releases\/download\/([^"]*)\/([^"]*\.php)"/';
$html_update_prefix = 'https://github.com/kildom/wp_downloader/releases/download';
$public_key = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEIk7ZCuaV8jp+A5MxdivJM+LCqXiv\nKVQJijYssSjx5L5cvLofKa74tpdY4UF4Dfcb/8Bu6ZUN39KIj4YNHVb1KA==\n-----END PUBLIC KEY-----\n";
$backup_public_key = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEuc9S0sE8ANEFnsOCOlUZRI1jV/C1\nvUzJkwieSBzv3I4X6aHbl6YaBXwXtDeZLFW+dEMdu2HikrxOQYi6SSAqaQ==\n-----END PUBLIC KEY-----\n";

function is_valid_url($url) {
    return preg_match_all('/^https:\/\/([a-z_0-9-.]+\.)?wordpress\.org\/.*$/', $url);
}

function get_url($url, $secure = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !!$secure);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    $res = curl_exec($ch);
    if(curl_error($ch)) {
        echo(curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

function FUNC_auto_update() {
    global $version, $update_url, $html_update_url, $html_update_regex, $html_update_prefix;
    global $public_key, $backup_public_key;
    header('Content-type: text/plain');
    $update = isset($_REQUEST['update']) ? intval($_REQUEST['update']) : 0;
    $json = get_url($update_url);
    $cnt = json_decode($json, false);
    $download_url = null;
    $new_version = $cnt->tag_name;
    if (!$new_version) {
        $html = get_url($html_update_url);
        if (!preg_match($html_update_regex, $html, $m) || !$m) {
            echo($json);
            echo($html);
            echo("\nUnable to read release information\nError");
            return;
        }
        $new_version = $m[1];
        $download_url = "$html_update_prefix/$m[1]/$m[2]";
    }
    if (strnatcasecmp($version, $new_version) >= 0) {
        echo("current: $version\n");
        echo("new: $new_version\n");
        echo("update: 0\n");
        echo("OK");
        return;
    }
    if (!$update) {
        echo("current: $version\n");
        echo("new: $new_version\n");
        echo("update: 1\n");
        echo("OK");
        return;
    }
    if ($download_url === null) {
        foreach ($cnt->assets as $asset) {
            if (strrchr($asset->name, '.') == '.php') {
                $download_url = $asset->browser_download_url;
            }
        }
    }
    if ($download_url === null) {
        echo("\nAsset not found in latest release\nError");
        return;
    }
    
    $update_file = get_url($download_url);

    $pos = strrpos($update_file, '/*');
    if (!$pos) {
        echo("\nMissing signature\nError");
        return;
    }
    $sig = substr($update_file, $pos + 2);
    $data = substr($update_file, 0, $pos);
    $data = rtrim($data);
    $pos = strrpos($sig, '*/');
    if (!$pos) {
        echo("\nMissing signature\nError");
        return;
    }
    if (trim(substr($sig, $pos + 2)) != '') {
        echo("\nInvalid data after the signature\nError");
        return;
    }
    $sig = substr($sig, 0, $pos);
    $res = openssl_verify($data, base64_decode($sig), $public_key, OPENSSL_ALGO_SHA256);
    if ($res !== 1) {
        $res = openssl_verify($data, base64_decode($sig), $backup_public_key, OPENSSL_ALGO_SHA256);
    }
    if ($res !== 1) {
        echo("\nInvalid signature\nError");
        return;
    }
    file_put_contents('wp_downloader.php', $update_file); // TODO: different name
    echo($update_file);
    echo("\nOK");
}

function FUNC_download_page() {
    header('Content-type: text/plain');
    $url = stripslashes($_REQUEST['url']);
    if (!is_valid_url($url)) {
        echo("\nInvalid URL\nError");
        return;
    }
    $cnt = file_get_contents($url);
    if ($cnt === false) {
        echo("\nCannot download $url\nError");
    } else {
        echo($cnt);
        echo("\nOK");
    }
}

function progress($url, $a, $b, $c, $d)
{
    global $progress_last_value;
    if (!isset($progress_last_value) || $b > $progress_last_value + 128 * 1024) {
        echo("$b/$a" . str_repeat(' ', 1024) . "\n");
        flush();
        $progress_last_value = $b;
    }
}

function FUNC_download_release() {
    header('Content-type: text/plain');
    $url = stripslashes($_REQUEST['url']);
    if (!is_valid_url($url)) return;
    $ch = curl_init($url);
    $fp = fopen('_wp_dwnl_rel.zip', 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    @curl_exec($ch);
    if(curl_error($ch)) {
        echo("\n");
        echo(curl_error($ch));
        $output = "\nError";
    } else {
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status > 299) {
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

function FUNC_unpack() {
    header('Content-type: text/plain');
    if (isset($_REQUEST['chmod_php'])) {
        $chmod_php = intval($_REQUEST['chmod_php'], 0);
    } else {
        $chmod_php = 0644;
    }
    if (isset($_REQUEST['chmod_others'])) {
        $chmod_others = intval($_REQUEST['chmod_others'], 0);
    } else {
        $chmod_others = 0644;
    }
    if (isset($_REQUEST['dir'])) {
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
        if ($common_prefix === false) $common_prefix = $name;
        while (substr($name, 0, strlen($common_prefix)) != $common_prefix) {
            $common_prefix = substr($common_prefix, 0, -1);
        }
    }
    $common_prefix = strlen($common_prefix);
    $result = "\nOK";
    for ($i=0; $i < $za->numFiles; $i++) {
        if ($i % 20 == 0) {
            echo($i . '/' . $za->numFiles . str_repeat(' ', 1024) . "\n");
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
                chmod($path, $chmod_php);
            } else {
                chmod($path, $chmod_others);
            }
        }
    }
    echo($result);
}

function FUNC_cleanup() {
    header('Content-type: text/plain');
    $keep_downloader = isset($_REQUEST['keep_downloader']) ? intval($_REQUEST['keep_downloader']) : 0;
    if (!$keep_downloader) {
        @unlink('wp_downloader.php');
    }
    @unlink('_wp_dwnl_rel.zip');
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
    return isset($_REQUEST['test']) && !!$_REQUEST['test']; // TEST CONDITION
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
