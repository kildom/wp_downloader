
let devel_mode = /*BUILDVAR:devel*/true/**/;


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

function download(func, data, progress) {
    return new Promise((resolve, reject) => {
        let body = Object.entries(data)
            .concat([['func', func]])
            .map(([key, val]) => encodeURIComponent(key) + '=' + encodeURIComponent(val))
            .join('&');
        console.log(`Requesting: ${body}`);
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
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
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
let chmod = false;
let chmodPHP = 0755;
let chmodOther = 0644;

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
    for (let i = 1; i <= 3; i++) {
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

function numToChmod(num)
{
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
