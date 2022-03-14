<html>
<head>
<script>
<?php include('page.js') ?>
<?php if (do_test()) { include('test.js'); } ?>
</script>
<style>
<?php include('page.css') ?>
</style>
</head>
<body>

<div id="screen-progress" class="screen">

</div>

<div id="screen-options" class="screen" style="text-align: center;">
    <a id="install" class="button-big" onclick="wrap(install);" href="javascript:// Download and Install WordPress">Download and Install<br>WordPress</a>
    <br><br>
    <table class="details">
        <tr>
            <td width="33%"><br>Version</td>
            <td width="34%"><br>Language</td>
            <td width="33%"><br>Download from</td>
        </tr>
        <tr>
            <td><span id="version"></span></td>
            <td><span id="lang"></span></td>
            <td>wordpress.org</td>
        </tr>
        <tr>
            <td><br><a class="button-small" onclick="wrap(setRelease)" href="javascript:// Change Version">Change Version</a></td>
            <td><br><a class="button-small" onclick="wrap(setLanguage)" href="javascript:// Change Language">Change Language</a></td>
            <td><br><a class="button-small" onclick="wrap(uploadZip)" href="javascript:// Upload custom ZIP file">Install custom ZIP file</a></td>
        </tr>
    </table><br>
    <table class="details">
        <tr>
            <td width="50%"><br>Destination URL</td>
            <td width="50%"><br>Advanced options</td>
        </tr>
        <tr>
            <td><span id="destination">http://example.com/</span></td>
            <td><span id="adv-options">None</span></td>
        </tr>
        <tr>
            <td><br><a class="button-small" onclick="wrap(setFolder)" href="javascript:// Set Subfolder">Set Subfolder</a></td>
            <td><br><a class="button-small" onclick="wrap(setChmod)" href="javascript:// Advanced Options">Advanced Options</a></td>
        </tr>
    </table>
</div>

<div id="screen-error" class="screen">
    <h1>Error</h1>
    <span id="error-message"></span><br><br>
    <table>
        <tr>
            <td width="33%"><a onclick="wrap(retry)" class="button-small" href="javascript:// Retry">Retry</a></td>
            <td>It will start the download and install process from the beginning.
                All information obtained so far, including user input, will be lost.<br></td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td width="33%"><a onclick="wrap(abort)" class="button-small" href="javascript:// Abort">Abort</a></td>
            <td>It will remove all the temporary files and the downloader itself.
                If there are partially unpacked WordPress files, they will remain on
                the server. You have to delete them manually.<br></td>
        </tr>
    </table>
</div>

<div id="screen-update" class="screen">
    <h1>Automatic Update</h1>
    There is a new version of WordPress Downloader.<br>
    Your version: <span id="current-wpd-version"></span><br>
    Latest version: <span id="new-wpd-version"></span>
    <br><br>
    <table>
        <tr>
            <td width="33%"><a onclick="wrap(do_update, false)" class="button-small" href="javascript:// Update">Update</a></td>
            <td>WordPress Downloader will update itself and restart in a new version.<br></td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td width="33%"><a onclick="wrap(do_update, true)" class="button-small" href="javascript:// Update and download">Update and download</a></td>
            <td>WordPress Downloader will update itself and restart in a new version.
                Additionally, your browser will download copy of updated version to your local system.<br></td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td width="33%"><a onclick="wrap(load_releases)" class="button-small" href="javascript:// Skip">Skip</a></td>
            <td>Do not update now (not recommended).<br></td>
        </tr>
    </table>
</div>

<div id="popup-folder" class="popup"><br><br>
<div>
    <h1>Select folder</h1>
    <input type="text" value="" class="text-input" id="folder" onkeydown="fixFolderName(this)" onkeyup="fixFolderName(this)" onkeypress="fixFolderName(this, event)"></input>
    <br><br>
    <div style="text-align: center">
    <a onclick="wrap(folderSelected)" class="button-small" href="javascript:// OK">OK</a>
    </div>
</div>
</div>

<div id="popup-releases" class="popup"><br><br>
<div>
    <h1>Select release</h1>
    <div id="releases-list"></div>
    <br>
    <div style="text-align: center">
    <a onclick="wrap(setVersion)" class="button-small" href="javascript:// Cancel">Cancel</a>
    </div>
</div>
</div>

<div id="popup-chmod" class="popup"><br><br>
<div>
    <h1>Advanced options</h1>
    <input type="checkbox" id="chmod" onchange="updateChmod()">
    <label for="chmod">Change file permissions (chmod) after unpacking WordPress.</label>
    <table id="chmod_values">
        <tr><td>
            <input type="text" style="width: 80px" value="0755" class="text-input" id="chmod_php" onkeydown="fixChmod(this)" onkeyup="fixChmod(this)" onkeypress="fixChmod(this, event)"></input>
        </td><td>
            PHP files linux permissions
        </td></tr>
        <tr><td>
            <input type="text" style="width: 80px" value="0644" class="text-input" id="chmod_other" onkeydown="fixChmod(this)" onkeyup="fixChmod(this)" onkeypress="fixChmod(this, event)"></input>
        </td><td>
            Other files linux permissions
        </td></tr>
    </table>
    <br><br>
    <div style="text-align: center">
    <a onclick="wrap(chmodSelected)" class="button-small" href="javascript:// OK">OK</a>
    </div>
</div>
</div>

<!--div id="drop-area">
    Drop your custom ZIP file here to install it.
</div-->

</body>