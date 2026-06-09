<?php
// =============================================================
// Taxi Financial Tracker – Configuration
// =============================================================
// Copy this file and adjust the values for your environment.
// =============================================================

// ----- Database -----------------------------------------------
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'wjexqgiq_taxi_tracker';
const DB_USER = 'wjexqgiq_taxi_tracker';
const DB_PASS = 'l,Z,d{]jnsW?';
const DB_CHARSET = 'utf8mb4';

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
const ALLOW_USER_MODIFICATIONS = true;

// ----- Session ------------------------------------------------
// Change to a long random string in production.
const SESSION_NAME = 'vxJO60VzbgkiPXKnUpSs30isuDY8VeA2Ou4dVM3Qjb1w85vFuTbZl5o4YThrJSSv';
