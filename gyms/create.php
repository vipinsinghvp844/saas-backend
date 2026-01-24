<?php
// gyms/create.php

require_once "../config/cors.php";
header("Content-Type: application/json");

require_once "../config/db.php";
require_once "../middleware/auth.php";
require_once "../middleware/roleGuard.php";
require_once "../mailer/send-template.php"; // ✅ NEW

/* ✅ SLUG GENERATOR */
function generateSlug(PDO $conn, string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    $base = $slug;
    $i = 1;

    while (true) {
        $stmt = $conn->prepare("SELECT id FROM gyms WHERE slug = :slug");
        $stmt->execute([":slug" => $slug]);

        if (!$stmt->fetch()) {
            break;
        }

        $slug = $base . '-' . $i;
        $i++;
    }

    return $slug;
}

/* ✅ PASSWORD GENERATOR (No confusing chars like 0/O, 1/I) */
function generatePassword(int $len = 8): string {
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    return substr(str_shuffle($chars), 0, $len);
}

try {

    /* 1️⃣ AUTH */
    $auth_user = authenticate();
    $GLOBALS['auth_user'] = $auth_user;
    requireRole(['super_admin']);

    /* 2️⃣ READ FORM DATA */
    $gym      = json_decode($_POST['gym'] ?? '', true);
    $owner    = json_decode($_POST['owner'] ?? '', true);
    $settings = json_decode($_POST['settings'] ?? '', true);

    $plan       = strtolower(trim($_POST['plan'] ?? 'free'));
    $trial_days = (int)($_POST['trial_days'] ?? 0);
    $status     = strtolower(trim($_POST['status'] ?? 'active'));

    /* 3️⃣ VALIDATION */
    if (
        empty($gym['gym_name']) ||
        empty($gym['gym_email']) ||
        empty($owner['owner_firstName']) ||
        empty($owner['owner_email']) ||
        empty($plan)
    ) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Required fields missing"
        ]);
        exit;
    }

    /* 4️⃣ DB */
    $db = new Database();
    $conn = $db->connect();

    /* 5️⃣ DUPLICATE CHECKS */
    $stmt = $conn->prepare("SELECT id FROM gyms WHERE email = :email");
    $stmt->execute([":email" => $gym['gym_email']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            "status" => false,
            "message" => "Gym email already exists"
        ]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([":email" => $owner['owner_email']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            "status" => false,
            "message" => "Owner email already exists"
        ]);
        exit;
    }

    /* 6️⃣ START TRANSACTION */
    $conn->beginTransaction();

    /* 7️⃣ SLUG */
    $slug = generateSlug($conn, $gym['gym_name']);

    /* 8️⃣ LOGO UPLOAD (OPTIONAL) */
    $logoPath = null;

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {

        $uploadDir = __DIR__ . "/../storage/uploads/logos/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ["jpg", "jpeg", "png", "webp"];

        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid logo file type. Only jpg, jpeg, png, webp allowed.");
        }

        $fileName = "gym_" . time() . "_" . rand(1000, 9999) . "." . $ext;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $fileName)) {
            throw new Exception("Logo upload failed");
        }

        $logoPath = "storage/uploads/logos/" . $fileName;
    }

    /* 9️⃣ INSERT GYM (only basic fields) */
    $stmt = $conn->prepare("
        INSERT INTO gyms
        (name, slug, email, phone, address, city, state, zip, logo, status, plan)
        VALUES
        (:name, :slug, :email, :phone, :address, :city, :state, :zip, :logo, :status, :plan)
    ");

    $stmt->execute([
        ":name"    => $gym['gym_name'],
        ":slug"    => $slug,
        ":email"   => $gym['gym_email'],
        ":phone"   => $gym['gym_phone'] ?? null,
        ":address" => $gym['gym_address'] ?? null,
        ":city"    => $gym['gym_city'] ?? null,
        ":state"   => $gym['gym_state'] ?? null,
        ":zip"     => $gym['gym_zip'] ?? null,
        ":logo"    => $logoPath,
        ":status"  => $status,
        ":plan"    => $plan
    ]);

    $gym_id = $conn->lastInsertId();

    /* 🔟 INSERT OWNER (users table) */
    $plainPassword = generatePassword(8);

    $stmt = $conn->prepare("
        INSERT INTO users
        (
            gym_id,
            first_name,
            last_name,
            email,
            password,
            role,
            status,
            force_password_change
        )
        VALUES
        (
            :gym_id,
            :first_name,
            :last_name,
            :email,
            :password,
            'gym_admin',
            'active',
            1
        )
    ");

    $stmt->execute([
        ":gym_id"     => $gym_id,
        ":first_name" => $owner['owner_firstName'],
        ":last_name"  => $owner['owner_LastName'] ?? null,
        ":email"      => $owner['owner_email'],
        ":password"   => password_hash($plainPassword, PASSWORD_DEFAULT),
    ]);

    /* ✅ 11️⃣ INSERT SUBSCRIPTION (gym_subscriptions table) */
    $stmt = $conn->prepare("
        INSERT INTO gym_subscriptions
        (gym_id, plan, trial_days, status)
        VALUES
        (:gym_id, :plan, :trial_days, 'active')
    ");

    $stmt->execute([
        ":gym_id"     => $gym_id,
        ":plan"       => $plan,
        ":trial_days" => $trial_days,
    ]);

    /* ✅ 12️⃣ INSERT SETTINGS (gym_settings table) */
    $stmt = $conn->prepare("
        INSERT INTO gym_settings
        (gym_id, settings_json)
        VALUES
        (:gym_id, :settings_json)
    ");

    $stmt->execute([
        ":gym_id" => $gym_id,
        ":settings_json" => json_encode($settings ?? [
            "send_welcome_email" => true,
            "auto_approve_members" => false,
            "enable_notifications" => true,
        ]),
    ]);

    /* ✅ 13️⃣ COMMIT */
    $conn->commit();

    /* ✅ 14️⃣ SEND EMAIL (TEMPLATE BASED ✅) */
    $loginUrl = "http://localhost:5173/login";

    sendTemplateMail(
        $owner['owner_email'],
        "gym-created-credentials",
        [
            "owner_name" => trim(($owner['owner_firstName'] ?? '') . " " . ($owner['owner_LastName'] ?? '')),
            "gym_name"   => $gym['gym_name'] ?? "Gym",
            "login_url"  => $loginUrl,
            "email"      => $owner['owner_email'],
            "password"   => $plainPassword
        ]
    );

    echo json_encode([
        "status" => true,
        "message" => "Gym + Owner + Subscription + Settings created successfully",
        "data" => [
            "gym_id" => $gym_id,
            "slug" => $slug
        ]
    ]);

} catch (Exception $e) {

    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
}
