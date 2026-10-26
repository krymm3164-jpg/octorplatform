<?php
session_set_cookie_params(["httponly" => true, "samesite" => "Strict"]);
session_start();
header("Content-Type: application/json; charset=utf-8");

require __DIR__ . "/db.php";

function reply($ok, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(["ok" => $ok, "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(false, "طريقة الطلب غير صحيحة.", 405);
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    reply(false, "أدخل اسم المستخدم وكلمة المرور.", 400);
}

$stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username = :u");
$stmt->execute([":u" => $username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin["password_hash"])) {
    sleep(1); // إبطاء المحاولات الخاطئة
    reply(false, "اسم المستخدم أو كلمة المرور غير صحيحة.", 401);
}

session_regenerate_id(true);
$_SESSION["admin_id"] = $admin["id"];

reply(true, "تم تسجيل الدخول.");