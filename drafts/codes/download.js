
const fs = require('fs');
const axios = require('axios');

let all = '';

async function main() {
    for (let l = 'a'.charCodeAt(0); l <= 'z'.charCodeAt(0); l++) {
        let c = String.fromCharCode(l);
        let res = await axios.get(`https://en.wikipedia.org/wiki/ISO_639:${c}`);
        console.log(res.status);
        res = res.data;
        let pos = res.indexOf('<table class="wikitable sortable"');
        if (pos < 0) throw 'pos';
        res = res.substr(pos);
        pos = res.indexOf('</table>');
        if (pos < 0) throw 'pos';
        res = res.substr(0, pos + 8);
        all += res;
    }
    fs.writeFileSync('codes.html', `<html><body>${all}</html></body>`);
}

main();
