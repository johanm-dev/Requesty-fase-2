<?php
/**
 * Requesty - Front Controller Principal (MVC)
 * Punto de entrada único para la aplicación web
 */

// Reporte de errores en desarrollo
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Cargar el núcleo de la aplicación
require_once __DIR__ . '/app/core/App.php';

// Inicializar la aplicación
App::run();