
let devel_mode = /*BUILDVAR:devel*/true/**/;

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
    } else {
        path = '.';
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
