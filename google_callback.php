<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';
require_once __DIR__ . "/config/database.php";

if (!isset($_GET['code'])) {
    die("No Google auth code received.");
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    die("Google token error: " . htmlspecialchars($token['error']));
}

$client->setAccessToken($token);

$oauth = new Google\Service\Oauth2($client);
$google_user = $oauth->userinfo->get();

if (!$google_user) {
    die("Failed to fetch Google user info.");
}

$email = $google_user->email ?? '';
$name = $google_user->name ?? '';
$google_id = $google_user->id ?? '';

if ($email === '' || $google_id === '') {
    die("Missing Google user data.");
}

$stmt = $conn->prepare("SELECT id, name FROM users WHERE google_id = ?");
if (!$stmt) {
    die("Prepare failed (google_id lookup): " . $conn->error);
}
$stmt->bind_param("s", $google_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $db_name);
$stmt->fetch();

if ($stmt->num_rows > 0) {
    $_SESSION['user_id'] = $id;
    $_SESSION['user_name'] = $db_name;
    header("Location: index.php");
    exit();
}

$stmt2 = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$stmt2) {
    die("Prepare failed (email lookup): " . $conn->error);
}
$stmt2->bind_param("s", $email);
$stmt2->execute();
$stmt2->store_result();
$stmt2->bind_result($existing_id);
$stmt2->fetch();

if ($stmt2->num_rows > 0) {
    $update = $conn->prepare("UPDATE users SET google_id = ?, provider='google' WHERE id = ?");
    if (!$update) {
        die("Prepare failed (update user): " . $conn->error);
    }
    $update->bind_param("si", $google_id, $existing_id);
    if (!$update->execute()) {
        die("Update failed: " . $update->error);
    }

    $_SESSION['user_id'] = $existing_id;
    $_SESSION['user_name'] = $name;
    header("Location: index.php");
    exit();
}

$random_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

$insert = $conn->prepare("
    INSERT INTO users (name, email, password_hash, google_id, provider)
    VALUES (?, ?, ?, ?, 'google')
");
if (!$insert) {
    die("Prepare failed (insert user): " . $conn->error);
}
$insert->bind_param("ssss", $name, $email, $random_password, $google_id);
if (!$insert->execute()) {
    die("Insert failed: " . $insert->error);
}

$_SESSION['user_id'] = $insert->insert_id;
$_SESSION['user_name'] = $name;

header("Location: index.php");
exit();