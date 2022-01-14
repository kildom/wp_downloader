<?php

$cnt = file_get_contents(__DIR__ . "/entry.php");
$tst = $cnt;

function include_repl($m) {
    return file_get_contents(__DIR__ . "/" . $m[1]);
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

file_put_contents('wp_downloader.php', $cnt);
file_put_contents('wp_downloader_test.php', $tst);
