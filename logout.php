<?php
require __DIR__ . '/includes/bootstrap.php';
if (auth()) { log_activity('auth.logout', 'User', (int) auth()['id'], 'Logged out'); }
logout_user();
session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
