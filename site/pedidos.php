<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        throw new Falha(405, 'Método indisponível.');
    }
    if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
        throw new Falha(403, 'Origem inválida.');
    }
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origem = (https() ? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? '');
        if ($_SERVER['HTTP_ORIGIN'] !== $origem) throw new Falha(403, 'Origem inválida.');
    }
    $tipo = strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE'] ?? '',2)[0]));
    if ($tipo !== 'application/json' || ($_SERVER['HTTP_X_DECOMER'] ?? '') !== 'pedido') {
        throw new Falha(415, 'Envie o pedido pela sacola da loja.');
    }
    $corpo = file_get_contents('php://input',false,null,0,8193);
    if (strlen($corpo) > 8192) throw new Falha(413, 'Pedido acima do limite.');
    $pdo = banco();
    $acao = $_GET['acao'] ?? '';
    if (!in_array($acao,['criar','consultar'],true)) throw new Falha(404, 'Ação indisponível.');
    transacao($pdo,fn () => $acao === 'criar'
        ? limites($pdo,[['pedido:'.clienteIp(),18,600]])
        : limites($pdo,[['consulta:'.clienteIp(),90,600]]));
    try { $dados = json_decode($corpo,false,12,JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new Falha(400, 'Pedido inválido.'); }
    if (!$dados instanceof stdClass) throw new Falha(400, 'Pedido inválido.');
    if ($acao === 'criar') {
        if (random_int(1,10) === 1) transacao($pdo,fn () => limparPedidosAntigos($pdo,time()));
        responderJson(novoPedido($pdo,$dados),201);
    }
    responderJson(consultarPedido($pdo,$dados));
} catch (Throwable $erro) {
    [$status,$mensagem] = falhaPublica($erro);
    responderJson(['erro'=>$mensagem],$status);
}
