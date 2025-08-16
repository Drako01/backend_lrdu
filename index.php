<?php
require_once __DIR__ . '/server/Server.php';


$server = new Server();
$server->handleRequest();
