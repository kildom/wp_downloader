
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

function show_options() {
    let ver = selected_zip.version;
    if (selected_zip == zips[0]) ver += ' (latest)';
    document.querySelector('#version').innerText = ver;
    document.querySelector('#lang').innerText = releases_lang;
    switchScreen('options');
}

async function main() {
    switchScreen('progress');
    logText(`Loading releases from: ${releases_url}...`);
    let releases_page = await download('download_page', { url: releases_url });
    logAppend(' OK');
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
    let zip_hash = await download('get_hash', { });
    zip_hash = parseHash(zip_hash);
    if (zip_hash != hash) throw Error(`Hashes does not match. Expected: ${hash}, downloaded: ${zip_hash}`);
    logAppend(' OK');

    logText(`Unpacking the ZIP file...`);
    await download('unpack', { dir: 'temp' }, (done, total) => { // TODO: temp
        if (done > 0 && total >= done) {
            logProgress(done / total);
        }
    });
    logProgress(1);
    logAppend(' OK');
    
    logText(`Verifying unpacked files...`);
    await download('unpack', { dir: 'temp', verify: 1 }, (done, total) => { // TODO: temp
        if (done > 0 && total >= done) {
            logProgress(done / total);
        }
    });
    logProgress(1);
    logAppend(' OK');

    await cleanup();

    logText(`Redirecting to the installer...`);
    setTimeout(() => { location.href = 'temp/'; }, 1000);
}

async function cleanup(force) {
    
    logText(`Removing the ZIP file and a downloader itself...`);
    await download('cleanup', { keep_downloader: 1 });
    try {
        let zip_hash = await download('get_hash', { });
        if (zip_hash != '0') throw Error(`Cannot delete downloader temporary files`);
    } catch (ex) {
        if (force) await download('cleanup', { });
        throw ex;
    }
    await download('cleanup', { });
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

async function wrap(func, ...args)
{
    try {
        return await func.apply(undefined, args);
    } catch (ex) {
        console.error(ex);
        document.querySelector('#error-message').innerText = ex.toString();
        switchScreen('error');
    }
}

window.onload = (() => { wrap(main); });
