
const fs = require('fs');

codes = fs.readFileSync('codes.txt', 'utf-8');

function trimCode(code) {
    return code.replace(/\((.*)\)/, '$1').trim();
}

let all = {};
let reg = {};
let output = {
    lang: all,
    reg: reg,
};

function addCode(code, obj) {
    if (!code.length) return obj;
    if (code in all) {
        throw `the same ${code} ${JSON.stringify(all[code])} ${JSON.stringify(obj)}`;
    }
    all[code] = obj;
    return typeof(obj) == 'string' ? obj : code;
}

num = 0;
for (let line of codes.split(/\r?\n/gi)) {
    num++;
    if (!line.trim().length) continue;
    let [code1, code2, code3, type, native, eng] = line.split('\t');
    code1 = trimCode(code1);
    code2 = trimCode(code2);
    code3 = trimCode(code3);
    type = type.trim();
    native = native.trim();
    eng = eng.trim();
    if (!eng.length) throw `eng ${num} ${code1} ${code2} ${code3}`;
    if (type.match(/[AEHS]/i)) continue;
    let obj = [ eng ];
    if (native.length && eng != native) obj.push(native);
    obj = addCode(code1, obj);
    if (code2 != code1)
        obj = addCode(code2, obj);
    if (code3 != code1 && code3 != code2)
        obj = addCode(code3, obj);
}

codes = fs.readFileSync('reg.txt', 'UTF-8');
native = JSON.parse(fs.readFileSync('regn.json', 'UTF-8'));

codes = codes
    .split(/\r?\n/)
    .filter(x => x.trim().length)
    .map(x => x.split('\t'));

let rem = Object.fromEntries('the,la,le,el,a,les,die,l\',o'
    .split(',')
    .map(x => [x, null]));

for (let c of codes) {
    let names = [ c[0] ];
    let code = c[1];
    let alias = c[2];
    if (code in native) {
        names = names.concat(native[code]);
    }
    let newNames = {};
    for (let n of names) {
        let m = n.replace(/\s*\(\s*(.+?)\s*\)\s*/g, (x, m) => m.toLowerCase() in rem ? '' : x);
        if (n != m) {
            console.log(`${n} -> ${m}`);
        } else {
            let a = n.match(/\s*\(\s*(.+?)\s*\)\s*/g);
            if (a != null)
                console.log(a);
        }
        newNames[m] = null;
    }
    reg[code] = Object.keys(newNames);
    reg[alias] = code;
}

fs.writeFileSync('codes.json', JSON.stringify(output));
