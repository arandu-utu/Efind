<?php
/**
 * E-Find — Cierre de sesión
 * GET /api/logout.php
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
session_destroy();
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
