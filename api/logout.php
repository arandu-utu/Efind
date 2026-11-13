<?php
/**
 * E-Find — Cierre de sesión
 * POST /api/logout.php
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit; }
session_destroy();
echo json_encode(['ok' => true]);
