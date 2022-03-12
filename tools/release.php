<?php

include(__DIR__ . '/_cacert.php');
include(__DIR__ . '/_json.php');

$root_dir = __DIR__ . "/..";
$prev_release_dir = "$root_dir/prev_release";
$release_dir = "$root_dir/release";

@mkdir($release_dir);

# Get version number from current tag

function get_version() {
    $ok = exec('git describe --tags', $tag, $code);
    if ($ok === false || $code != 0) {
        echo("Cannot get current tag from git\n");
        exit(1);
    }
    $tag = preg_replace('/[\s\r\n]/', '', implode('', $tag));
    $ok = exec('git diff --stat HEAD', $diff, $code);
    if ($ok === false || $code != 0) {
        echo("Cannot get current tag from git\n");
        exit(1);
    }
    $diff = preg_replace('/[\s\r\n]/', '', implode('', $diff));
    if ($diff != '') {
        $diff = '-DIRTY';
    }
    return $tag . $diff;
}

$build_version = get_version();

# Build wp_downloader.php

include(__DIR__ . '/build.php');

copy("$root_dir/$downloader_file_name", "$release_dir/$downloader_file_name");

# Prepare release information

$info = array(
    'version' => $build_version,
    'hash' => hash_file('sha256', "$release_dir/$downloader_file_name"),
    'name' => $downloader_file_name
);

# Download cacert.pem and extract github.pem

update_cacert("$prev_release_dir/cacert.pem", "$release_dir/cacert.pem", $small_cert);

$info['small_cert'] = $small_cert;

# Create and sign info.json

sign_and_write_json("$release_dir/info.json", $info);

# Push commit with new release information

# Create a release
