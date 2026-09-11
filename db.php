<?php
$conn = mysqli_connect("localhost", "root", "", "monitor_ai");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
