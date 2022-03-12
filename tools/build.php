<?php

$downloader_file_name = 'wp_downloader.php';

//----------------- Replace includes

$cnt = file_get_contents(__DIR__ . "/../entry.php");
$tst = $cnt;
$build_vars = array('devel' => 'false');

function include_repl($m) {
    return file_get_contents(__DIR__ . "/../" . $m[1]);
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

if (!isset($build_version) && isset($_SERVER['BUILD_VERSION']) && trim($_SERVER['BUILD_VERSION']) != '') {
    $build_version = trim($_SERVER['BUILD_VERSION']);
}

if (!isset($build_version)) {
    echo("\nBUILD_VERSION environment variable is not set\n");
    exit(1);
}

$build_vars['version'] = $build_version;

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

//----------------- Write final output

function common_new_lines(&$text) {
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);
    $text = str_replace("\n", "\r\n", $text);
}

common_new_lines($cnt);
common_new_lines($tst);

file_put_contents(__DIR__ . "/../$downloader_file_name", $cnt);
file_put_contents(__DIR__ . "/../test_$downloader_file_name", $tst);
