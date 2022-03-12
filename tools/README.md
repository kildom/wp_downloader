# Utility scripts

## `build.php`

The script creates production output:
* `wp_downloader.php` file - final end-user script file.
* `test_wp_downloader.php` file - the same as above, but with automated self-test code.

Requires:
* `BUILD_VERSION` environment variable containing version string (e.g. `v1.0.0`) that will be embedded into the script.

## `release.php`

The script creates new files for the release branch:
* builds new `wp_downloader.php` file using version number obtained from `git`.
* updates certificates information.
* creates new `info.json` file.

Requires:
* `../prev_release` directory containing files from recent release branch (only `cacert.pem` is required).
* `BUILD_PRIVATE_KEY` environment variable containing private key for signing `info.json` file.
* `git` command available.

The script output is:
* `../release/wp_downloader.php` - final end-user script file.
* `../release/info.json` - new release information file.
* `../release/cacert.pem` - new certificates file.

## `updatecert.php`

The script updates certificates information on the release branch:
* It downloads newest `cacert.pem` from https://curl.se/ca/cacert.pem
* It extracts certificates from `cacert.pem` for specific domains that are contained in the `info.json`.
* Updates timestamp in the `info.json` if file gets too old.

Requires:
* `../prev_release` directory containing files from recent release branch: `cacert.pem` and `info.json`.
* `BUILD_PRIVATE_KEY` environment variable containing private key for signing `info.json` file.

The script output is:
* `../release/cacert.pem` - new certificates file, it does not exist if `../prev_release` already contains newest version of this file.
* `../release/info.json` - new release information file, it does not exists if the release information was unchanged.

## `keygen.php`

The script generates new private/public key pair and prints them.

On some platforms requires:
* `OPENSSL_CONF` environment variable set to `openssl.cnf` file as described in https://www.php.net/manual/en/openssl.installation.php.
