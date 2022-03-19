# WordPress Downloader

This script simplifies download and installation of the WordPress.

You have to place a [`wp_downloader.php`](https://github.com/kildom/wp_downloader/releases/latest/download/wp_downloader.php) file to your server and start the installation with just **one click**.

## Download

Just click this link: [`wp_downloader.php`](https://github.com/kildom/wp_downloader/releases/latest/download/wp_downloader.php) 

## Demo

![](resources/screen.apng)

## Fetures

* Contained in a single `.php` file.
* Automatically download the releases from `wordpress.org`.
* Selection of a different version and language of WordPress.
* Installation a custom (uploaded by the user) ZIP file.
* Secure connections (HTTPS) between servers and digital signature/hash verification provides full transfer security.
* Automatic self updates, so you don't need to care about downloading a newer version of the downloaded manually.

## Rationale

Normally, you have to download a release ZIP file, unpack it, send over **2500 small files** to your server and then start the installation.
This approach is especially inconvenient if you have an unstable or slow FTP connection.

The downloader resolves above inconvenience.
