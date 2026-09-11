<?php
include 'db.php';
$email = $_GET['email'];
$res = mysqli_query($conn, "SELECT avatar FROM users WHERE email='$email'");
if($u = mysqli_fetch_assoc($res)) {
    echo json_encode(['status' => 'success', 'image' => $u['avatar']]);
} else {
    echo json_encode(['status' => 'error']);
}
?>