<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();

header('Location: dashboard.php');
exit();
