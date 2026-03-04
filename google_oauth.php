<?php
require 'config.php';

header('Location: ' . $client->createAuthUrl());
exit();