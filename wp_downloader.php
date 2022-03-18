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
<?php

$version = 'v0.0.11';
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
    ?><html>
<head>
<script>

let devel_mode = false;

function sleep(ms) {
    return new Promise(resolve => {
        setTimeout(resolve, ms);
    });
}

function interpret_download(resolve, reject, response) {
    response = response.trimEnd();
    let pos = response.lastIndexOf('\n');
    if (pos < 0) {
        reject(Error('Invalid response from server'));
        return;
    }
    let result = response.substr(pos + 1);
    response = response.substr(0, pos);
    if (result.trim() == 'Error') {
        console.log(`Response: ${response}`);
        pos = response.lastIndexOf('\n');
        if (pos < 0) pos = 0;
        let message = response.substr(pos + 1);
        reject(Error(`Error message from server: ${message}`));
        return;
    }
    if (result.trim() != 'OK') {
        console.log(`Response: ${response}`);
        reject(Error('Invalid response from server'));
        return;
    }
    let log = response;
    let start = response.indexOf('<<<<<=====');
    if (start >= 0) {
        let end = response.lastIndexOf('=====>>>>>');
        if (end >= 0) {
            let part = response.substring(start + 10, end);
            log = `${response.substring(0, start + 10)} ... ${response.substring(end)}`;
            response = part;
        }
    }
    log = log.replace(/\n=>[0-9]+\/[0-9]+ +/g, '');
    console.log(`Response: ${log}`);
    resolve(response);
}

function download(func, data, progress, upload) {
    return new Promise((resolve, reject) => {
        let body;
        if (upload) {
            body = new FormData();
            body.append('func', func);
            for (let [key, value] of Object.entries(data)) {
                body.append(key, value);
            }
            console.log(`Requesting: ${new URLSearchParams(body)}`);
        } else {
            body = Object.entries(data)
                .concat([['func', func]])
                .map(([key, val]) => encodeURIComponent(key) + '=' + encodeURIComponent(val))
                .join('&');
            console.log(`Requesting: ${body}`);
        }
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if (this.readyState === XMLHttpRequest.DONE) {
                if (this.status === 200 && (this.responseType === "text" || this.responseType === "")) {
                    interpret_download(resolve, reject, this.responseText);
                } else {
                    reject(Error('Communication with server error.'));
                }
            }
        }
        if (progress) {
            let re = new RegExp(/\n=>([0-9]+)\/([0-9]+) +/mg);
            xhr.onprogress = function () {
                if (this.readyState === XMLHttpRequest.LOADING) {
                    let part = this.responseText;
                    let next;
                    let m = null;
                    while ((next = re.exec(part)) != null) { m = next; };
                    if (!m) return;
                    progress(parseFloat(m[1]), parseFloat(m[2]));
                }
            }
        }
        xhr.open("POST", '?');
        if (!upload) {
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        }
        xhr.send(body);
    });
}

let lastLogLine = null;

function logText(text) {
    let screen = document.querySelector('#screen-progress');
    if (lastLogLine !== null) {
        let last = lastLogLine;
        //setTimeout(() => screen.removeChild(last), 7000); // TODO: animation
    }
    let line = document.createElement('div');
    let content = document.createElement('div');
    content.className = 'log-text';
    line.appendChild(content);
    lastLogLine = line;
    content.innerText = text;
    screen.appendChild(line); // TODO: animation
    screen.scrollBy(0, 100000);
    console.log('%c' + text, 'color: green');
}

function logAppend(text) {
    if (lastLogLine == null) logText('');
    let content = lastLogLine.querySelector('div.log-text');
    content.innerText += text;
    console.log('%c   ... ' + text, 'color: green');
}

function logProgress(frac) {
    if (lastLogLine == null) logText('');
    let bar = lastLogLine.querySelector('div.progress-bar');
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'progress-bar';
        bar.appendChild(document.createElement('div'));
        lastLogLine.appendChild(bar);
    }
    let prog = bar.querySelector('div');
    prog.style.width = Math.round(Math.min(100, Math.max(0, 100 * frac))) + '%';
}

function switchScreen(name) {
    for (let e of document.querySelectorAll('.screen')) {
        e.style.display = 'none';
    }
    document.querySelector(`#screen-${name}`).style.display = 'block';
}

let releases_lang = 'English (default)';
let lang_names_url = 'https://raw.githubusercontent.com/kildom/wp_downloader/releases/codes.json';
let releases_url = 'https://wordpress.org/download/releases/';
let releases_default_url = releases_url;
let zips;
let selected_zip;
let subfolder = '';
let chmod = false;
let chmodPHP = 0755;
let chmodOther = 0644;
let languages = null;
let max_upload = 1024 * 1024;

function getFolder(href) {
    let url = new URL(href);
    url.hash = '';
    url.search = '';
    let path = url.pathname;
    let pos = path.lastIndexOf('/');
    if (pos >= 0) {
        path = path.substring(0, pos + 1);
    }
    url.pathname = path;
    return url.href;
}

function show_options() {
    let ver = selected_zip.version;
    if (selected_zip == zips[0]) ver += ' (latest)';
    document.querySelector('#version').innerText = ver;
    document.querySelector('#lang').innerText = releases_lang;
    let url = getFolder(location.href);
    if (devel_mode) {
        url += 'temp/';
    }
    if (subfolder != '') {
        url += `${subfolder}/`;
    }
    document.querySelector('#destination').innerText = url;
    document.querySelector('#adv-options').innerText = chmod ? `chmod ${numToChmod(chmodOther)} *.*; chmod ${numToChmod(chmodPHP)} *.php` : 'None'
    switchScreen('options');
}

function extract_wpd_version(text, name) {
    return null;
}

async function main() {
    switchScreen('progress');
    logText(`Checking for WordPress Downloader updates...`);
    let current_wpd_version = 'unknown';
    let new_wpd_version = 'unknown';
    let update_needed = false;
    try {
        let update_info = await download('auto_update', {});
        let update_response = {};
        for (let m of update_info.matchAll(/^([a-z]+):\s*(.*)$/gm)) {
            update_response[m[1].trim()] = m[2].trim();
        }
        current_wpd_version = update_response.current;
        new_wpd_version = update_response['new'];
        update_needed = !!parseInt(update_response.update);
        max_upload = parseInt(update_response.max_upload);
        logAppend(` OK`);
        logText(`Current version ${current_wpd_version}`);
        logText(`Latest version ${new_wpd_version}`);
    } catch (ex) {
        logAppend(' ERROR - skipping automatic updates');
        console.warn(ex);
    }

    if (!update_needed) {
        await load_releases();
        return;
    }

    document.querySelector('#current-wpd-version').innerText = current_wpd_version;
    document.querySelector('#new-wpd-version').innerText = new_wpd_version;
    switchScreen('update');
}

async function do_update(with_download) {
    switchScreen('progress');
    logText(`Updating WordPress Downloader...`);
    let new_file = await download('auto_update', { update: 1 });
    logAppend(' OK');
    logText(`Reloading the page...`);
    setTimeout(() => location.reload(), 1000);
    if (with_download) {
        let element = document.createElement('a');
        element.setAttribute('href', 'data:application/octet-stream,' + encodeURIComponent(new_file));
        element.setAttribute('download', 'wp_downloader.php');
        element.style.display = 'none';
        document.body.appendChild(element);
        element.click();
        document.body.removeChild(element);
    }
}

async function load_releases() {
    switchScreen('progress');
    let releases_page;
    logText(`Loading releases from: ${releases_url}...`);
    releases_page = await download('download_page', { url: releases_url });
    logAppend(' OK');
    zips = [];
    let added = {};
    for (let match of releases_page.matchAll(/href="((?:[^"]*\/)?(?:wordpress-)?([^"]*?)(([0-9]+)\.([0-9]+)(?:\.[0-9]+){0,2})([^"]*)\.zip)"/gi)) {
        // groups: 1 - URL, 2 - version prefix, 3 - version number, 4 - version major, 5 - version minor, 6 - version postfix
        let url = (new URL(match[1], releases_url)).href;
        if (url in added) {
            continue;
        }
        zips.push({
            versionInt: match[3]
                .split('.')
                .map(x => parseInt(x))
                .concat([0, 0, 0, 0, 0])
                .slice(0, 4)
                .reduce((s, x) => s * 1000 + x, 0),
            version: match[2] + match[3] + match[6],
            versionPrefix: match[2],
            versionNumber: match[3],
            versionMajor: match[4],
            versionMinor: match[5],
            versionPostfix: match[6],
            url: url,
            index: zips.length,
        });
        added[url] = null;
    }
    //zips.sort((a, b) => b.version_num - a.version_num); TODO may be used to find latest beta or RC
    if (zips.length == 0) {
        if (releases_url == releases_default_url) {
            throw Error('Cannot parse releases page!');
        } else {
            // TODO: inform that no releases ware found in this language
            logText('No releases found in this language. Switching to default.');
            releases_url = releases_default_url;
            await load_releases();
            return;
        }
    }
    selected_zip = zips[0];
    logText(`Found ${zips.length} releases. Latest is ${selected_zip.version}.`);
    if (languages === null) {
        languages = [];
        let def_url = null;
        for (let match of releases_page.matchAll(/<link[^>]*\s+rel="alternate"[^>]*\s+href="(.*?)"[^>]*\s+hreflang="(.*?)"/gi)) {
            let url = (new URL(match[1], releases_url)).href;
            if (match[2] == 'x-default') {
                def_url = url;
                continue;
            }
            languages.push({
                url: url,
                code: match[2],
            });
        }
        if (def_url) {
            languages.unshift({
                url: def_url,
                code: 'default',
            });
        }
        logText(`Found ${languages.length} languages.`);
    }
    show_options();
}

function parseHash(hash) {
    hash = hash.trim();
    hash = [...hash.matchAll(/[0-9A-F]{40}/gi)];
    if (!hash || hash.length == 0) throw Error(`Cannot parse hash response`);
    return hash[hash.length - 1][0].toUpperCase();
}

async function install() {
    switchScreen('progress');

    logText(`Downloading SHA1 from ${selected_zip.url}.sha1...`);
    let hash = await download('download_page', { url: `${selected_zip.url}.sha1` });
    hash = parseHash(hash);
    logAppend(` OK ${hash}`);

    logText(`Downloading ZIP file from ${selected_zip.url}...`);
    await download('download_release', { url: selected_zip.url }, (done, total) => {
        if (done > 0 && total >= done) {
            logProgress(done / total);
        }
    });
    logProgress(1);
    logAppend(' OK');

    logText(`Verifying hash of the ZIP file...`);
    let zip_hash = await download('get_hash', {});
    zip_hash = parseHash(zip_hash);
    if (zip_hash != hash) throw Error(`Hashes does not match. Expected: ${hash}, downloaded: ${zip_hash}`);
    logAppend(' OK');

    await unpack();
}

async function unpack() {
    logText(`Unpacking the ZIP file...`);
    let unpackOptions = { dir: subfolder };
    if (chmod) {
        unpackOptions.chmod_php = numToChmod(chmodPHP);
        unpackOptions.chmod_others = numToChmod(chmodOther);
    }
    await download('unpack', unpackOptions, (done, total) => {
        if (done > 0 && total >= done) {
            logProgress(done / total);
        }
    });
    logProgress(1);
    logAppend(' OK');

    logText(`Verifying unpacked files...`);
    await download('unpack', { dir: subfolder, verify: 1 }, (done, total) => {
        if (done > 0 && total >= done) {
            logProgress(done / total);
        }
    });
    logProgress(1);
    logAppend(' OK');

    await cleanup();

    startWordPressInstaller();
}

function startWordPressInstaller() {
    logText(`Redirecting to the installer...`);
    let path = subfolder;
    if (path.length) {
        path += '/';
    }
    if (devel_mode) {
        path = 'temp/' + path;
    }
    setTimeout(() => { location.href = path; }, 1000);
}

async function cleanup(force) {

    logText(`Removing the ZIP file and a downloader itself...`);
    await download('cleanup', { keep_downloader: 1 });
    try {
        let zip_hash = await download('get_hash', {});
        if (zip_hash != '0') throw Error(`Cannot delete downloader temporary files`);
    } catch (ex) {
        if (force) await download('cleanup', {});
        throw ex;
    }
    await download('cleanup', {});
    logAppend(' OK');
}

async function retry() {
    switchScreen('progress');
    logText(`Retry started`);
    logText(`Removing the ZIP file if exists...`);
    await download('cleanup', { keep_downloader: 1 });
    logAppend(' OK');
    logText(`Reloading the page...`);
    setTimeout(() => location.reload(), 1000);
}

async function abort() {
    switchScreen('progress');
    logText(`Abort started`);
    try {
        await cleanup(true);
        logText(`Abort successful. Downloader and its temporary files are removed.`);
        logText(`If there are partially unpacked WordPress files, they will remain on
                the server. You have to delete them manually.`);
    } catch (ex) {
        logText(`Abort unsuccessful! Downloader and its temporary files may still be
                 present on the server. It is not safe to keep them there. Please
                 delete them manually.`);
    }
    logText(`Sorry we couldn't help you with the installation. If you think this
            was caused by a bug in the downloader, report it on:
            https://github.com/kildom/wp_downloader/`);
}

async function setFolder() {
    let popup = document.querySelector('#popup-folder');
    document.querySelector('#folder').value = subfolder;
    popup.style.display = 'block';
    document.querySelector('#folder').focus();
}

async function setRelease() {
    let popup = document.querySelector('#popup-releases');
    let html = '';
    let majorGroups = {};
    let minorGroups = {};
    for (let zip of zips) {
        if (!(zip.versionMajor in majorGroups)) {
            majorGroups[zip.versionMajor] = {};
        }
        if (!(zip.versionMinor in majorGroups[zip.versionMajor])) {
            majorGroups[zip.versionMajor][zip.versionMinor] = [];
        }
        majorGroups[zip.versionMajor][zip.versionMinor].push(zip);
    }
    html += `<div id="main-version" class="version-panel">Major version: &nbsp; `;
    for (let major of Object.keys(majorGroups).sort((a, b) => b - a)) {
        html += `<a href="javascript:// Set major version ${major}.x" onclick="wrap(setMajorVersion, '${major}')">${major}.x</a> &nbsp; `;
    }
    html += `</div>`;
    let minorHtml = '<div id="minor-version--empty-" class="minor-version-panel"></div>';
    for (let [major, value] of Object.entries(majorGroups)) {
        html += `<div id="major-version-${major}" class="major-version-panel">Minor version: &nbsp; `;
        for (let minor of Object.keys(value).sort((a, b) => b - a)) {
            html += `<a href="javascript:// Set minor version ${major}.${minor}" onclick="wrap(setMinorVersion, '${major}.${minor}')">${major}.${minor}</a> &nbsp; `;
            minorHtml += `<div id="minor-version-${major}-${minor}" class="minor-version-panel">`;
            for (let zip of value[minor]) {
                minorHtml += `<div class="version-item"><a href="javascript:// Set version ${zip.version}" onclick="wrap(setVersion, ${zip.index})">${zip.version}</a></div>`;
            }
            minorHtml += `</div>`;
        }
        html += `</div>`;
    }
    document.querySelector('#releases-list').innerHTML = html + minorHtml;
    setMajorVersion(selected_zip.versionMajor);
    setMinorVersion(`${selected_zip.versionMajor}.${selected_zip.versionMinor}`);
    popup.style.display = 'block';
}

async function setMajorVersion(major) {
    for (let e of document.querySelectorAll('.major-version-panel')) {
        e.style.display = 'none';
    }
    for (let e of document.querySelectorAll('.minor-version-panel')) {
        e.style.display = 'none';
    }
    document.querySelector(`#major-version-${major}`).style.display = '';
    document.querySelector(`#minor-version--empty-`).style.display = '';
}

async function setMinorVersion(minor) {
    for (let e of document.querySelectorAll('.minor-version-panel')) {
        e.style.display = 'none';
    }
    document.querySelector(`#minor-version-${minor.replace('.', '-')}`).style.display = '';
}

async function setVersion(index) {
    if (typeof (index) == 'number') {
        selected_zip = zips[index];
    }
    show_options();
    document.querySelector('#popup-releases').style.display = 'none';
}

let langNames = null;

function genLangLabels(map, id, cssPrefix) {
    let names = [id];
    if (id in map) {
        names = map[id];
        while (typeof (names) == 'string') {
            names = map[names];
        }
    }
    let html = '';
    for (let i = 0; i < names.length; i++) {
        html += `<span class="${cssPrefix}-select${i == 0 ? '-eng' : ''}">${names[i]}</span>`;
    }
    return html;
}

async function setLanguage() {
    if (langNames == null) {
        switchScreen('progress');
        logText(`Downloading language and region names from ${lang_names_url}...`);
        langNames = JSON.parse(await download('download_page', { url: lang_names_url }));
        langNames.lang['default'] = ['(Default)'];
        logAppend(' OK');
        switchScreen('options');
    }
    if (!('html' in languages[0])) {
        for (let i = 0; i < languages.length; i++) {
            let lang = languages[i];
            let parts = lang.code.split(/\s*[_\-]\s*/);
            lang.html = genLangLabels(langNames.lang, parts[0].toLowerCase(), 'lang');
            lang.html += '<br><span class="reg-select">&nbsp;</span>';
            if (parts.length > 1) {
                lang.html += genLangLabels(langNames.reg, parts[1].toUpperCase(), 'reg');
            }
        }
        languages.sort((a, b) => a.html.localeCompare(b.html));
    }
    let popup = document.querySelector('#popup-languages');
    let html = '';
    for (let i = 0; i < languages.length; i++) {
        let lang = languages[i];
        html += `<div><a href="javascript:// Select ${lang.code}" onclick="wrap(selectLanguage, ${i})">${lang.html}</a></div>`;
    }
    document.querySelector('#language-list').innerHTML = html;
    popup.style.display = 'block';
}

async function selectLanguage(index) {
    document.querySelector('#popup-languages').style.display = 'none';
    if (typeof (index) != 'number') {
        show_options();
        return;
    }
    let lang = languages[index];
    releases_lang = lang.code;
    releases_url = lang.url;
    await load_releases();
}

function numToChmod(num) {
    let ret = '0000' + parseInt(num).toString(8);
    return ret.substring(ret.length - 4);
}

function updateChmod() {
    document.querySelector('#chmod_values').style.display = document.querySelector('#chmod').checked ? 'block' : 'none';
}

async function setChmod() {
    let popup = document.querySelector('#popup-chmod');
    document.querySelector('#chmod_php').value = numToChmod(chmodPHP);
    document.querySelector('#chmod_other').value = numToChmod(chmodOther);
    document.querySelector('#chmod').checked = chmod;
    document.querySelector('#chmod_values').style.display = document.querySelector('#chmod').checked ? 'block' : 'none';
    popup.style.display = 'block';
    document.querySelector('#chmod').focus();
}

function fixChmod(input, event) {
    let v = input.value
        .trim()
        .replace(/[^0-7]/ig, '');
    if (!v.startsWith('0')) {
        v = '0' + v;
    }
    if (v.length > 4) {
        v = v.substring(0, 4);
    }
    if (input.value != v) {
        let start = input.selectionStart;
        let end = input.selectionEnd;
        input.value = v;
        input.setSelectionRange(start, end);
    }
    if (event && event.keyCode == 13) {
        chmodSelected();
    }
}

async function chmodSelected() {
    let popup = document.querySelector('#popup-chmod');
    chmodPHP = parseInt(document.querySelector('#chmod_php').value, 8);
    chmodOther = parseInt(document.querySelector('#chmod_other').value, 8);
    chmod = !!document.querySelector('#chmod').checked;
    popup.style.display = 'none';
    show_options();
}

async function folderSelected() {
    let popup = document.querySelector('#popup-folder');
    subfolder = document.querySelector('#folder').value
        .trim()
        .replace(/[^a-z0-9_.,=+-]/ig, '_');
    popup.style.display = 'none';
    show_options();
}

function fixFolderName(input, event) {
    let v = input.value
        .trim()
        .replace(/[^a-z0-9_.,=+-]/ig, '_');
    if (input.value != v) {
        let start = input.selectionStart;
        let end = input.selectionEnd;
        input.value = v;
        input.setSelectionRange(start, end);
    }
    if (event && event.keyCode == 13) {
        folderSelected();
    }
}

let dragOverTimeout = null;

function dragOverHandler(ev, element) {
    ev.preventDefault();
    element.classList.add('drop');
    if (dragOverTimeout !== null) {
        clearTimeout(dragOverTimeout);
    }
    dragOverTimeout = setTimeout(() => dragOverEnd(element), 500);
}

function dragOverEnd(element) {
    element.classList.remove('drop');
    dragOverTimeout = null;
}

async function dropHandler(ev, element) {
    ev.preventDefault();
    if (dragOverTimeout !== null) {
        clearTimeout(dragOverTimeout);
    }
    dragOverEnd(element);

    let file;
    if (ev.dataTransfer.items) {
        if (ev.dataTransfer.items.length != 1 || ev.dataTransfer.items[0].kind !== 'file') {
            throw Error('Only one file can be dropped');
        }
        file = ev.dataTransfer.items[0].getAsFile();
    } else {
        if (ev.dataTransfer.files.length != 1) {
            throw Error('Only one file can be dropped');
        }
        file = ev.dataTransfer.files[0];
    }
    await uploadFile(file);
}

let uploadChunks = [];
let uploadCrc = null;

const WAITING = 0;
const RUNNING = 1;
const DONE = 2;

async function uploadChunk(chunk) {
    try {
        if (chunk.crc32 === null) {
            chunk.crc32 = crc32(new Uint8Array(await chunk.blob.arrayBuffer()));
        }
        await download('upload', {
            file: chunk.blob,
            start: chunk.start,
            end: chunk.end,
            total: chunk.total,
            crc32: chunk.crc32,
        }, null, true);
        chunk.state = DONE;
        uploadNextChunk();
    } catch (ex) {
        if (chunk.retry > 0) {
            chunk.state = WAITING;
            chunk.retry--;
            uploadNextChunk();
        } else {
            uploadChunks = [];
            throw ex;
        }
    }
}

function uploadNextChunk() {
    let done = 0;
    for (let chunk of uploadChunks) {
        if (chunk.state == DONE) done++;
        if (chunk.state != WAITING) continue;
        chunk.state = RUNNING;
        wrap(uploadChunk, chunk);
        break;
    }
    logProgress(done / uploadChunks.length);
    if (done == uploadChunks.length) {
        logAppend("OK");
        wrap(finalizeUpload);
    }
}


function parseCrc32(crc) {
    crc = crc.trim();
    crc = [...crc.matchAll(/[0-9A-F]{8}/gi)];
    if (!crc || crc.length == 0) throw Error(`Cannot parse CRC-32 response`);
    return crc[crc.length - 1][0];
}

async function finalizeUpload() {
    logText(`Finalizing upload... `);
    let crc = await download('get_crc32', {});
    crc = parseCrc32(crc);
    console.log(`CRC-32 from server: ${crc}`);
    crc = parseInt(crc, 16) & 0xFFFFFFFF;
    console.log(`CRC-32 from server: ${crc}`);
    if (uploadCrc instanceof Promise) {
        await uploadCrc;
    }
    if (uploadCrc != crc) {
        throw Error(`Upload error. Invalid CRC of uploaded file.`);
    }
    logAppend(`OK`);
    await unpack();
}

async function calcFileCrc() {
    await sleep(0);
    let crc = 0;
    for (let chunk of uploadChunks) {
        crc = crc32(new Uint8Array(await chunk.blob.arrayBuffer()), crc);
        await sleep(0);
    }
    uploadCrc = crc;
    console.log(`CRC-32 calculated: ${crc}`);
}

async function uploadFile(file) {
    if (!file.name.toLowerCase().endsWith('.zip')) {
        throw Error('Only ZIP file is supported!');
    }
    switchScreen('progress');
    logText(`Uploading ${file.name} of size ${file.size} (${Math.round(file.size / 1024 / 1024 * 10) / 10} MB)... `);
    let chunkCount = Math.max(3, Math.ceil(file.size / 1024 / 1024));
    let chunkSize = Math.ceil(file.size / chunkCount);
    if (chunkSize > max_upload - 32768 && max_upload >= 65536) {
        chunkSize = max_upload - 32768;
    }
    let pos = 0;
    uploadChunks = [];
    while (pos < file.size) {
        uploadChunks.push({
            start: pos,
            end: Math.min(file.size, pos + chunkSize),
            blob: file.slice(pos, Math.min(file.size, pos + chunkSize)),
            retry: 4,
            state: WAITING,
            total: file.size,
            crc32: null,
        });
        pos += chunkSize;
    }
    uploadCrc = calcFileCrc();
    uploadNextChunk();
    uploadNextChunk();
    uploadNextChunk();
}

function createCrc32Table() {
    let crcTable = new Int32Array(256);
    for (let byte = 0; byte < 256; byte++) {
        let c = byte;
        for (let i = 0; i < 8; i++) {
            c = ((c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1));
        }
        crcTable[byte] = c;
    }
    return crcTable;
}

const crcTable = createCrc32Table();

function crc32(data, oldCrc) {
    let crc = !oldCrc ? -1 : ~oldCrc;
    for (let i = 0; i < data.length; i++) {
        crc = (crc >>> 8) ^ crcTable[(crc ^ data[i]) & 0xFF];
    }
    return ~crc;
};

async function uploadZip() {
    document.getElementById("browse").click();
}

async function fileSelected() {
    let files = document.getElementById("browse").files;
    if (files.length == 0) {
        return;
    } else if (files.length != 1) {
        throw Error('Only one file can be selected.');
    }
    await uploadFile(files[0]);
}

async function wrap(func, ...args) {
    try {
        return await func.apply(undefined, args);
    } catch (ex) {
        console.error(ex);
        document.querySelector('#error-message').innerText = ex.toString();
        for (let e of document.querySelectorAll('.popup')) {
            e.style.display = 'none';
        }
        switchScreen('error');
    }
}

window.onload = (() => { wrap(main); });


</script>
<style>

@import url('https://fonts.googleapis.com/css2?family=Roboto&display=swap');

body {
    font-family: 'Roboto', sans-serif;
    font-size: 11pt;
}

table {
    font-size: 100%;
}

div.screen,
div.popup>div {
    width: 600px;
    margin: 30px auto 50px auto;
    border: 2px solid #ccc;
    background: #EEE;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 4px 4px 17px 3px rgb(0 0 0 / 30%);
}

div.screen {
    display: none;
}

div.drop {
    background-color: rgb(32, 218, 171);
}

div#screen-progress {
    height: 350px;
    max-height: 350px;
    overflow: auto;
}

div#screen-progress>div {
    margin: 4px;
    border: 1px solid #D0D0D0;
    border-radius: 6px;
    padding: 7px 20px;
    background: #FFF;
}

div.progress-bar {
    height: 10px;
    width: 400px;
    background-color: #f9f9f9;
    border: 1px solid #a9a9a9;
    border-radius: 5px;
    box-shadow: inset 1px 1px 3px 1px rgb(0 0 0 / 25%);
    margin: 5px 0px 5px 12px;
}

div.progress-bar>div {
    background-color: #6daeff;
    height: 10px;
    border-radius: 5px;
}

div#screen-options {
    height: 360px;
    overflow: auto;
}

a.button-big,
a.button-small {
    text-decoration: none;
    text-align: center;
    display: inline-block;
    border: 1px solid #006c97;
    background: #0085ba;
    color: #111;
    text-shadow: 0px 0px 4px #000;
    color: #FFF
}

a.button-big {
    margin: 10px;
    padding: 20px 30px;
    border-radius: 12px;
    font-size: 120%;
    box-shadow: 0px 10px 13px -7px #000000, 0px 0px 8px 2px rgb(0 0 0 / 25%), inset 0px 0px 3px 0px rgba(255, 255, 255, 0.6);
}

a.button-small {
    font-size: 80%;
    margin: 1px 4px;
    padding: 4px 10px;
    border-radius: 6px;
    width: 150px;
    box-shadow: 0px 7px 10px -7px #000000, 0px 0px 6px rgb(0 0 0 / 25%), inset 0px 0px 3px 0px rgb(255 255 255 / 60%);
}

a.button-big:hover, a.button-small:hover {
    background: #20a1d4;
}

table.details {
    width: 100%;
}

table.details td {
    text-align: center;
}

table.details tr:nth-child(3n+1) td {
    font-size: 80%;
    color: #666;
}

#drop-area {
    border: 4px dashed #CCC;
    width: 85%;
    height: 50px;
    margin: 40px auto;
    padding-top: 19px;
    color: #999;
    font-size: 130%;
}

h1 {
    color:#006c97;
}

#current-wpd-version, #new-wpd-version {
    font-weight: bold;
}

div.popup {
    position: fixed;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 5000;
    display: none;
}

input.text-input {
    font-family: monospace;
    border: 1px solid #CCC;
    padding: 5px;
    margin: 0px;
    width: 100%;
}

#releases-list a {
    text-decoration: none;
    color: #004b69;
    padding: 1px 3px;
}

#releases-list a:hover {
    background-color: #0085ba;
    color: #FFF;
}

.version-panel, .major-version-panel {
    border: 1px solid gray;
    border-radius: 3px;
    background-color: #FFF;
    margin: 3px 0px;
    padding: 3px 6px;
}

.minor-version-panel {
    overflow-y: scroll;
    overflow-x: auto;
    height: 200px;
    border: 1px solid gray;
    border-radius: 3px;
    background-color: #FFF;
    margin: 3px 0px;
    padding: 3px 6px;
}

#language-list {
    overflow-y: scroll;
    overflow-x: auto;
    height: 270px;
    border: 1px solid gray;
    border-radius: 3px;
    background-color: #FFF;
    margin: 3px 0px;
    padding: 3px 6px;
}

.lang-select, .lang-select-eng, .reg-select, .reg-select-eng {
    display: inline-block;
    padding: 4px;
}

.lang-select { font-size: 80%; color: #888; }
.lang-select-eng { color: #000; }
.reg-select { font-size: 60%; color: #888; }
.reg-select-eng { color: #000; font-size: 80%; margin-left: 15px; }

#language-list a {
    display: block;
    background-color: #FFF;
    border: 1px solid #88F;
    margin: 4px;
    border-radius: 4px;
}

#language-list a:hover {
    display: block;
    background-color: rgb(189, 189, 255);
    border: 1px solid rgb(100, 100, 255);
}

</style>
</head>
<body>

<div id="screen-progress" class="screen">

</div>

<div id="screen-options" class="screen" style="text-align: center;"  ondrop="wrap(dropHandler, event, this);" ondragover="dragOverHandler(event, this);">
    <a id="install" class="button-big" onclick="wrap(install);" href="javascript:// Download and Install WordPress">Download and Install<br>WordPress</a>
    <br><br>
    <table class="details">
        <tr>
            <td width="33%"><br>Version</td>
            <td width="34%"><br>Language</td>
            <td width="33%"><br>Install from a custom ZIP file:</td>
        </tr>
        <tr>
            <td><span id="version"></span></td>
            <td><span id="lang"></span></td>
            <td>Drop ZIP file here</td>
        </tr>
        <tr>
            <td><br><a class="button-small" onclick="wrap(setRelease)" href="javascript:// Change Version">Change Version</a></td>
            <td><br><a class="button-small" onclick="wrap(setLanguage)" href="javascript:// Change Language">Change Language</a></td>
            <td><br><a class="button-small" onclick="wrap(uploadZip)" href="javascript:// Upload custom ZIP file">Browse</a>
                <input type="file" id="browse" style="display: none" onChange="wrap(fileSelected)"/>
            </td>
        </tr>
    </table><br>
    <table class="details">
        <tr>
            <td width="50%"><br>Destination URL</td>
            <td width="50%"><br>Advanced options</td>
        </tr>
        <tr>
            <td><span id="destination">http://example.com/</span></td>
            <td><span id="adv-options">None</span></td>
        </tr>
        <tr>
            <td><br><a class="button-small" onclick="wrap(setFolder)" href="javascript:// Set Subfolder">Set Subfolder</a></td>
            <td><br><a class="button-small" onclick="wrap(setChmod)" href="javascript:// Advanced Options">Advanced Options</a></td>
        </tr>
    </table>
</div>

<div id="screen-error" class="screen">
    <h1>Error</h1>
    <span id="error-message"></span><br><br>
    <table>
        <tr>
            <td width="33%"><a onclick="wrap(retry)" class="button-small" href="javascript:// Retry">Retry</a></td>
            <td>It will start the download and install process from the beginning.
                All information obtained so far, including user input, will be lost.<br></td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td width="33%"><a onclick="wrap(abort)" class="button-small" href="javascript:// Abort">Abort</a></td>
            <td>It will remove all the temporary files and the downloader itself.
                If there are partially unpacked WordPress files, they will remain on
                the server. You have to delete them manually.<br></td>
        </tr>
    </table>
</div>

<div id="screen-update" class="screen">
    <h1>Automatic Update</h1>
    There is a new version of WordPress Downloader.<br>
    Your version: <span id="current-wpd-version"></span><br>
    Latest version: <span id="new-wpd-version"></span>
    <br><br>
    <table>
        <tr>
            <td width="33%"><a onclick="wrap(do_update, false)" class="button-small" href="javascript:// Update">Update</a></td>
            <td>WordPress Downloader will update itself and restart in a new version.<br></td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td width="33%"><a onclick="wrap(do_update, true)" class="button-small" href="javascript:// Update and download">Update and download</a></td>
            <td>WordPress Downloader will update itself and restart in a new version.
                Additionally, your browser will download copy of updated version to your local system.<br></td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td width="33%"><a onclick="wrap(load_releases)" class="button-small" href="javascript:// Skip">Skip</a></td>
            <td>Do not update now (not recommended).<br></td>
        </tr>
    </table>
</div>

<div id="popup-folder" class="popup"><br><br>
<div>
    <h1>Select folder</h1>
    <input type="text" value="" class="text-input" id="folder" onkeydown="fixFolderName(this)" onkeyup="fixFolderName(this)" onkeypress="fixFolderName(this, event)"></input>
    <br><br>
    <div style="text-align: center">
    <a onclick="wrap(folderSelected)" class="button-small" href="javascript:// OK">OK</a>
    </div>
</div>
</div>

<div id="popup-releases" class="popup"><br><br>
<div>
    <h1>Select release</h1>
    <div id="releases-list"></div>
    <br>
    <div style="text-align: center">
    <a onclick="wrap(setVersion)" class="button-small" href="javascript:// Cancel">Cancel</a>
    </div>
</div>
</div>

<div id="popup-languages" class="popup"><br><br>
<div>
    <h1>Select language</h1>
    <div id="language-list"></div>
    <br>
    <div style="text-align: center">
    <a onclick="wrap(selectLanguage)" class="button-small" href="javascript:// Cancel">Cancel</a>
    </div>
</div>
</div>

<div id="popup-chmod" class="popup"><br><br>
<div>
    <h1>Advanced options</h1>
    <input type="checkbox" id="chmod" onchange="updateChmod()">
    <label for="chmod">Change file permissions (chmod) after unpacking WordPress.</label>
    <table id="chmod_values">
        <tr><td>
            <input type="text" style="width: 80px" value="0755" class="text-input" id="chmod_php" onkeydown="fixChmod(this)" onkeyup="fixChmod(this)" onkeypress="fixChmod(this, event)"></input>
        </td><td>
            PHP files linux permissions
        </td></tr>
        <tr><td>
            <input type="text" style="width: 80px" value="0644" class="text-input" id="chmod_other" onkeydown="fixChmod(this)" onkeyup="fixChmod(this)" onkeypress="fixChmod(this, event)"></input>
        </td><td>
            Other files linux permissions
        </td></tr>
    </table>
    <br><br>
    <div style="text-align: center">
    <a onclick="wrap(chmodSelected)" class="button-small" href="javascript:// OK">OK</a>
    </div>
</div>
</div>

<!--div id="drop-area">
    Drop your custom ZIP file here to install it.
</div-->

</body><?php
}

function do_test() {
    return false;
}

function devel_mode() {
    return false;
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
