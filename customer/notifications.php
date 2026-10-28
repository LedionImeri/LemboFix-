<?php
// Notifications are shared by every role and live at /notifications.php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login(ROLE_CUSTOMER);
redirect('notifications.php');
