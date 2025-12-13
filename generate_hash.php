<?php
$password = "password123";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "Password: " . $password . "\n";
echo "Hash: " . $hashed_password . "\n";
?>