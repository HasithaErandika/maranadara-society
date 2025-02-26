<?php

define('APP_START', true);
$plain_password = 'admin123';
$hash = password_hash($plain_password, PASSWORD_DEFAULT);
echo "Generated Hash: " . $hash . "<br>";
if (password_verify($plain_password, '$2y$10$W9Qz7X1gL7Qz7X1gL7Qz7uW9Qz7X1gL7Qz7X1gL7Qz7uW9Qz7X1gL')) {
    echo "Hash matches admin123!";
} else {
    echo "Hash does NOT match admin123.";
}
