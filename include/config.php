<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'menu_management';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}
