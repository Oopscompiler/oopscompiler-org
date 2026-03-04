<?php

require __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();

$client->setClientId('860043806268-o52tjh30feinen7o3g2jeagngdrqa2i0.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/studentapp/src/google-callback.php');

$client->addScope("email");
$client->addScope("profile");