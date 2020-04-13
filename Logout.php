<?php
session_start();
$_SESSION = array();
@session_destroy(); //@でエラー抑止
header("Location: Login.php");
