<?php

function is_valid_url($url) {
    return preg_match_all('/^https:\/\/([a-z_0-9-.]+\.)?wordpress\.org\/.*$/', $url);
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
    if ($za->open('_wp_dwnl_rel.zip', ZipArchive::RDONLY) !== true) {
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
    ?>
    <?php include('page.php') ?>
    <?php
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
