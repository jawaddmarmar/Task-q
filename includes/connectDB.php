<?php

$conn = new mysqli("localhost", "root", "", "smart_project_manager");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>