<?php
header("Content-Type: application/json; charset=utf-8");

require __DIR__ . "/db.php";

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(["ok" => false, "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    fail("طريقة الطلب غير صحيحة.", 405);
}

// قراءة الحقول وتنظيفها
function field($name) {
    return trim($_POST[$name] ?? "");
}

$full_name        = field("full_name");
$email            = field("email");
$password         = $_POST["password"] ?? "";
$password2        = $_POST["password2"] ?? "";
$phone            = field("phone");
$whatsapp         = field("whatsapp");
$specialization   = field("specialization");
$degree           = field("degree");
$experience_years = field("experience_years");
$license_number   = field("license_number");
$clinic_name      = field("clinic_name");
$address          = field("address");
$work_hours       = field("work_hours");
$maps_url         = field("maps_url");
$bio              = field("bio");

// التحقق من الحقول المطلوبة
$required = [$full_name, $email, $password, $phone, $whatsapp, $specialization,
             $degree, $experience_years, $license_number, $clinic_name, $address, $work_hours];
foreach ($required as $value) {
    if ($value === "") {
        fail("الرجاء تعبئة جميع الحقول المطلوبة.");
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("البريد الإلكتروني غير صحيح.");
}

if (strlen($password) < 8) {
    fail("كلمة المرور يجب أن تكون 8 أحرف على الأقل.");
}

if ($password !== $password2) {
    fail("كلمتا المرور غير متطابقتين.");
}

if (!ctype_digit($experience_years) || (int)$experience_years > 70) {
    fail("سنوات الخبرة غير صحيحة.");
}

if ($maps_url !== "" && !filter_var($maps_url, FILTER_VALIDATE_URL)) {
    fail("رابط الخرائط غير صحيح.");
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO doctors
        (full_name, email, password_hash, phone, whatsapp, specialization, degree,
         experience_years, license_number, clinic_name, address, work_hours, maps_url, bio)
        VALUES
        (:full_name, :email, :password_hash, :phone, :whatsapp, :specialization, :degree,
         :experience_years, :license_number, :clinic_name, :address, :work_hours, :maps_url, :bio)"
    );

    $stmt->execute([
        ":full_name"        => $full_name,
        ":email"            => $email,
        ":password_hash"    => $password_hash,
        ":phone"            => $phone,
        ":whatsapp"         => $whatsapp,
        ":specialization"   => $specialization,
        ":degree"           => $degree,
        ":experience_years" => (int)$experience_years,
        ":license_number"   => $license_number,
        ":clinic_name"      => $clinic_name,
        ":address"          => $address,
        ":work_hours"       => $work_hours,
        ":maps_url"         => $maps_url !== "" ? $maps_url : null,
        ":bio"              => $bio !== "" ? $bio : null,
    ]);

    echo json_encode(
        ["ok" => true, "message" => "✅ تم استلام طلبك وسيتم مراجعته قريباً."],
        JSON_UNESCAPED_UNICODE
    );
} catch (PDOException $e) {
    // 23000 = تكرار قيمة فريدة (البريد مسجل مسبقاً)
    if ($e->getCode() === "23000") {
        fail("هذا البريد الإلكتروني مسجل مسبقاً.", 409);
    }
    fail("حدث خطأ أثناء الحفظ، حاول مرة أخرى.", 500);
}
