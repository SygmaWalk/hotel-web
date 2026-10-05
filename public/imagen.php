<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/RoomImages.php';
$id = field($_GET, 'id');
if (!positiveId($id) || !($photo = hotel()->image((int) $id))) fail(404, 'La imagen no existe.');
if (!$photo['active']) requireUser(true);
if (!preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D', $photo['filename'])) fail(404, 'La imagen no existe.');
$path = RoomImages::directory() . '/' . $photo['filename'];
if (!is_file($path)) fail(404, 'La imagen no está disponible.');
session_write_close();
header('Content-Type: ' . $photo['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $photo['filename'] . '"');
header("Content-Security-Policy: default-src 'none'; sandbox");
readfile($path);
