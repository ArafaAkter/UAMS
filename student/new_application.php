<?php
// student/new_application.php
// Redirect to apply.php (the actual application form)
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role('student');
redirect('student/apply.php');
