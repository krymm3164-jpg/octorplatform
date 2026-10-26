<?php
session_set_cookie_params(["httponly" => true, "samesite" => "Strict"]);
session_start();

// نخرج الطبيب فقط (حتى لا نؤثر على جلسة الأدمن إذا كانت بنفس المتصفح)
unset($_SESSION["doctor_id"]);
session_regenerate_id(true);

header("Location: doctor-login.html");
exit;