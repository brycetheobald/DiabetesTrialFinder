<?php
$db = new mysqli('127.0.0.1', 'root', 'root', 'diabetestrialfinder', 8889);
if ($db->connect_error) die('Database connection failed: ' . $db->connect_error);
$db->set_charset('utf8mb4');
