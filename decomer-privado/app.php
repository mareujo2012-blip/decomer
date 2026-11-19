<?php
declare(strict_types=1);

defined('DECOMER_PRIVADO') || define('DECOMER_PRIVADO', __DIR__);
require_once __DIR__.'/pix.php';
const MAX_QUANTIDADE = 99;
const MAX_PRODUTOS = 100;
const MAX_PRECO = 1000000;
const PRAZO_PEDIDO = 86400;

final class Falha extends RuntimeException
{
    public function __construct(public readonly int $http, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}

function h(mixed $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function brl(int $centavos): string
{
    return 'R$ '.number_format($centavos / 100, 2, ',', '.');
}

function jsonSeguro(mixed $valor): string
{
    return json_encode($valor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function pastaDados(): string
{
    return DECOMER_PRIVADO.'/dados';
}

function arquivoAdmin(): string
{
    return pastaDados().'/administrador.json';
}

function adminConfigurado(): bool
{
    $arquivo = arquivoAdmin();
    return is_file($arquivo) && !is_link($arquivo) && is_readable($arquivo)
        && (fileperms($arquivo) & 0077) === 0;
}

function emDesenvolvimento(): bool
{
    return PHP_SAPI === 'cli' || (PHP_SAPI === 'cli-server' && getenv('DECOMER_DEV') === '1'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));
}

function https(): bool
{
    return in_array(strtolower((string)($_SERVER['HTTPS'] ?? '')), ['on', '1'], true);
}

function segurancaHttp(): void
{
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store');
    if (https()) header('Strict-Transport-Security: max-age=31536000');
    if (!https() && !emDesenvolvimento()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Abra o endereço da loja com HTTPS.');
    }
}

function responderJson(mixed $valor, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo jsonSeguro($valor);
    exit;
}

function falhaPublica(Throwable $erro): array
{
    if ($erro instanceof Falha) return [$erro->http, $erro->getMessage()];
    error_log('De Comer: falha interna em '.$erro->getFile().':'.$erro->getLine().' ('.get_class($erro).')');
    return [503, 'Serviço indisponível. Tente novamente mais tarde.'];
}

function instalarSchema(PDO $pdo): void
{
    $sql = file_get_contents(DECOMER_PRIVADO.'/estrutura.sql');
    foreach (explode(";\n",$sql) as $comando) {
        if (trim($comando) !== '') $pdo->exec($comando);
    }
}

function conectarBanco(): PDO
{
    umask(0077);
    $dir = pastaDados();
    if (!is_dir($dir) || is_link($dir) || (fileperms($dir) & 0077) !== 0) {
        throw new Falha(503, 'Use permissão 700 na pasta de dados da loja.');
    }
    $arquivo = $dir.'/banco.json';
    $opcoes = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
    if (!is_file($arquivo) || is_link($arquivo)) {
        throw new Falha(503,'Configure o banco e inicialize a loja pelo guia de instalação.');
    }
    if ((fileperms($arquivo) & 0077) !== 0) throw new Falha(503,'Use permissão 600 no arquivo de conexão.');
    $c = json_decode(file_get_contents($arquivo),true,8,JSON_THROW_ON_ERROR);
    if (!is_array($c) || !in_array($c['host']??null,['localhost','127.0.0.1'],true)
        || !is_string($c['banco']??null) || preg_match('/\A[a-zA-Z0-9_]{1,64}\z/D',$c['banco']) !== 1
        || !is_string($c['usuario']??null) || !is_string($c['senha']??null) || $c['senha'] === '') {
        throw new Falha(503,'Confira os dados do banco na pasta privada.');
    }
    $dsn = 'mysql:host='.$c['host'].';dbname='.$c['banco'].';charset=utf8mb4';
    if (emDesenvolvimento() && getenv('DECOMER_DB_SOCKET')) {
        $dsn = 'mysql:unix_socket='.getenv('DECOMER_DB_SOCKET').';dbname='.$c['banco'].';charset=utf8mb4';
    }
    $db = new PDO($dsn,$c['usuario'],$c['senha'],$opcoes + [PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec('SET SESSION innodb_lock_wait_timeout=5');
    return $db;
}

function inicializarBanco(PDO $pdo): void
{
    instalarSchema($pdo);
    transacao($pdo, function () use ($pdo): void {
        if ($pdo->query('SELECT id FROM loja WHERE id=1')->fetchColumn()) return;
        $base = json_decode(file_get_contents(DECOMER_PRIVADO.'/catalogo-inicial.json'),true,32,JSON_THROW_ON_ERROR);
        consulta($pdo,'INSERT INTO loja (id,configuracao,categorias) VALUES (1,?,?)',[jsonSeguro($base['loja']),jsonSeguro($base['categorias'])]);
        $salvar = $pdo->prepare('INSERT INTO produtos (id,nome,modelo,centavos,categoria,posicao,publicado,foto,descricao) VALUES (?,?,?,?,?,?,1,?,?)');
        foreach ($base['catalogo'] as $i => $p) {
            $salvar->execute([$p['id'],$p['nome'],$p['modelo'],$p['centavos'],$p['categoria'],($i+1)*10,jsonSeguro([
                'arquivo'=>$p['arquivo'],'pequena'=>$p['pequena'],'grande'=>$p['grande'],
            ]),'']);
        }
    },false);
}

function banco(): PDO
{
    static $pdo;
    return $pdo ??= conectarBanco();
}

function transacao(PDO $pdo, callable $acao, bool $travarLoja = true): mixed
{
    $pdo->beginTransaction();
    try {
        if ($travarLoja) {
            if (!$pdo->query('SELECT id FROM loja WHERE id=1 FOR UPDATE')->fetchColumn()) throw new Falha(503,'Inicialize a loja pelo guia de instalação.');
        }
        $resultado = $acao();
        $pdo->commit();
        return $resultado;
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $erro;
    }
}

function consulta(PDO $pdo, string $sql, array $dados = []): PDOStatement
{
    $query = $pdo->prepare($sql);
    $query->execute($dados);
    return $query;
}

function estadoLoja(PDO $pdo): array
{
    $linha = $pdo->query('SELECT configuracao, categorias, revisao FROM loja WHERE id=1')->fetch();
    return [
        'loja'=>json_decode($linha['configuracao'], true, 16, JSON_THROW_ON_ERROR),
        'categorias'=>json_decode($linha['categorias'], true, 16, JSON_THROW_ON_ERROR),
        'revisao'=>(int)$linha['revisao'],
    ];
}

function formatarProduto(array $p): array
{
    return [
        'id'=>$p['id'], 'nome'=>$p['nome'], 'modelo'=>$p['modelo'], 'centavos'=>(int)$p['centavos'],
        'categoria'=>$p['categoria'], 'descricao'=>$p['descricao'], 'posicao'=>(int)$p['posicao'],
        'publicado'=>(bool)$p['publicado'], 'foto'=>json_decode($p['foto'], true, 8, JSON_THROW_ON_ERROR),
    ];
}

function produtos(PDO $pdo, bool $publicos = true): array
{
    $sql = 'SELECT * FROM produtos'.($publicos ? ' WHERE publicado=1' : '').' ORDER BY posicao, id';
    $mapa = [];
    foreach ($pdo->query($sql) as $p) $mapa[$p['id']] = formatarProduto($p);
    return $mapa;
}

function fotoUrl(array $foto, string $tamanho = '940'): string
{
    if (isset($foto['arquivo'])) return 'assets/img/produtos/'.$foto['arquivo'].'-'.$tamanho.'.webp';
    return 'imagem.php?id='.$foto['id'].'&t='.$tamanho;
}

function logoUrl(array $loja): string
{
    return $loja['logo'] ? fotoUrl($loja['logo'], '400') : 'assets/img/de-comer.webp';
}

function textoCampo(mixed $valor, int $maximo, bool $obrigatorio = true): string
{
    if (!is_string($valor) || preg_match('//u', $valor) !== 1 || strlen($valor) > $maximo * 4 + 16
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\p{Cf}]/u', $valor)) {
        throw new Falha(422, 'Revise os campos de texto.');
    }
    $valor = trim(str_replace(["\r\n", "\r"], "\n", $valor));
    if (mb_strlen($valor, 'UTF-8') > $maximo || ($obrigatorio && $valor === '')) {
        throw new Falha(422, 'Preencha os campos obrigatórios dentro do limite indicado.');
    }
    return $valor;
}

function nomeCliente(mixed $valor): string
{
    if (!is_string($valor) || strlen($valor) > 320
        || preg_match("/^[\p{L}\p{M} .’'-]*$/uD", $valor) !== 1) {
        throw new Falha(422, 'Informe um nome válido ou deixe o campo em branco.');
    }
    return textoCampo(preg_replace('/ +/', ' ', $valor), 80, false);
}

function calcularPedido(mixed $itens, array $catalogo): array
{
    if (!$itens instanceof stdClass || count((array)$itens) < 1 || count((array)$itens) > MAX_PRODUTOS) {
        throw new Falha(422, 'Escolha pelo menos um produto.');
    }
    $lista = [];
    $total = 0;
    foreach ((array)$itens as $id => $quantidade) {
        $p = $catalogo[$id] ?? null;
        if (!$p || !$p['publicado'] || !is_int($quantidade) || $quantidade < 1 || $quantidade > MAX_QUANTIDADE) {
            throw new Falha(422, 'Há um produto indisponível ou uma quantidade inválida na sacola.');
        }
        $subtotal = $p['centavos'] * $quantidade;
        $total += $subtotal;
        $lista[] = ['id'=>$id, 'nome'=>$p['nome'], 'modelo'=>$p['modelo'], 'quantidade'=>$quantidade,
            'centavos'=>$p['centavos'], 'subtotal'=>$subtotal];
    }
    $sinal = intdiv($total + 1, 2);
    return ['itens'=>$lista, 'total'=>$total, 'sinal'=>$sinal, 'restante'=>$total-$sinal];
}

function limites(PDO $pdo, array $regras): void
{
    $agora = time();
    consulta($pdo,'DELETE FROM limites WHERE ate<=?',[$agora]);
    $selecionar = $pdo->prepare('SELECT usos,ate FROM limites WHERE chave=?');
    $inserir = $pdo->prepare('INSERT INTO limites (chave,ate,usos) VALUES (?,?,?)');
    $alterar = $pdo->prepare('UPDATE limites SET ate=?, usos=? WHERE chave=?');
    $linhas = [];
    foreach ($regras as [$chave,$maximo,$janela]) {
        $selecionar->execute([$chave]);
        $antigo = $selecionar->fetch();
        $ativo = $antigo && $antigo['ate'] > $agora;
        $usos = $ativo ? (int)$antigo['usos'] : 0;
        if ($usos >= $maximo) {
            header('Retry-After: '.max(1, $antigo['ate'] - $agora));
            throw new Falha(429, 'Muitas tentativas. Aguarde antes de tentar novamente.');
        }
        $linhas[] = [$chave, $ativo ? $antigo['ate'] : $agora + $janela, $usos+1, $ativo];
    }
    foreach ($linhas as [$chave,$ate,$usos,$ativo]) {
        if ($ativo) $alterar->execute([$ate,$usos,$chave]);
        else $inserir->execute([$chave,$ate,$usos]);
    }
}

function clienteIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $bin = @inet_pton($ip);
    if ($bin !== false && strlen($bin) === 16) {
        $bin = str_starts_with($bin,str_repeat("\0",10)."\xff\xff") ? substr($bin,12) : substr($bin,0,8);
    }
    return hash('sha256', $bin === false ? 'desconhecido' : $bin);
}

function evento(PDO $pdo, string $acao, string $alvo): void
{
    consulta($pdo,'INSERT INTO eventos (instante,acao,alvo) VALUES (?,?,?)',[time(),$acao,$alvo]);
    $max = (int)$pdo->lastInsertId();
    if ($max>1000) consulta($pdo,'DELETE FROM eventos WHERE id<=?',[$max-1000]);
}

function estadosPedido(): array
{
    return ['solicitado'=>'Aguardando confirmação','confirmado'=>'Aguardando entrada',
        'sinal_recebido'=>'Aguardando saldo','quitado'=>'Pagamento completo','cancelado'=>'Cancelado'];
}

function pedidoPublico(array $pedido): array
{
    $data = ['codigo'=>$pedido['codigo'], 'estado'=>$pedido['estado'], 'total'=>(int)$pedido['total'],
        'sinal'=>(int)$pedido['sinal'], 'restante'=>(int)$pedido['restante'], 'whatsapp'=>$pedido['whatsapp']];
    if ($pedido['estado'] === 'confirmado' && $pedido['pix']) {
        if ($pedido['confirmado'] === null && (int)$pedido['criado'] < time() - PRAZO_PEDIDO) {
            $data['entrada_expirada'] = true;
            return $data;
        }
        $data['pix_entrada'] = cobrancaPix($pedido,'entrada');
    }
    return $data;
}

function limparPedidosAntigos(PDO $pdo, int $agora): void
{
    // Pendente = sem entrada registrada: o legado 'solicitado' e o 'confirmado' aberto pela sacola sem confirmação manual.
    consulta($pdo, "DELETE FROM pedidos WHERE criado < ? AND (estado='solicitado' OR (estado='confirmado' AND confirmado IS NULL AND pago IS NULL))", [$agora - 7*86400]);
    consulta($pdo, 'DELETE c FROM contatos_pedidos c JOIN pedidos p ON p.codigo=c.codigo WHERE p.criado < ?', [$agora - 90*86400]);
    consulta($pdo, "UPDATE pedidos SET nome='', assinatura='' WHERE (nome<>'' OR assinatura<>'') AND criado < ?", [$agora - 90*86400]);
}

function telefoneComprador(mixed $valor): string
{
    if (!is_string($valor) || strlen($valor) > 24 || preg_match('/\A[0-9+(). -]+\z/D',$valor) !== 1) {
        throw new Falha(422,'Informe um WhatsApp válido com DDD.');
    }
    $numero = preg_replace('/[^0-9]/','',$valor);
    if (in_array(strlen($numero),[10,11],true)) $numero = '55'.$numero;
    if (preg_match('/\A55[1-9][0-9](?:9[0-9]{8}|[2-5][0-9]{7})\z/D',$numero) !== 1) {
        throw new Falha(422,'Informe um WhatsApp válido com DDD.');
    }
    return $numero;
}

function novoPedido(PDO $pdo, stdClass $dados): array
{
    if (array_diff(array_keys((array)$dados), ['itens','nome','telefone','revisao','requisicao'])
        || !is_string($dados->requisicao ?? null) || !tokenPedidoValido($dados->requisicao)
        || !is_int($dados->revisao ?? null)) throw new Falha(422, 'Pedido inválido. Reabra a sacola e tente novamente.');
    $nome = nomeCliente($dados->nome ?? '');
    $legado = strlen($dados->requisicao) === 48 && !property_exists($dados,'telefone');
    if (!$legado && $nome === '') throw new Falha(422,'Informe seu nome para a encomenda.');
    $telefone = $legado ? '' : telefoneComprador($dados->telefone ?? null);
    return transacao($pdo, function () use ($pdo, $dados, $nome, $telefone): array {
        $agora = time();
        $itens = $dados->itens ?? null;
        if (!$itens instanceof stdClass || count((array)$itens) > MAX_PRODUTOS) throw new Falha(422, 'Sacola inválida.');
        $ordenados = (array)$itens;
        ksort($ordenados, SORT_STRING);
        $camposAssinatura = [$nome, $dados->revisao, $ordenados];
        if ($telefone !== '') $camposAssinatura[] = $telefone;
        $assinatura = hash_hmac('sha256', jsonSeguro($camposAssinatura), $dados->requisicao);
        $assinaturaAnterior = $telefone === '' ? hash('sha256', jsonSeguro($camposAssinatura)) : '';
        $chave = hash('sha256', $dados->requisicao);
        $existente = consulta($pdo,'SELECT * FROM pedidos WHERE consulta=?',[$chave])->fetch();
        if ($existente) {
            $mesmaSacola = hash_equals($existente['assinatura'], $assinatura)
                || ($assinaturaAnterior !== '' && hash_equals($existente['assinatura'],$assinaturaAnterior));
            if ($existente['criado'] < $agora - PRAZO_PEDIDO || !$mesmaSacola) {
                throw new Falha(409, 'Essa solicitação já foi usada. Reabra a sacola e gere um novo pedido.');
            }
            return respostaPedido($existente);
        }
        if ($telefone === '') throw new Falha(409, 'Atualize a página para informar seu nome e WhatsApp.');
        $config = estadoLoja($pdo);
        if (!encomendasAbertas($config['loja']) || !adminConfigurado()) {
            throw new Falha(409, 'A loja está com encomendas pausadas. Consulte o atendimento.');
        }
        if ($dados->revisao !== $config['revisao']) throw new Falha(409, 'O catálogo foi atualizado. Recarregue a página e revise os valores.');
        $pix = trim($config['loja']['chavePix'] ?? '');
        $recebedor = trim($config['loja']['recebedorPix'] ?? '');
        if (preg_match('/\A\+55[1-9][0-9]9[0-9]{8}\z/D',$pix) !== 1 || $recebedor === '') {
            throw new Falha(503, 'O Pix está temporariamente indisponível. Fale com a loja pelo WhatsApp.');
        }
        $p = calcularPedido($itens, produtos($pdo));
        limites($pdo,[['pedidos:global',350,600]]);
        $codigo = 'DC-'.strtoupper(bin2hex(random_bytes(12)));
        $whatsapp = $config['loja']['whatsapp'];
        consulta($pdo,"INSERT INTO pedidos (codigo,criado,revisao,nome,itens,total,sinal,restante,whatsapp,consulta,assinatura,estado,pix,recebedor) VALUES (?,?,?,?,?,?,?,?,?,?,?,'confirmado',?,?)",[
            $codigo, $agora, $config['revisao'], $nome, jsonSeguro($p['itens']), $p['total'], $p['sinal'], $p['restante'], $whatsapp, $chave, $assinatura, $pix, $recebedor,
        ]);
        consulta($pdo,'INSERT INTO contatos_pedidos (codigo,telefone) VALUES (?,?)',[$codigo,$telefone]);
        return $p + ['codigo'=>$codigo, 'whatsapp'=>$whatsapp];
    });
}

function respostaPedido(array $pedido): array
{
    return [
        'codigo'=>$pedido['codigo'], 'itens'=>json_decode($pedido['itens'],true,16,JSON_THROW_ON_ERROR),
        'total'=>(int)$pedido['total'], 'sinal'=>(int)$pedido['sinal'], 'restante'=>(int)$pedido['restante'],
        'whatsapp'=>$pedido['whatsapp'],
    ];
}

function encomendasAbertas(array $loja): bool
{
    $hoje = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    return $loja['recebendo'] && (empty($loja['prazo']) || $hoje <= $loja['prazo']);
}

function tokenPedidoValido(string $token): bool
{
    return preg_match('/\A[0-9a-f]{32}(?:[0-9a-f]{16})?\z/D',$token) === 1;
}

function consultarPedido(PDO $pdo, stdClass $dados): array
{
    $codigo = $dados->codigo ?? null;
    $token = $dados->token ?? null;
    $mensagem = 'Pedido não encontrado. Fale com a loja se precisar de ajuda.';
    if (!is_string($token) || !tokenPedidoValido($token)
        || ($codigo !== null && (!is_string($codigo) || preg_match('/\ADC-[A-F0-9]{24}\z/D',$codigo) !== 1))) {
        throw new Falha(404,$mensagem);
    }
    $linha = consulta($pdo,'SELECT * FROM pedidos WHERE consulta=?',[hash('sha256',$token)])->fetch();
    if (!$linha || ($codigo !== null && !hash_equals($linha['codigo'],$codigo))
        || ($linha['estado'] === 'solicitado' && $linha['criado'] < time() - PRAZO_PEDIDO)
        || $linha['criado'] < time() - 90*86400) {
        throw new Falha(404,$mensagem);
    }
    if (in_array($linha['estado'],['quitado','cancelado'],true)) {
        $linha['whatsapp'] = estadoLoja($pdo)['loja']['whatsapp'];
    }
    return pedidoPublico($linha) + ['itens'=>json_decode($linha['itens'],true,16,JSON_THROW_ON_ERROR)];
}
