<?php
// student/applications.php
// Redirect to my_applications.php (canonical page)
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role('student');
redirect('student/my_applications.php');
