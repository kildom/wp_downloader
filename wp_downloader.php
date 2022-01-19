<?php

function get_url($url, $secure = true, $prepare_only = false) {
    $ch = curl_init($url);
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, "WordPress Downloader PHP script");
    }
    if ($secure) {
        if (gettype($secure) != 'string') {
            $secure = __DIR__ . "/_wp_dwnl_cacert.pem";
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        if (file_exists($secure)) {
            curl_setopt($ch, CURLOPT_CAINFO, $secure);
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
<?php

$version = 'v0.0.4-6-gb6338a7';
//$version = 'v0.0.0';
$cacert_url = /*BUILDVAR:cacert_url*/'https://curl.se/ca/cacert-2021-10-26.pem'/**/;
$cacert_hash = /*BUILDVAR:cacert_hash*/'cb6545d71a1f4d3e3ab93541c97a6b8e3131a5ae'/**/;
$cacert_latest_url = 'https://curl.se/ca/cacert.pem';
$update_url = 'https://api.github.com/repos/kildom/wp_downloader/releases/latest';
$html_update_url = 'https://github.com/kildom/wp_downloader/releases/latest';
$html_update_regex = '/href=".*\/wp_downloader\/releases\/download\/([^"]*)\/([^"]*\.php)"/';
$html_update_prefix = 'https://github.com/kildom/wp_downloader/releases/download';
$public_key = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEIk7ZCuaV8jp+A5MxdivJM+LCqXiv\nKVQJijYssSjx5L5cvLofKa74tpdY4UF4Dfcb/8Bu6ZUN39KIj4YNHVb1KA==\n-----END PUBLIC KEY-----\n";
$backup_public_key = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEuc9S0sE8ANEFnsOCOlUZRI1jV/C1\nvUzJkwieSBzv3I4X6aHbl6YaBXwXtDeZLFW+dEMdu2HikrxOQYi6SSAqaQ==\n-----END PUBLIC KEY-----\n";

function is_valid_url($url) {
    return preg_match_all('/^https:\/\/([a-z_0-9-.]+\.)?wordpress\.org\/.*$/', $url);
}

function FUNC_auto_update() {
    global $version, $update_url, $html_update_url, $html_update_regex, $html_update_prefix;
    global $public_key, $backup_public_key;
    header('Content-type: text/plain');
    $update = isset($_REQUEST['update']) ? intval($_REQUEST['update']) : 0;
    $json = get_url($update_url, false);
    $cnt = json_decode($json, false);
    $download_url = null;
    $new_version = $cnt->tag_name;
    if (!$new_version) {
        $html = get_url($html_update_url, false);
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
    
    $update_file = get_url($download_url, false);

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

function FUNC_cacert_fix() {
    global $cacert_url, $cacert_hash, $cacert_latest_url;
    header('Content-type: text/plain');
    $level = intval($_REQUEST['level']);
    if ($level > 0 && !cacert_exists()) {
        echo("\nInvalid state\nError");
        return;
    }
    if ($level == 0) {
        $cacert = get_url($cacert_url, false);
        if (!$cacert) {
            echo("\nCannot download cacert\nError");
            return;
        }
        if (sha1($cacert) != $cacert_hash) {
            echo("\nInvalid cacert hash\nError");
            return;
        }
        file_put_contents("_wp_dwnl_cacert.pem", $cacert);
    } else {
        $cacert = get_url($cacert_latest_url, true);
        if (!$cacert) {
            echo("\nCannot download cacert\nError");
            return;
        }
        file_put_contents("_wp_dwnl_cacert.pem", $cacert);
    }
    echo("\nOK");
}

function FUNC_download_page() {
    header('Content-type: text/plain');
    $url = stripslashes($_REQUEST['url']);
    if (!is_valid_url($url)) {
        echo("\nInvalid URL\nError");
        return;
    }
    $cnt = get_url($url, true);
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
    $ch = get_url($url, true, true);
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
    if (!devel_mode()) {
        @unlink('_wp_dwnl_cacert.pem');
    }
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

let devel_mode = false;


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
        pos = response.lastIndexOf('\n');
        if (pos < 0) pos = 0;
        let message = response.substr(pos + 1);
        reject(Error(`Error message from server: ${message}`));
        return;
    }
    if (result.trim() != 'OK') {
        reject(Error('Invalid response from server'));
        return;
    }
    resolve(response);
}

function download(func, data, progress) {
    return new Promise((resolve, reject) => {
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
            let re = new RegExp(/\n([0-9]+)\/([0-9]+)\s+/mg);
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
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        let body = Object.entries(data)
            .concat([['func', func]])
            .map(([key, val]) => encodeURIComponent(key) + '=' + encodeURIComponent(val))
            .join('&');
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
}

function logAppend(text) {
    if (lastLogLine == null) logText('');
    let content = lastLogLine.querySelector('div.log-text');
    content.innerText += text;
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
let releases_url = 'https://wordpress.org/download/releases/';
let zips;
let selected_zip;
let subfolder = '';

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
    for (let i = 0; i < 3; i++) {
        try {
            logText(`Loading releases from: ${releases_url}...`);
            releases_page = await download('download_page', { url: releases_url });
            logAppend(' OK');
            break;
        } catch (ex) {
            logAppend(' ERROR');
            if (i == 3) throw ex;
            logText(`Applying SSL cacert workaround level ${i}...`);
            await download('cacert_fix', { level: i });
            logAppend(' OK');
        }
    }
    zips = [];
    for (let match of releases_page.matchAll(/href="([^"]+-([0-9\.]+\.[0-9\.]+[^"]*).zip)"/gi)) {
        zips.push({
            version_num: match[2]
                .split('.')
                .map(x => parseInt(x))
                .concat([0, 0, 0, 0, 0])
                .slice(0, 4)
                .reduce((s, x) => s * 1000 + x, 0),
            version: match[2],
            url: (new URL(match[1], releases_url)).href,
        });
    }
    //zips.sort((a, b) => b.version_num - a.version_num); TODO may be used to find latest beta or RC
    if (zips.length == 0) throw Error('Cannot parse releases page!');
    selected_zip = zips[0];
    logText(`Found ${zips.length} releases. Latest is ${selected_zip.version}.`);
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

    logText(`Unpacking the ZIP file...`);
    await download('unpack', { dir: subfolder }, (done, total) => {
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
    let path = subfolder + '/';
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

async function wrap(func, ...args) {
    try {
        return await func.apply(undefined, args);
    } catch (ex) {
        console.error(ex);
        document.querySelector('#error-message').innerText = ex.toString();
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

</style>
</head>
<body>

<div id="screen-progress" class="screen">

</div>

<div id="screen-options" class="screen" style="text-align: center;">
    <a id="install" class="button-big" onclick="wrap(install);" href="javascript:// Download and Install WordPress">Download and Install<br>WordPress</a>
    <br><br>
    <table class="details">
        <tr>
            <td width="33%"><br>Version</td>
            <td width="34%"><br>Language</td>
            <td width="33%"><br>Download from</td>
        </tr>
        <tr>
            <td><span id="version"></span></td>
            <td><span id="lang"></span></td>
            <td>wordpress.org</td>
        </tr>
        <tr>
            <td><br><a class="button-small" href="javascript:// Change Version">Change Version</a></td>
            <td><br><a class="button-small" href="javascript:// Change Language">Change Language</a></td>
            <td><br><a class="button-small" href="javascript:// Upload custom ZIP file">Install custom ZIP file</a></td>
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
            <td><br><a class="button-small" href="javascript:// Advanced Options">Advanced Options</a></td>
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
