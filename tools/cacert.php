<?php

include(__DIR__ . '/../geturl.php');

$cert_test_url = 'https://raw.githubusercontent.com/kildom/wp_downloader/main/README.md';
$cacert_url = 'https://curl.se/ca/cacert.pem';
$cacert_hash_url = 'https://curl.se/ca/cacert.pem.sha256';

function update_cacert($input_file, $output_file, &$github_cert)
{
    global $cacert_url, $cacert_hash_url, $cert_test_url;

    function get_certs($url, $cert) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, "WordPress Downloader PHP script");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, $cert);
        curl_setopt($ch, CURLOPT_CAPATH, dirname($cert));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CERTINFO, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        if (curl_error($ch)) {
            echo("\n" . curl_error($ch) . "\n");
            $res = false;
        } else {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($status != 200) {
                echo("\nHTTP status code: $status\n");
                $res = false;
            }
        }
        if ($res !== false) {
            $res = curl_getinfo($ch, CURLINFO_CERTINFO);
        }
        curl_close($ch);
        return $res;
    }
    
    function parse_key_identifier($key, $force) {
        $key = trim($key);
        $key = strtoupper(trim(preg_replace('/keyid\s*:?/i', '', $key)));
        if ($key == '' && $force) {
            echo('Invalid key!');
            exit(1);
        }
        return $key;
    }


    $new_hash = ' ' . get_url($cacert_hash_url, $input_file) . ' ';
    preg_match('/[\s\r\n]([0-9a-f]{64})[\s\r\n]/i', $new_hash, $m);
    $new_hash = strtolower($m[1]);
    if (!$new_hash) {
        echo("Cannot download $cacert_hash_url\n");
        exit(1);
    }

    $old_hash = strtolower(hash_file('sha256', $input_file));

    if ($old_hash != $new_hash) {
        $cacert = get_url($cacert_url, $input_file);
        if (!$cacert) {
            echo("Cannot download $cacert_url\n");
            exit(1);
        }
        file_put_contents($output_file, $cacert);
    } else {
        copy($input_file, $output_file);
    }

    $list = get_certs($cert_test_url, $output_file);
    if (!$list) {
        echo("Cannot download $cert_test_url");
        exit(1);
    }
    $certs = array();
    foreach ($list as $c) {
        $x = openssl_x509_parse($c['Cert']);
        $x['-'] = $c['Cert'];
        if (isset($x['extensions']['subjectKeyIdentifier'])) {
            $key = parse_key_identifier($x['extensions']['subjectKeyIdentifier'], false);
            $certs[$key] = $x;
        }
    }

    $list = file_get_contents($output_file);
    preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/ms', $list, $m);
    $cacerts = array();
    foreach ($m[0] as $c) {
        $x = openssl_x509_parse($c);
        $x['-'] = $c;
        if (isset($x['extensions']['subjectKeyIdentifier'])) {
            $key = parse_key_identifier($x['extensions']['subjectKeyIdentifier'], false);
            $cacerts[$key] = $x;
        }
    }

    $extracted = array();
    foreach ($certs as $key => $cert) {
        $auth_key = parse_key_identifier($cert['extensions']['authorityKeyIdentifier'], true);
        if (isset($cacerts[$auth_key])) {
            $extracted[$auth_key] = $cacerts[$auth_key];
        } else if (!isset($certs[$auth_key])) {
            echo("Cannot find parent certificate $auth_key of certificate $key");
            exit(1);
        }
    }

    $pem = '';
    foreach ($extracted as $cert) {
        $pem .= trim($cert['-']) . "\n\n";
    }

    if (trim($pem) == '') {
        echo("Cannot extract root certificates for $cert_test_url\n");
        exit(1);
    }

    file_put_contents("$output_file.tmp.pem", $pem);

    $list = get_certs($cert_test_url, "$output_file.tmp.pem");
    unlink("$output_file.tmp.pem");

    if (!$list) {
        echo("Extracting root certificates for $cert_test_url failed");
        exit(1);
    }

    $github_cert = $pem;

    return $old_hash != $new_hash;
}
