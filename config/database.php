<?php

$DB_HOST = "localhost";
$DB_NAME = "u853944306_Oopscompiler";
$DB_USER = "u853944306_Oopscompiler";
$DB_PASS = "OopsCompiler7#";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}