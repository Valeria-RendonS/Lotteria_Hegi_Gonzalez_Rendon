<?php
    session_start();
    session_unset();
    session_destroy();

    // impedisce al browser di tornare indietro alla pagina cachata
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
    header("Location: index.html");
    exit;
?>
