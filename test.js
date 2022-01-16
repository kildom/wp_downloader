window.testing = true;

let testName = location.hash;
let switchScreenResolver = null;

function watchFunction(old) {
    let resolver = null;
    let result = function() {
        if (resolver !== null) {
            let t = resolver;
            resolver = null;
            t.call(null, [...arguments]);
        }
        if (old) {
            return old.apply(this, arguments);
        }
    };
    result.wait = function() {
        return new Promise(resolve => {
            resolver = resolve;
        });
    };
    return result;
}

switchScreen = watchFunction(switchScreen);
startWordPressInstaller = watchFunction();

function forTime(time) {
    return new Promise(resolve => {
        setTimeout(resolve, time);
    })
}

watchdogTimer = null;

function reportTestError(value) {
    console.error(value);
    if (value instanceof Error) {
        value = value.toString();
    }
}

function reportTestDone() {
    console.info("Test Successful");
    if (watchdogTimer !== null) {
        clearTimeout(watchdogTimer);
    }
}

function watchdog(time) {
    console.log(`Setting watchdog for ${time}`);
    if (watchdogTimer !== null) {
        clearTimeout(watchdogTimer);
    }
    watchdogTimer = setTimeout(() => {
        console.error(`Watchdog timeout!`);
        reportTestError('Watchdog timeout!');
    }, time);
}

async function testOneClick() {
    watchdog(10000);
    while ((await switchScreen.wait())[0] != 'options') { }
    console.log('options active');
    await forTime(100);
    let p = switchScreen.wait();
    document.querySelector('#install').click();
    watchdog(60000);
    await p;
    console.log('progress active');
    await startWordPressInstaller.wait();
    console.log('done');
    reportTestDone();
}

switch (testName) {
    case '#oneClick':
        testOneClick();
        break;
}
