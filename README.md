# WordPress Downloader

This script simplifies download and installation of the WordPress.

You have to place a single `wp_downloader.php` file to your server and start the installation with just **one click**.

## Download

Go to [releases](https://github.com/kildom/wp_downloader/releases/latest) to download latest `wp_downloader.php` file.

## Fetures

* Contained in a single `.php` file.
* Automatically downloads releases from `wordpress.org`.
* Allows selecting of different versions and languages of WordPress. ***(TODO)***
* Allows installing a custom ZIP file. ***(TODO)***
* Usues secure connections (HTTPS) between servers or digital signature/hash verification to provide full transfer security.
* Supports automatic self updates, so you don't need to care about updating downloader manually.

## Rationale

Normally, you have to download release ZIP file, unpack it, send over **2500 small files** to your server and then start the installation.
This approach is especially inconvenient if you have unstable or slow FTP connection or you want to do it fast.

This downloader resolves above inconvenience.
