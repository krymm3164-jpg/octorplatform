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

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    reply(false, "أدخل البريد الإلكتروني وكلمة المرور.", 400);
}

$stmt = $pdo->prepare("SELECT id, password_hash, status FROM doctors WHERE email = :e");
$stmt->execute([":e" => $email]);
$doctor = $stmt->fetch();

if (!$doctor || !password_verify($password, $doctor["password_hash"])) {
    sleep(1); // إبطاء المحاولات الخاطئة
    reply(false, "البريد الإلكتروني أو كلمة المرور غير صحيحة.", 401);
}

// نكشف حالة الطلب فقط بعد التأكد من كلمة المرور
if ($doctor["status"] === "pending") {
    reply(false, "طلب تسجيلك ما زال قيد المراجعة. سنفعّل حسابك بعد التحقق من بياناتك.", 403);
}
if ($doctor["status"] === "rejected") {
    reply(false, "تم رفض طلب التسجيل. لمزيد من المعلومات تواصل مع إدارة المنصة.", 403);
}

session_regenerate_id(true);
$_SESSION["doctor_id"] = $doctor["id"];

reply(true, "تم تسجيل الدخول.");