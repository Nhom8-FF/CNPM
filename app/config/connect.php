<?php 
    
    $server   = '127.0.0.1';  // Using IP instead of 'localhost'
    $user     = 'root';
    $pass     = '';
    $database = 'online_courses';
    

    $conn = new mysqli($server, $user, $pass, $database);

    // Kiểm tra kết nối
    if ($conn->connect_error) {
        die("Kết nối không thành công: " . $conn->connect_error);
    } else {
        $conn->query("SET NAMES 'utf8'");
    }
?>
