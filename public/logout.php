<?php
require __DIR__ . '/../src/bootstrap.php';

$_SESSION = [];
session_destroy();
redirect('login.php');
