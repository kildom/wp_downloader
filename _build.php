<?php

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

$cnt = file_get_contents(__DIR__ . "/entry.php");
$tst = $cnt;

function include_repl($m) {
    $contents = file_get_contents(__DIR__ . "/" . $m[1]);
    if ($m[1] == 'version.php' && isset($_SERVER['BUILD_VERSION']) && $_SERVER['BUILD_VERSION'] != '') {
        $contents = str_replace("'9999.99.99'", "'" . $_SERVER['BUILD_VERSION'] . "'", $contents);
    }
    return $contents;
}

$inc_pattern = '/<\?php\\s+include\(\'([^\']+)\'\)\\s+\?>/i';
$tst_pattern = '/<\?php\\s+if\\s*\(do_test\(\)\)\\s*\{\\s*include\(\'([^\']+)\'\);?\\s+\}\\s+\?>/i';
$cnd_pattern = '/\n[^\n]*\/\/\\s*TEST CONDITION/i';

do {
    $newcnt = preg_replace_callback($inc_pattern, 'include_repl', $cnt);
    $newcnt = preg_replace($tst_pattern, '', $newcnt);
    $newcnt = preg_replace($cnd_pattern, "\n    return false;", $newcnt);
    $newtst = preg_replace_callback($inc_pattern, 'include_repl', $tst);
    $newtst = preg_replace_callback($tst_pattern, 'include_repl', $newtst);
    $newtst = preg_replace($cnd_pattern, "\n    return true;", $newtst);
    if ($newcnt == $cnt && $newtst == $tst) break;
    $cnt = $newcnt;
    $tst = $newtst;
} while (true);

if (isset($_SERVER['BUILD_PRIVATE_KEY']) && $_SERVER['BUILD_PRIVATE_KEY'] != '') {
    $key = str_replace('~', "\n", $_SERVER['BUILD_PRIVATE_KEY']);
    $data = rtrim($cnt);
    openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
    $cnt .= "\n" . wordwrap("/*" . base64_encode($signature), 50, "\n", true) . "*/\n";
}

file_put_contents('wp_downloader.php', $cnt);
file_put_contents('wp_downloader_test.php', $tst);
