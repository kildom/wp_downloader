<?php

include('geturl.php');

//----------------- Key generator

if (isset($argv[1]) && $argv[1] == 'keys') {
    $new_key_pair = openssl_pkey_new(array(
        "private_key_type" => OPENSSL_KEYTYPE_EC,
        "curve_name" => 'prime256v1'
    ));
    openssl_pkey_export($new_key_pair, $private_key_pem);
    $private_key_pem = str_replace("\r\n", "~", $private_key_pem);
    $private_key_pem = str_replace("\n", "~", $private_key_pem);
    echo("$private_key_pem\n\n");
    $details = openssl_pkey_get_details($new_key_pair);
    $public_key_pem = $details['key'];
    $public_key_pem = str_replace("\r\n", '\n', $public_key_pem);
    $public_key_pem = str_replace("\n", '\n', $public_key_pem);
    echo("$public_key_pem\n");
    exit(0);
}

//----------------- Replace includes

$cnt = file_get_contents(__DIR__ . "/entry.php");
$tst = $cnt;
$build_vars = array('devel' => 'false');

function include_repl($m) {
    return file_get_contents(__DIR__ . "/" . $m[1]);
}

$inc_pattern = '/<\?php\\s+include\(\'([^\']+)\'\)\\s+\?>/i';
$tst_pattern = '/<\?php\\s+if\\s*\(do_test\(\)\)\\s*\{\\s*include\(\'([^\']+)\'\);?\\s+\}\\s+\?>/i';

do {
    $newcnt = preg_replace_callback($inc_pattern, 'include_repl', $cnt);
    $newcnt = preg_replace($tst_pattern, '', $newcnt);
    $newtst = preg_replace_callback($inc_pattern, 'include_repl', $tst);
    $newtst = preg_replace_callback($tst_pattern, 'include_repl', $newtst);
    if ($newcnt == $cnt && $newtst == $tst) break;
    $cnt = $newcnt;
    $tst = $newtst;
} while (true);

//----------------- Add version information

if (!isset($_SERVER['BUILD_VERSION']) || $_SERVER['BUILD_VERSION'] == '') {
    echo("\nBUILD_VERSION environment variable not set\n");
    exit(1);
}

$build_vars['version'] = $_SERVER['BUILD_VERSION'];

//----------------- Fetch latest cacert bundle

function get_cacert_cmp($a, $b) {
    return strnatcmp($b[2], $a[2]);
}

function get_cacert() {
    $url = 'https://curl.se/docs/caextract.html';
    $html = get_url($url);
    if (!$html) {
        echo("\nCannot get list of certificate bundles from $url\n");
        exit(1);
    }
    preg_match_all('/"([^"]*cacert-(2[0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9])\.pem)/i', $html, $m, PREG_SET_ORDER);
    usort($m, 'get_cacert_cmp');
    $url = $m[0][1];
    if (!$url) {
        echo("\nCannot parse list of certificate bundles\n");
        exit(1);
    }
    if ($url[0] == '/') {
        $url = "https://curl.se$url";
    }
    $cacert = get_url($url);
    if (!$cacert) {
        echo("\nCannot get certificate bundle from $url\n");
        exit(1);
    }
    return array('url' => $url, 'hash' => sha1($cacert));
}

$cacert = get_cacert();
$build_vars['cacert_url'] = $cacert['url'];
$build_vars['cacert_hash'] = $cacert['hash'];

//----------------- Replace build variables

function replace_vars(&$text, $vars) {
    foreach ($vars as $name => $value) {
        $a = addcslashes(addcslashes($value, '\'\\'), '\\');
        $text = preg_replace('/\/\*BUILDVAR:' . $name . '\*\/\'.*?\'\/\*\*\//', "'$a'", $text);
    }
    foreach ($vars as $name => $value) {
        $a = addcslashes($value, '\\');
        $text = preg_replace('/\/\*BUILDVAR:' . $name . '\*\/.*?\/\*\*\//', $a, $text);
    }
}

$build_vars['test'] = 'false';
replace_vars($cnt, $build_vars);

$build_vars['test'] = 'true';
replace_vars($tst, $build_vars);

//----------------- Add signature

if (isset($_SERVER['BUILD_PRIVATE_KEY']) && $_SERVER['BUILD_PRIVATE_KEY'] != '') {
    $key = str_replace('~', "\n", $_SERVER['BUILD_PRIVATE_KEY']);
    $data = rtrim($cnt);
    openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
    $cnt .= "\n" . wordwrap("/*" . base64_encode($signature), 50, "\n", true) . "*/\n";
}

//----------------- Write final output

function common_new_lines(&$text) {
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);
    $text = str_replace("\n", "\r\n", $text);
}

common_new_lines($cnt);
common_new_lines($tst);

file_put_contents('wp_downloader.php', $cnt);
file_put_contents('wp_downloader_test.php', $tst);
