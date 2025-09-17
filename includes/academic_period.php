<?php
// includes/academic_period.php

require_once '../config/config.php'; // Make sure you have this file to connect to your database

function getCurrentAcademicPeriod($db) {
    $query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
