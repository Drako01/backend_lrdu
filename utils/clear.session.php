<?php
include_once __DIR__ . '/../managers/session.mannager.php';

SessionManager::destroySession();

echo "Sesión borrada con éxito.";
