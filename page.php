<html>
<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    <a class="button-big" onclick="wrap(install);" href="javascript:// Download and Install WordPress">Download and Install<br>WordPress</a>
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
            <td><br><a class="button-small" href="javascript:// Change Version">Change Version</a></td>
            <td><br><a class="button-small" href="javascript:// Change Language">Change Language</a></td>
            <td><br><a class="button-small" href="javascript:// Upload custom ZIP file">Install custom ZIP file</a></td>
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
            <td><br><a class="button-small" href="javascript:// Set Subfolder">Set Subfolder</a></td>
            <td><br><a class="button-small" href="javascript:// Advanced Options">Advanced Options</a></td>
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


<!--div id="drop-area">
    Drop your custom ZIP file here to install it.
</div-->

</body>