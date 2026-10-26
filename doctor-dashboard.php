<?php
session_set_cookie_params(["httponly" => true, "samesite" => "Strict"]);
session_start();

// إذا ما في تسجيل دخول، رجّعه لصفحة الدخول
if (empty($_SESSION["doctor_id"])) {
    header("Location: doctor-login.html");
    exit;
}

require __DIR__ . "/db.php";

function e($text) {
    return htmlspecialchars((string)($text ?? ""), ENT_QUOTES, "UTF-8");
}

// رمز حماية لحفظ الجدول (CSRF)
if (empty($_SESSION["doctor_csrf"])) {
    $_SESSION["doctor_csrf"] = bin2hex(random_bytes(32));
}

// الطبيب يشوف بياناته هو فقط، وبشرط أن يكون حسابه معتمداً
$stmt = $pdo->prepare(
    "SELECT full_name, email, phone, whatsapp, specialization, degree,
            experience_years, license_number, clinic_name, address, work_hours, status
     FROM doctors
     WHERE id = :id AND status = 'approved'"
);
$stmt->execute([":id" => $_SESSION["doctor_id"]]);
$doctor = $stmt->fetch();

// إذا تغيّرت حالته (مثلاً تم تعليقه) نخرجه
if (!$doctor) {
    unset($_SESSION["doctor_id"]);
    header("Location: doctor-login.html");
    exit;
}

// جدول الدوام الحالي
$days = ["السبت", "الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس", "الجمعة"];

$stmt = $pdo->prepare(
    "SELECT day_of_week, start_time, end_time, slot_minutes
     FROM doctor_schedule
     WHERE doctor_id = :id"
);
$stmt->execute([":id" => $_SESSION["doctor_id"]]);

$schedule = [];
$currentSlot = 30;
foreach ($stmt->fetchAll() as $row) {
    $schedule[(int)$row["day_of_week"]] = [
        "start" => substr($row["start_time"], 0, 5),
        "end"   => substr($row["end_time"], 0, 5),
    ];
    $currentSlot = (int)$row["slot_minutes"];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الطبيب | منصة الأطباء</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .doc-panel { text-align: right; max-width: 700px; margin: 0 auto 20px; }
        .doc-panel .doc-line { margin-bottom: 6px; color: #dfe2f3; }
        .doc-panel .doc-private { color: #ffd479; font-weight: 700; }
        .status-badge { display: inline-block; padding: 4px 14px; border-radius: 999px;
                        background: rgba(46,160,90,.15); border: 1px solid #2ea05a; color: #7ee2a8; font-weight: 700; }
        .coming-soon { text-align: center; color: var(--muted); padding: 20px 10px; }

        .schedule-card { text-align: right; max-width: 700px; margin: 0 auto 20px; }
        .day-row { display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
                   padding: 10px 0; border-bottom: 1px solid rgba(158,165,204,.15); }
        .day-row:last-of-type { border-bottom: none; }
        .day-row label.day-name { display: flex; align-items: center; gap: 8px; margin: 0; min-width: 120px; color: var(--gold-light); }
        .day-row input[type="checkbox"] { width: auto; }
        .day-row .time-box { display: flex; align-items: center; gap: 8px; }
        .day-row input[type="time"] { width: auto; padding: 8px 10px; }
        .slot-select { max-width: 220px; }
        .form-msg { margin-top: 18px; padding: 12px 16px; border-radius: 12px; font-weight: 700; text-align: center; }
        .form-msg.success { background: rgba(46,160,90,.15); border: 1px solid #2ea05a; color: #7ee2a8; }
        .form-msg.error   { background: rgba(220,70,70,.15); border: 1px solid #dc4646; color: #ff9a9a; }
    </style>
</head>
<body>

<header>
    <h1><span class="logo-icon">🏥</span> منصة الأطباء</h1>
    <nav>
        <a href="index.html">الرئيسية</a>
        <a href="doctors.html">الأطباء</a>
        <a href="doctor-logout.php">تسجيل الخروج</a>
    </nav>
</header>

<main>
    <section>
        <div class="section-heading">
            <div><span>لوحة الطبيب</span><h2>👋 أهلاً بك، <?= e($doctor["full_name"]) ?></h2></div>
        </div>

        <div class="doctor-card doc-panel">
            <p><span class="status-badge">✅ حسابك معتمد</span></p>
            <p class="doc-line">🩺 التخصص: <?= e($doctor["specialization"]) ?></p>
            <p class="doc-line">🎓 الشهادة: <?= e($doctor["degree"]) ?></p>
            <p class="doc-line">⏳ الخبرة: <?= e($doctor["experience_years"]) ?> سنة</p>
            <p class="doc-line">🏥 العيادة: <?= e($doctor["clinic_name"]) ?></p>
            <p class="doc-line">📌 العنوان: <?= e($doctor["address"]) ?></p>
            <p class="doc-line">🕒 الدوام: <?= e($doctor["work_hours"]) ?></p>
            <p class="doc-line">📞 الهاتف: <?= e($doctor["phone"]) ?></p>
            <p class="doc-line">💬 واتساب: <?= e($doctor["whatsapp"]) ?></p>
            <p class="doc-line doc-private">✉️ بريدك (لا يظهر للمرضى): <?= e($doctor["email"]) ?></p>
            <p class="doc-line doc-private">🪪 رقم الإجازة (لا يظهر للمرضى): <?= e($doctor["license_number"]) ?></p>
        </div>

        <!-- ===== جدول الدوام ===== -->
        <div class="doctor-card schedule-card">
            <h3>🗓️ جدول الدوام</h3>
            <p class="doc-line">حدد أيام دوامك وأوقاته، والمرضى يحجزون مواعيد جاهزة ضمنها.</p>

            <form id="scheduleForm" style="max-width:none; margin:0; padding:0; background:none; border:none; box-shadow:none; backdrop-filter:none;">
                <input type="hidden" name="csrf" value="<?= e($_SESSION["doctor_csrf"]) ?>">

                <?php foreach ($days as $i => $dayName):
                    $isOpen = isset($schedule[$i]);
                    $start = $isOpen ? $schedule[$i]["start"] : "17:00";
                    $end   = $isOpen ? $schedule[$i]["end"]   : "20:00";
                ?>
                    <div class="day-row">
                        <label class="day-name">
                            <input type="checkbox" name="open[<?= $i ?>]" value="1" <?= $isOpen ? "checked" : "" ?>>
                            <?= e($dayName) ?>
                        </label>
                        <div class="time-box">
                            <span>من</span>
                            <input type="time" name="start[<?= $i ?>]" value="<?= e($start) ?>">
                            <span>إلى</span>
                            <input type="time" name="end[<?= $i ?>]" value="<?= e($end) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>

                <label for="slot_minutes">مدة الموعد الواحد</label>
                <select id="slot_minutes" name="slot_minutes" class="slot-select">
                    <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
                        <option value="<?= $m ?>" <?= $m === $currentSlot ? "selected" : "" ?>><?= $m ?> دقيقة</option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" id="saveBtn" style="margin-top:20px;">💾 حفظ الجدول</button>
                <p id="scheduleMsg" class="form-msg" hidden></p>
            </form>
        </div>

        <p class="coming-soon">حجوزات المرضى ستظهر هنا في الخطوات القادمة.</p>
    </section>
</main>

<footer>
    <p>© 2026 منصة الأطباء</p>
</footer>

<script>
const form = document.getElementById("scheduleForm");
const msg = document.getElementById("scheduleMsg");
const btn = document.getElementById("saveBtn");

function showMsg(text, type) {
    msg.hidden = false;
    msg.className = "form-msg " + type;
    msg.textContent = text;
}

form.addEventListener("submit", async function (e) {
    e.preventDefault();
    btn.disabled = true;

    try {
        const response = await fetch("doctor-schedule.php", {
            method: "POST",
            body: new FormData(form)
        });
        const data = await response.json();
        showMsg(data.ok ? data.message : "❌ " + data.message, data.ok ? "success" : "error");
    } catch (err) {
        showMsg("❌ تعذر الاتصال بالخادم. تأكد أن Apache شغال وأنك فاتح الصفحة من localhost.", "error");
    }

    btn.disabled = false;
});
</script>

</body>
</html>