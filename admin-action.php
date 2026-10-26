<?php
session_set_cookie_params(["httponly" => true, "samesite" => "Strict"]);
session_start();
header("Content-Type: application/json; charset=utf-8");

function reply($ok, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(["ok" => $ok, "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// لازم يكون أدمن مسجّل دخول
if (empty($_SESSION["admin_id"])) {
    reply(false, "غير مصرح. سجّل الدخول أولاً.", 401);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(false, "طريقة الطلب غير صحيحة.", 405);
}

// التحقق من رمز الحماية
$csrf = $_POST["csrf"] ?? "";
if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $csrf)) {
    reply(false, "طلب غير صالح. حدّث الصفحة وحاول مرة أخرى.", 403);
}

$id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

if (!$id || !in_array($action, ["approve", "reject"], true)) {
    reply(false, "بيانات غير صحيحة.", 400);
}

require __DIR__ . "/db.php";

$newStatus = $action === "approve" ? "approved" : "rejected";

try {
    // نغيّر الحالة فقط للطلبات المنتظرة
    $stmt = $pdo->prepare("UPDATE doctors SET status = :s WHERE id = :id AND status = 'pending'");
    $stmt->execute([":s" => $newStatus, ":id" => $id]);

    if ($stmt->rowCount() === 0) {
        reply(false, "هذا الطلب غير موجود أو تمت معالجته مسبقاً.", 404);
    }

    reply(true, $action === "approve" ? "✅ تمت الموافقة على الطبيب." : "تم رفض الطلب.");
} catch (PDOException $e) {
    reply(false, "حدث خطأ أثناء الحفظ.", 500);
}
