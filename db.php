<?php
// إعدادات الاتصال بقاعدة البيانات (الإعدادات الافتراضية لـ XAMPP)
$host = "localhost";
$dbname = "doctor_platform";
$user = "root";
$pass = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["ok" => false, "message" => "تعذر الاتصال بقاعدة البيانات."]);
    exit;
}
