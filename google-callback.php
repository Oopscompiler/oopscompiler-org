<?php
session_start();
require 'config.php';

$conn = new mysqli("localhost", "root", "", "coding");
if ($conn->connect_error) {
    die("DB Error");
}

if (isset($_GET['code'])) {

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    $oauth = new Google\Service\Oauth2($client);
    $google_user = $oauth->userinfo->get();

    $email = $google_user->email;
    $name = $google_user->name;
    $google_id = $google_user->id;
    $email = $google_user->email;
    $name = $google_user->name;

    // First check by google_id
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE google_id = ?");
    $stmt->bind_param("s", $google_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $db_name);
    $stmt->fetch();

    if ($stmt->num_rows > 0) {

        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $db_name;

    } else {

        // Check if email exists (account created by local signup)
        $stmt2 = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $stmt2->store_result();
        $stmt2->bind_result($existing_id);
        $stmt2->fetch();

        if ($stmt2->num_rows > 0) {

            // Link Google to existing account
            $update = $conn->prepare("UPDATE users SET google_id = ?, provider='google' WHERE id = ?");
            $update->bind_param("si", $google_id, $existing_id);
            $update->execute();

            $_SESSION['user_id'] = $existing_id;
            $_SESSION['user_name'] = $name;

        } else {

            // Create new account
            $random_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

            $insert = $conn->prepare("
            INSERT INTO users (name, email, password_hash, google_id, provider)
            VALUES (?, ?, ?, ?, 'google')
        ");
            $insert->bind_param("ssss", $name, $email, $random_password, $google_id);
            $insert->execute();

            $_SESSION['user_id'] = $insert->insert_id;
            $_SESSION['user_name'] = $name;
        }
    }
    header("Location: home2.php");
    exit();
}