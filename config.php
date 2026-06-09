<?php
// =============================================================
// Taxi Financial Tracker – Configuration
// =============================================================
// Copy this file and adjust the values for your environment.
// =============================================================

// ----- Database -----------------------------------------------
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'taxi_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ----- Feature flags ------------------------------------------
/**
 * ALLOW_USER_MODIFICATIONS
 *
 * When set to FALSE the backend will reject every INSERT, UPDATE,
 * and DELETE request that targets the `users` table, even when the
 * request comes from an Admin account.
 * The frontend will also hide the "Add / Edit / Delete" buttons for
 * the Users section when this value is false.
 */
define('ALLOW_USER_MODIFICATIONS', true);

// ----- Session ------------------------------------------------
// Change to a long random string in production.
define('SESSION_NAME', 'taxi_tracker_session');
