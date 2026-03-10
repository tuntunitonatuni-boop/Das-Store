<?php
// logout.php
require_once 'config.php';
require_once 'includes/auth.php';
session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit;
