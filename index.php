<?php

// index.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Order.php';
require_once __DIR__ . '/services/KitchenService.php';
require_once __DIR__ . '/Router.php';

$router = new Router();
$router->route();