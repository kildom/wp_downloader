<?php

include(__DIR__ . '/_cacert.php');
include(__DIR__ . '/_json.php');

$root_dir = __DIR__ . "/..";
$prev_release_dir = "$root_dir/prev_release";
$release_dir = "$root_dir/release";
$force_update_period = 60 * 60 * 24 * 30;

@mkdir($release_dir);

$info = json_decode(file_get_contents("$prev_release_dir/info.json"), true);

if (!$info) {
    echo("Cannot read previous release information\n");
    exit(1);
}

$changed = update_cacert("$prev_release_dir/cacert.pem", "$release_dir/cacert.pem", $small_cert);

if (!$changed) {
    unlink("$release_dir/cacert.pem");
}

if ($small_cert != $info['small_cert'] || time() - $info['timestamp'] > $force_update_period) {
    $info['small_cert'] = $small_cert;
    sign_and_write_json("$release_dir/info.json", $info);
}
