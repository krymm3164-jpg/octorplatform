<?php
session_set_cookie_params(["httponly" => true, "samesite" => "Strict"]);
session_start();
header("Content-Type: application/json; charset=utf-8");

function reply($ok, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(["ok" => $ok, "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// لازم يكون طبيب مسجّل دخول
if (empty($_SESSION["doctor_id"])) {
    reply(false, "غير مصرح. سجّل الدخول أولاً.", 401);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(false, "طريقة الطلب غير صحيحة.", 405);
}

// التحقق من رمز الحماية
$csrf = $_POST["csrf"] ?? "";
if (empty($_SESSION["doctor_csrf"]) || !hash_equals($_SESSION["doctor_csrf"], $csrf)) {
    reply(false, "طلب غير صالح. حدّث الصفحة وحاول مرة أخرى.", 403);
}

require __DIR__ . "/db.php";

// الحساب لازم يكون معتمداً
$stmt = $pdo->prepare("SELECT id FROM doctors WHERE id = :id AND status = 'approved'");
$stmt->execute([":id" => $_SESSION["doctor_id"]]);
if (!$stmt->fetch()) {
    reply(false, "حسابك غير مفعّل.", 403);
}

// مدة الموعد
$allowedSlots = [10, 15, 20, 30, 45, 60];
$slot = (int)($_POST["slot_minutes"] ?? 0);
if (!in_array($slot, $allowedSlots, true)) {
    reply(false, "مدة الموعد غير صحيحة.", 400);
}

// قراءة أيام الدوام والتحقق منها
$timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';
$rows = [];

for ($d = 0; $d <= 6; $d++) {
    if (!isset($_POST["open"][$d])) {
        continue; // اليوم غير مفعّل
    }

    $start = $_POST["start"][$d] ?? "";
    $end   = $_POST["end"][$d] ?? "";

    if (!preg_match($timePattern, $start) || !preg_match($timePattern, $end)) {
        reply(false, "أدخل وقت بداية ونهاية صحيحين لكل يوم دوام.", 400);
    }

    if ($start >= $end) {
        reply(false, "وقت النهاية لازم يكون بعد وقت البداية.", 400);
    }

    [$sh, $sm] = array_map("intval", explode(":", $start));
    [$eh, $em] = array_map("intval", explode(":", $end));
    if ((($eh * 60 + $em) - ($sh * 60 + $sm)) < $slot) {
        reply(false, "فترة الدوام أقصر من مدة الموعد الواحد.", 400);
    }

    $rows[] = [$d, $start . ":00", $end . ":00"];
}

if (count($rows) === 0) {
    reply(false, "اختر يوم دوام واحد على الأقل.", 400);
}

try {
    $pdo->beginTransaction();

    // نستبدل الجدول القديم بالجديد
    $del = $pdo->prepare("DELETE FROM doctor_schedule WHERE doctor_id = :id");
    $del->execute([":id" => $_SESSION["doctor_id"]]);

    $ins = $pdo->prepare(
        "INSERT INTO doctor_schedule (doctor_id, day_of_week, start_time, end_time, slot_minutes)
         VALUES (:doc, :day, :start, :end, :slot)"
    );
    foreach ($rows as [$day, $start, $end]) {
        $ins->execute([
            ":doc"   => $_SESSION["doctor_id"],
            ":day"   => $day,
            ":start" => $start,
            ":end"   => $end,
            ":slot"  => $slot,
        ]);
    }

    $pdo->commit();
    reply(true, "✅ تم حفظ جدول الدوام.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    reply(false, "حدث خطأ أثناء الحفظ.", 500);
}
