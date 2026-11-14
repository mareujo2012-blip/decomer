<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header_remove('X-Powered-By');

function pastaPrivada(): string
{
    $candidatas = [
        dirname(__DIR__).'/decomer-privado',
        dirname(__DIR__, 2).'/decomer-privado',
    ];
    $documento = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $www = $documento !== '' ? realpath($documento) : false;
    foreach ($candidatas as $pasta) {
        $real = realpath($pasta);
        if ($www !== false && $real !== false && $real !== $www && !in_array('public_html', explode(DIRECTORY_SEPARATOR,$real),true)
            && !str_starts_with($real, rtrim($www, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            && is_file($real.'/app.php')) {
            return $real;
        }
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit('Loja indisponível. Confira a instalação da pasta privada.');
}

define('DECOMER_PRIVADO', pastaPrivada());
require DECOMER_PRIVADO.'/app.php';
segurancaHttp();
