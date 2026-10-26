<?php
header("Content-Type: application/json; charset=utf-8");

require __DIR__ . "/db.php";

// ربط اسم التخصص في الرابط بالاسم المحفوظ في القاعدة (نفس خيارات صفحة التسجيل)
$specialties = [
    "cardiology"    => "القلب والشرايين",
    "dentistry"     => "طب الأسنان",
    "ophthalmology" => "طب العيون",
    "pediatrics"    => "طب الأطفال",
    "dermatology"   => "الأمراض الجلدية",
    "orthopedics"   => "العظام والمفاصل",
    "neurology"     => "طب الأعصاب",
    "ent"           => "الأنف والأذن والحنجرة",
    "pulmonology"   => "الصدر والجهاز التنفسي",
    "internal"      => "الطب الباطني",
    "urology"       => "المسالك البولية",
    "gynecology"    => "النساء والولادة",
];

$slug = trim($_GET["specialty"] ?? "");
$specialtyName = null;

if ($slug !== "") {
    if (!isset($specialties[$slug])) {
        http_response_code(400);
        echo json_encode(["ok" => false, "message" => "تخصص غير معروف."], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $specialtyName = $specialties[$slug];
}

try {
    // الأطباء المعتمدون فقط، وبالحقول العامة فقط
    // (البريد ورقم الإجازة وكلمة المرور لا تُرجع أبداً)
    $sql = "SELECT id, full_name, specialization, degree, experience_years,
                   clinic_name, address, work_hours, phone, whatsapp, maps_url, bio
            FROM doctors
            WHERE status = 'approved'";
    $params = [];

    if ($specialtyName !== null) {
        $sql .= " AND specialization = :spec";
        $params[":spec"] = $specialtyName;
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();

    // السماح بروابط http/https فقط للخرائط (حماية من روابط خطرة)
    foreach ($doctors as &$d) {
        if ($d["maps_url"] !== null && !preg_match('#^https?://#i', $d["maps_url"])) {
            $d["maps_url"] = null;
        }
    }
    unset($d);

    echo json_encode(
        ["ok" => true, "specialty" => $specialtyName, "doctors" => $doctors],
        JSON_UNESCAPED_UNICODE
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "تعذر تحميل الأطباء."], JSON_UNESCAPED_UNICODE);
}