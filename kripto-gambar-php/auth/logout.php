<?php
require_once __DIR__ . '/../config/config.php';
logoutUser();
header('Location: ' . url('/auth/login.php'));
exit;
