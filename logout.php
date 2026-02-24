<?php
session_start();

unset($_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['email'], $_SESSION['phone'], $_SESSION['role']);

header('Location: login.php');
exit();
?>
