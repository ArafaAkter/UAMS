<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? e($page_title) . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">
</head>
<body>
    <header class="main-header">
        <div class="container header-container">
            <a href="<?php echo base_url(); ?>" class="logo">
                <span class="logo-icon">🎓</span>
                <span class="logo-text"><?php echo APP_NAME; ?></span>
            </a>
            <nav class="main-nav">
                <?php if (is_logged_in()): ?>
                    <span class="welcome-msg">Welcome, <?php echo e($_SESSION['full_name']); ?></span>
                    <a href="<?php echo base_url('logout.php'); ?>" class="btn btn-logout">Logout</a>
                <?php else: ?>
                    <a href="<?php echo base_url('login.php'); ?>" class="btn btn-login">Login</a>
                    <a href="<?php echo base_url('register.php'); ?>" class="btn btn-register">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="main-content">
