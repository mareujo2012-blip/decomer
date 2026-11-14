<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

$id = $_GET['id'] ?? '';
$tamanho = $_GET['t'] ?? '';
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '',['GET','HEAD'],true) || !is_string($id)
    || preg_match('/\A[a-f0-9]{32}\z/D',$id) !== 1 || !in_array($tamanho,['400','940'],true)) {
    http_response_code(404);
    exit;
}
$arquivo = pastaDados().'/fotos/'.$id.'-'.$tamanho.'.webp';
if (!is_file($arquivo) || is_link($arquivo) || is_link(dirname($arquivo))) {
    http_response_code(404);
    exit;
}
$foto = @fopen($arquivo,'rb');
if ($foto === false) { http_response_code(404); exit; }
header('Content-Type: image/webp');
header('Content-Length: '.fstat($foto)['size']);
header('Cache-Control: public, max-age=86400');
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') fpassthru($foto);
fclose($foto);
