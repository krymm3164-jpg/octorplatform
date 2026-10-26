<?php
session_set_cookie_params(["httponly" => true, "samesite" => "Strict"]);
session_start();

// إذا ما في تسجيل دخول، رجّعه لصفحة الدخول
if (empty($_SESSION["admin_id"])) {
    header("Location: admin-login.html");
    exit;
}

require __DIR__ . "/db.php";

// رمز حماية للأزرار (CSRF)
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

function e($text) {
    return htmlspecialchars((string)($text ?? ""), ENT_QUOTES, "UTF-8");
}

// الأطباء المنتظرون للمراجعة (الأقدم أولاً)
$stmt = $pdo->query(
    "SELECT id, full_name, email, phone, whatsapp, specialization, degree,
            experience_years, license_number, clinic_name, address, work_hours,
            maps_url, bio, created_at
     FROM doctors
     WHERE status = 'pending'
     ORDER BY created_at ASC"
);
$pending = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الأدمن | منصة الأطباء</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .req-card { text-align: right; margin-bottom: 20px; }
        .req-card h3 { margin-bottom: 6px; }
        .req-card .req-spec { color: var(--gold-light); font-weight: 700; margin-bottom: 10px; }
        .req-card .req-line { font-size: 0.95rem; margin-bottom: 4px; color: #dfe2f3; }
        .req-card .req-private { color: #ffd479; font-weight: 700; }
        .req-card .req-bio { font-size: 0.9rem; color: var(--muted); margin-top: 8px; }
        .req-actions { display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
        .btn-approve { background: linear-gradient(135deg, #7ee2a8, #2ea05a); color: #06210f; }
        .btn-reject  { background: linear-gradient(135deg, #ff9a9a, #dc4646); color: #2a0606; }
        .req-list { display: block; }
        .admin-note { text-align: center; color: var(--muted); padding: 30px 10px; }
        .admin-msg { margin: 0 0 16px; padding: 12px 16px; border-radius: 12px; font-weight: 700; text-align: center; }
        .admin-msg.success { background: rgba(46,160,90,.15); border: 1px solid #2ea05a; color: #7ee2a8; }
        .admin-msg.error   { background: rgba(220,70,70,.15); border: 1px solid #dc4646; color: #ff9a9a; }
    </style>
</head>
<body>

<header>
    <h1><span class="logo-icon">🏥</span> منصة الأطباء</h1>
    <nav>
        <a href="index.html">الرئيسية</a>
        <a href="doctors.html">الأطباء</a>
        <a href="admin-logout.php">تسجيل الخروج</a>
    </nav>
</header>

<main>
    <section>
        <div class="section-heading">
            <div><span>لوحة التحكم</span><h2>📋 طلبات الأطباء المنتظرة (<span id="pendingCount"><?= count($pending) ?></span>)</h2></div>
        </div>

        <p id="adminMsg" class="admin-msg" hidden></p>

        <div id="requestsList" class="req-list">
            <?php foreach ($pending as $d): ?>
                <div class="doctor-card req-card" data-id="<?= (int)$d["id"] ?>">
                    <h3><?= e($d["full_name"]) ?></h3>
                    <p class="req-spec"><?= e($d["specialization"]) ?></p>

                    <p class="req-line">🎓 <?= e($d["degree"]) ?></p>
                    <p class="req-line">⏳ خبرة <?= e($d["experience_years"]) ?> سنة</p>
                    <p class="req-line">🏥 <?= e($d["clinic_name"]) ?></p>
                    <p class="req-line">📌 <?= e($d["address"]) ?></p>
                    <p class="req-line">🕒 <?= e($d["work_hours"]) ?></p>
                    <p class="req-line">📞 <?= e($d["phone"]) ?></p>
                    <p class="req-line">💬 واتساب: <?= e($d["whatsapp"]) ?></p>

                    <p class="req-line req-private">✉️ البريد (خاص): <?= e($d["email"]) ?></p>
                    <p class="req-line req-private">🪪 رقم الإجازة (خاص): <?= e($d["license_number"]) ?></p>

                    <?php if (!empty($d["maps_url"]) && preg_match('#^https?://#i', $d["maps_url"])): ?>
                        <p class="req-line"><a href="<?= e($d["maps_url"]) ?>" target="_blank" rel="noopener">📍 الموقع على الخريطة</a></p>
                    <?php endif; ?>

                    <?php if (!empty($d["bio"])): ?>
                        <p class="req-bio"><?= e($d["bio"]) ?></p>
                    <?php endif; ?>

                    <p class="req-line">🗓️ تاريخ الطلب: <?= e($d["created_at"]) ?></p>

                    <div class="req-actions">
                        <button type="button" class="btn-approve" data-action="approve">✅ موافقة</button>
                        <button type="button" class="btn-reject" data-action="reject">❌ رفض</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p id="emptyNote" class="admin-note" <?= count($pending) ? "hidden" : "" ?>>لا توجد طلبات منتظرة حالياً.</p>
    </section>
</main>

<footer>
    <p>© 2026 منصة الأطباء</p>
</footer>

<script>
const csrfToken = <?= json_encode($_SESSION["csrf"]) ?>;
const list = document.getElementById("requestsList");
const countEl = document.getElementById("pendingCount");
const emptyNote = document.getElementById("emptyNote");
const adminMsg = document.getElementById("adminMsg");

function showMsg(text, type) {
    adminMsg.hidden = false;
    adminMsg.className = "admin-msg " + type;
    adminMsg.textContent = text;
}

list.addEventListener("click", async function (e) {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;

    const card = btn.closest(".req-card");
    const id = card.dataset.id;
    const action = btn.dataset.action;
    const name = card.querySelector("h3").textContent;

    const question = action === "approve"
        ? "الموافقة على الطبيب: " + name + " ؟ سيظهر ملفه للمرضى."
        : "رفض طلب الطبيب: " + name + " ؟";
    if (!confirm(question)) return;

    card.querySelectorAll("button").forEach(b => b.disabled = true);

    try {
        const body = new FormData();
        body.append("id", id);
        body.append("action", action);
        body.append("csrf", csrfToken);

        const response = await fetch("admin-action.php", { method: "POST", body });
        const data = await response.json();

        if (data.ok) {
            card.remove();
            const left = list.querySelectorAll(".req-card").length;
            countEl.textContent = left;
            emptyNote.hidden = left > 0;
            showMsg(data.message, "success");
        } else {
            card.querySelectorAll("button").forEach(b => b.disabled = false);
            showMsg("❌ " + data.message, "error");
        }
    } catch (err) {
        card.querySelectorAll("button").forEach(b => b.disabled = false);
        showMsg("❌ تعذر الاتصال بالخادم.", "error");
    }
});
</script>

</body>
</html>