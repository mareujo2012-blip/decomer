<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require DECOMER_PRIVADO.'/admin.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Content-Type: text/html; charset=utf-8');
$erro = '';
$aviso = '';
$admin = null;
$autenticado = false;
$abas = ['inicio'=>'Visão geral','pedidos'=>'Pedidos','produtos'=>'Produtos','categorias'=>'Categorias',
    'pagina'=>'Página e marca','pix'=>'Pix','conta'=>'Minha conta'];
$aba = $_GET['aba'] ?? 'inicio';
if (!is_string($aba) || !isset($abas[$aba])) $aba = 'inicio';
$secoes = secoesEdicaoLoja();
$secao = $_GET['secao'] ?? 'marca';
if (!is_string($secao) || !isset($secoes[$secao])) $secao = 'marca';
$reexibirEdicao = false;

function voltar(string $aba, string $mensagem = '', string $secao = ''): never
{
    if ($mensagem !== '') $_SESSION['aviso'] = $mensagem;
    $destino = './?aba='.rawurlencode($aba);
    if ($secao !== '') $destino .= '&secao='.rawurlencode($secao);
    header('Location: '.$destino,true,303);
    exit;
}

try {
    $pdo = banco();
    if (isset($_COOKIE[cookieAdmin()]) && adminConfigurado()) iniciarSessao();
    $admin = lerAdmin();
    if (session_status() === PHP_SESSION_ACTIVE) {
        $autenticado = adminAutenticado($admin);
        if (!$autenticado) {
            $aviso = $_SESSION['aviso'] ?? '';
            encerrarSessao();
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!empty($_SERVER['CONTENT_LENGTH']) && empty($_POST)) throw new Falha(413, 'O formulário excedeu o limite de envio.');
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'entrar' && $admin && !$autenticado) {
            verificarLogin($admin);
            if (!autenticar($pdo,$admin,$_POST['email']??null,$_POST['senha']??null)) {
                throw new Falha(401,'E-mail ou senha incorretos.');
            }
            voltar('inicio');
        }
        if (!$autenticado) throw new Falha(403,'Entre para acessar o painel.');
        verificarCsrf();
        if ($acao === 'sair') {
            encerrarSessao();
            voltar('inicio');
        }
        if ($acao === 'trocar-senha') {
            trocarSenha($pdo,$admin);
            $_SESSION = [];
            voltar('inicio','Senha alterada. Entre com a nova senha.');
        }
        if (in_array($acao,['salvar-loja','salvar-whatsapp'],true)) {
            throw new Falha(409,'Este formulário pertence à versão anterior do painel. Reabra a seção para editar.');
        }
        if ($acao === 'salvar-secao') {
            if ($aba !== 'pagina') throw new Falha(422,'Abra a seção correta antes de salvar.');
            $reexibirEdicao = true;
            if ($secao === 'whatsapp') {
                salvarWhatsApp($pdo,$admin);
            } else {
                comAcessoAtual($admin,fn () => salvarLoja($pdo,$secao));
            }
            voltar('pagina',$secoes[$secao][3],$secao);
        }
        match ($acao) {
            'salvar-pix' => salvarPix($pdo,$admin),
            default => comAcessoAtual($admin,fn () => match ($acao) {
                'salvar-produto' => salvarProduto($pdo),
                'excluir-produto' => excluirProduto($pdo),
                'salvar-categoria' => salvarCategoria($pdo),
                'alterar-pedido' => alterarPedido($pdo),
                default => throw new Falha(422,'Ação inválida.'),
            }),
        };
        if ($acao === 'alterar-pedido') {
            $_SESSION['pedido_aberto'] = $_POST['id'];
        }
        voltar($aba,'Alteração salva.');
    }
    if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','HEAD'],true)) {
        header('Allow: GET, HEAD, POST');
        throw new Falha(405,'Método indisponível.');
    }
    $aviso = $_SESSION['aviso'] ?? $aviso;
    unset($_SESSION['aviso']);
} catch (Throwable $e) {
    [$codigo,$erro] = falhaPublica($e);
    http_response_code($codigo);
}

function formAcao(string $acao, ?string $token = null): void
{
    echo '<input type="hidden" name="csrf" value="'.h($token ?? $_SESSION['csrf'] ?? '').'"><input type="hidden" name="acao" value="'.h($acao).'">';
}

function listaPedidos(array $pedidos, array $rotulos): void
{
    echo '<ul class="eventos">';
    foreach ($pedidos as $p) {
        echo '<li class="pedido-recente"><div><a href="?aba=pedidos&amp;codigo='.h($p['codigo']).'#pedido-detalhe">'.h($p['nome'] !== '' ? $p['nome'] : 'Cliente sem nome').'</a>'
            .'<span>'.h(dataLocal((int)$p['criado'])).' · '.h($p['codigo']).'</span></div>'
            .'<span class="estado-pedido" data-estado="'.h($p['estado']).'">'.h($rotulos[$p['estado']]).'</span>'
            .'<strong>'.h(brl((int)$p['total'])).'</strong></li>';
    }
    echo '</ul>';
}

function dataLocal(int $data): string
{
    return (new DateTimeImmutable('@'.$data))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i');
}

function navegacaoPainel(array $abas, string $aba): void
{
    foreach ($abas as $slug=>$nome) {
        echo '<a href="?aba='.h($slug).'"'.($slug===$aba?' aria-current="page"':'').'>'.h($nome).'</a>';
    }
}

if ($autenticado) {
    $estado = estadoLoja($pdo);
    $loja = $estado['loja'];
    $categorias = $estado['categorias'];
    $lista = produtos($pdo,false);
    $rotulos = estadosPedido();
    if ($aba === 'produtos') {
        $idEditar = $_GET['editar'] ?? '';
        $produto = is_string($idEditar) ? ($lista[$idEditar] ?? null) : null;
    }
    if ($aba === 'pedidos') {
        $pedidoBusca = $_GET['codigo'] ?? $_SESSION['pedido_aberto']
            ?? (($_POST['acao']??'') === 'alterar-pedido' ? ($_POST['id']??'') : '');
        unset($_SESSION['pedido_aberto']);
        $pedidoBusca = is_string($pedidoBusca) ? strtoupper(trim(substr($pedidoBusca,0,27))) : '';
        $pedido = preg_match('/\ADC-[A-F0-9]{24}\z/D',$pedidoBusca) === 1
            ? consulta($pdo,'SELECT p.*, c.telefone FROM pedidos p LEFT JOIN contatos_pedidos c ON c.codigo=p.codigo WHERE p.codigo=?',[$pedidoBusca])->fetch()
            : false;
        $saldoRecebido = $pedido && $pedido['estado'] === 'quitado'
            ? consulta($pdo,'SELECT * FROM saldos_pedidos WHERE codigo=?',[$pedido['codigo']])->fetch()
            : false;
        // A mensagem do WhatsApp não traz código: a busca aceita nome, WhatsApp ou código.
        $termo = $_GET['busca'] ?? '';
        $termo = is_string($termo) && mb_check_encoding($termo,'UTF-8') ? trim(mb_substr($termo,0,80,'UTF-8')) : '';
        if ($termo !== '' && preg_match('/\ADC-[A-F0-9]{24}\z/Di',$termo) === 1) {
            $pedidoBusca = strtoupper($termo);
            $pedido = consulta($pdo,'SELECT p.*, c.telefone FROM pedidos p LEFT JOIN contatos_pedidos c ON c.codigo=p.codigo WHERE p.codigo=?',[$pedidoBusca])->fetch();
            $saldoRecebido = $pedido && $pedido['estado'] === 'quitado'
                ? consulta($pdo,'SELECT * FROM saldos_pedidos WHERE codigo=?',[$pedido['codigo']])->fetch()
                : false;
            $termo = '';
        }
        $encontrados = [];
        if ($termo !== '') {
            $digitos = preg_replace('/\D/','',$termo);
            $encontrados = strlen($digitos) >= 4 && preg_match('/\A[0-9\s()+.-]+\z/',$termo) === 1
                ? consulta($pdo,'SELECT p.codigo, p.criado, p.total, p.estado, p.nome FROM pedidos p JOIN contatos_pedidos c ON c.codigo=p.codigo WHERE c.telefone LIKE ? ORDER BY p.criado DESC LIMIT 30',['%'.$digitos.'%'])->fetchAll()
                : consulta($pdo,'SELECT codigo, criado, total, estado, nome FROM pedidos WHERE nome COLLATE utf8mb4_unicode_ci LIKE ? ORDER BY criado DESC LIMIT 30',['%'.addcslashes($termo,'%_\\').'%'])->fetchAll();
        }
        $recentes = $pdo->query("SELECT codigo, criado, total, estado, nome FROM pedidos ORDER BY criado DESC LIMIT 60")->fetchAll();
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title><?= h($autenticado ? $abas[$aba].' | '.$loja['marca'] : 'Administração | De Comer') ?></title>
  <link rel="icon" href="../assets/img/icone.png">
  <link rel="stylesheet" href="../assets/painel.css?v=5">
</head>
<body class="<?= $autenticado?'painel-autenticado':'painel-login' ?>">
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<header class="cabecalho">
  <div class="largura topo">
    <a class="marca" href="./"><img src="<?= h(isset($loja)?'../'.logoUrl($loja):'../assets/img/de-comer.webp') ?>" alt="" width="48" height="48"><strong><?= h($loja['marca']??'De Comer') ?></strong></a>
    <div class="acoes">
      <a href="../" class="botao contorno">Ver loja <span aria-hidden="true">↗</span></a>
      <?php if ($autenticado): ?><form method="post"><?php formAcao('sair'); ?><button class="botao contorno">Sair</button></form><?php endif; ?>
    </div>
  </div>
</header>
<?php if ($autenticado): ?>
<div class="area-admin largura">
  <nav class="abas" aria-label="Painel">
    <?php navegacaoPainel($abas,$aba); ?>
  </nav>
  <details class="menu-admin">
    <summary><span>Menu do painel</span><strong><?= h($abas[$aba]) ?></strong></summary>
    <nav aria-label="Painel"><?php navegacaoPainel($abas,$aba); ?></nav>
  </details>
<?php endif; ?>
<main id="conteudo" class="<?= $autenticado?'':'largura' ?>">
<?php if ($aba!=='pagina' || !$autenticado): ?>
  <?php if ($erro !== ''): ?><p class="alerta erro" role="alert"><?= h($erro) ?></p><?php endif; ?>
  <?php if ($aviso !== ''): ?><p class="alerta ok" role="status"><?= h($aviso) ?></p><?php endif; ?>
<?php endif; ?>
<?php if (!$autenticado): ?>
  <section class="entrada painel">
    <h1>Administração</h1>
    <?php if ($admin): ?>
      <form method="post" class="formulario">
        <?php formAcao('entrar',csrfLogin($admin)); ?>
        <label>E-mail <input type="email" name="email" required maxlength="254" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= h(emailAdmin($_POST['email']??null)??'') ?>"></label>
        <label>Senha <input type="password" name="senha" required maxlength="256" autocomplete="current-password"></label>
        <button class="botao principal">Entrar</button>
      </form>
    <?php else: ?>
      <p>O acesso é ativado no terminal da hospedagem. Siga o guia de instalação da loja.</p>
    <?php endif; ?>
  </section>
<?php else: ?>
  <?php if ($aba==='inicio'): ?>
    <h1>Visão geral</h1>
    <div class="metricas">
      <a href="?aba=produtos"><strong><?= count(array_filter($lista,static fn($p)=>$p['publicado'])) ?></strong><span>Produtos publicados</span></a>
      <a href="?aba=pedidos"><strong><?= h($pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('solicitado','confirmado')")->fetchColumn()) ?></strong><span>Pedidos em aberto</span></a>
      <a href="?aba=pagina&amp;secao=encomendas"><strong><?= encomendasAbertas($loja)?'Abertas':'Pausadas' ?></strong><span>Encomendas</span></a>
    </div>
    <?php if (empty($loja['chavePix']) || empty($loja['recebedorPix'])): ?>
      <p class="alerta">Configure a chave e o titular bancário na <a href="?aba=pix">seção Pix</a> para liberar a entrada no site.</p>
    <?php endif; ?>
    <section class="painel">
      <h2>Atividade do painel</h2>
      <ul class="eventos">
        <?php foreach ($pdo->query('SELECT instante,acao,alvo FROM eventos ORDER BY id DESC LIMIT 15') as $evento): ?>
          <li><strong><?= h($evento['acao']) ?></strong><span><?= h($evento['alvo']) ?> · <?= h(dataLocal((int)$evento['instante'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php elseif ($aba==='produtos'): ?>
    <div class="titulo-painel">
      <h1>Produtos</h1>
      <a class="botao contorno" href="#lista-produtos">Ver catálogo</a>
    </div>
    <div class="divisao">
      <section class="painel" id="editor">
        <h2><?= $produto?'Editar produto':'Novo produto' ?></h2>
        <form method="post" enctype="multipart/form-data" class="formulario">
          <?php formAcao('salvar-produto'); ?>
          <input type="hidden" name="id" value="<?= h($produto['id']??'') ?>">
          <label>Nome <input name="nome" required maxlength="90" value="<?= h($produto['nome']??'') ?>"></label>
          <label>Modelo <input name="modelo" maxlength="85" value="<?= h($produto['modelo']??'') ?>"></label>
          <label>Descrição <textarea name="descricao" maxlength="650" rows="3"><?= h($produto['descricao']??'') ?></textarea></label>
          <div class="campos">
            <label>Preço em reais <input name="preco" inputmode="decimal" required placeholder="38,00" maxlength="8" value="<?= $produto?h(number_format($produto['centavos']/100,2,',','')):'' ?>"></label>
            <label>Ordem <input name="posicao" type="number" min="0" max="9999" required value="<?= h($produto['posicao']??((count($lista)+1)*10)) ?>"></label>
          </div>
          <label>Categoria <select name="categoria" required>
            <?php foreach ($categorias as $id=>$categoria): ?><option value="<?= h($id) ?>" <?= ($produto['categoria']??'')===$id?'selected':'' ?>><?= h($categoria) ?></option><?php endforeach; ?>
          </select></label>
          <label>Foto <?= $produto?'nova (opcional)':'' ?><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" <?= $produto?'':'required' ?>></label>
          <p class="muted">JPEG, PNG ou WebP de até 8 MB.</p>
          <?php if ($produto): ?><img class="foto-edicao" src="<?= h('../'.fotoUrl($produto['foto'],'400')) ?>" alt="<?= h($produto['nome']) ?>"><?php endif; ?>
          <label class="check"><input type="checkbox" name="publicado" <?= ($produto['publicado']??true)?'checked':'' ?>> Publicado no catálogo</label>
          <div class="acoes-form">
            <button class="botao principal">Salvar produto</button>
            <?php if ($produto): ?><a href="?aba=produtos" class="botao contorno">Novo produto</a><?php endif; ?>
          </div>
        </form>
        <?php if ($produto): ?>
          <details class="risco"><summary>Excluir produto</summary><p>Pedidos já registrados conservam a descrição e o preço originais.</p>
            <form method="post" class="formulario"><?php formAcao('excluir-produto'); ?><input type="hidden" name="id" value="<?= h($produto['id']) ?>"><label class="check"><input type="checkbox" name="exclusao" required> Confirmo a exclusão</label><button class="botao excluir">Excluir definitivamente</button></form>
          </details>
        <?php endif; ?>
      </section>
      <section class="painel" id="lista-produtos" aria-labelledby="lista-produtos-titulo">
        <div class="titulo-lista">
          <h2 id="lista-produtos-titulo">Catálogo <small><?= count($lista) ?></small></h2>
          <a class="botao contorno" href="?aba=produtos#editor">Novo produto</a>
        </div>
        <ul class="listagem">
          <?php foreach ($lista as $p): ?>
            <li><img src="<?= h('../'.fotoUrl($p['foto'],'400')) ?>" alt="" width="55" height="60" loading="lazy"><div><strong><?= h($p['nome']) ?></strong><span><?= h($p['modelo'] ?: ($categorias[$p['categoria']]??'')) ?></span><span><?= h(brl($p['centavos'])) ?> · <?= $p['publicado']?'Publicado':'Oculto' ?></span></div><a href="?aba=produtos&amp;editar=<?= h($p['id']) ?>#editor" class="botao contorno">Editar<span class="sr"> <?= h($p['nome'].' '.$p['modelo']) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
  <?php elseif ($aba==='categorias'): ?>
    <h1>Categorias</h1>
    <section class="painel estreito">
      <form method="post" class="linha-form"><?php formAcao('salvar-categoria'); ?><label>Nova categoria <input name="nome" required maxlength="46"></label><button class="botao principal">Adicionar</button></form>
      <?php foreach ($categorias as $id=>$categoria): ?>
        <form method="post" class="linha-form"><?php formAcao('salvar-categoria'); ?><input type="hidden" name="id" value="<?= h($id) ?>">
          <label>Nome <input name="nome" required maxlength="46" value="<?= h($categoria) ?>"></label>
          <button class="botao contorno">Salvar</button>
          <?php if (!in_array($id,array_column($lista,'categoria'),true)): ?><button class="botao excluir" name="modo" value="excluir">Excluir</button><?php endif; ?>
        </form>
      <?php endforeach; ?>
    </section>
  <?php elseif ($aba==='pagina'): ?>
    <?php require DECOMER_PRIVADO.'/editor-loja.php'; ?>
  <?php elseif ($aba==='pix'): ?>
    <h1>Pix</h1>
    <section class="painel estreito">
      <p>O site não gera Pix no momento: o pagamento é combinado com o cliente pelo WhatsApp. Guarde aqui a chave e o titular para consulta. Ao receber, registre o pagamento em Pedidos.</p>
      <form method="post" class="formulario">
        <?php formAcao('salvar-pix'); ?>
        <label>Chave Pix por telefone <input type="tel" name="pix" maxlength="20" value="<?= h($loja['chavePix']) ?>"></label>
        <label>Titular conforme aparece no banco <input name="recebedor" maxlength="100" value="<?= h($loja['recebedorPix']??'') ?>"></label>
        <label>Senha atual do painel <input type="password" name="senha_atual" required maxlength="256" autocomplete="current-password"></label>
        <p class="muted">Deixe a chave em branco para desativar o Pix em novas confirmações.</p>
        <button class="botao principal">Salvar Pix</button>
      </form>
    </section>
  <?php elseif ($aba==='conta'): ?>
    <h1>Minha conta</h1>
    <section class="painel estreito">
      <h2>Trocar senha</h2>
      <p>Depois de salvar, entre novamente com a nova senha.</p>
      <form method="post" class="formulario">
        <?php formAcao('trocar-senha'); ?>
        <label>E-mail <input type="email" name="email" readonly autocomplete="username" value="<?= h($admin['email']) ?>"></label>
        <label>Senha atual <input type="password" name="senha_atual" required maxlength="256" autocomplete="current-password"></label>
        <label>Nova senha <input type="password" name="nova_senha" required minlength="15" maxlength="256" autocomplete="new-password" aria-describedby="dica-senha"></label>
        <p class="muted" id="dica-senha">Use pelo menos 15 caracteres. Você pode usar uma frase fácil de lembrar.</p>
        <label>Confirme a nova senha <input type="password" name="confirmar_senha" required minlength="15" maxlength="256" autocomplete="new-password"></label>
        <button class="botao principal">Trocar senha</button>
      </form>
    </section>
  <?php elseif ($aba==='pedidos'): ?>
    <h1>Pedidos</h1>
    <form method="get" class="painel linha-form">
      <input type="hidden" name="aba" value="pedidos"><label>Buscar pedido <input name="busca" value="<?= h($termo !== '' ? $termo : $pedidoBusca) ?>" maxlength="80" placeholder="Nome, WhatsApp ou código" required></label>
      <button class="botao principal">Conferir pedido</button>
    </form>
    <?php if ($pedidoBusca && !$pedido): ?><p class="alerta erro">Pedido não encontrado.</p><?php endif; ?>
    <?php if ($termo !== ''): ?>
      <section class="painel">
        <h2>Resultado da busca</h2>
        <?php if (!$encontrados): ?><p class="muted">Nenhum pedido encontrado para “<?= h($termo) ?>”.</p><?php endif; ?>
        <?php listaPedidos($encontrados,$rotulos); ?>
      </section>
    <?php endif; ?>
    <?php if ($pedido): ?>
      <section class="painel" id="pedido-detalhe">
        <h2><?= h($pedido['nome'] !== '' ? $pedido['nome'] : 'Cliente sem nome') ?></h2>
        <div class="metadados-pedido">
          <span class="estado-pedido" data-estado="<?= h($pedido['estado']) ?>"><?= h($rotulos[$pedido['estado']]) ?></span>
          <span><?= h(dataLocal((int)$pedido['criado'])) ?> · <small><?= h($pedido['codigo']) ?></small></span>
        </div>
        <?php if ($pedido['telefone']): ?>
          <?php $mensagemCliente = 'Olá! Aqui é da '.$loja['marca'].'. Estou falando sobre a encomenda '.$pedido['codigo'].'.'; ?>
          <p class="muted">WhatsApp informado pelo cliente na sacola.</p>
          <p><a class="botao contorno" href="<?= h('https://wa.me/'.$pedido['telefone'].'?text='.rawurlencode($mensagemCliente)) ?>" target="_blank" rel="noopener noreferrer">Falar com o cliente no WhatsApp</a></p>
        <?php endif; ?>
        <ul class="resumo-itens">
          <?php foreach (json_decode($pedido['itens'],true,16,JSON_THROW_ON_ERROR) as $i): ?>
            <li><span><?= h($i['quantidade'].'× '.$i['nome'].($i['modelo']?' · '.$i['modelo']:'')) ?> <small><?= h($i['id']) ?></small></span><strong><?= h(brl($i['subtotal'])) ?></strong></li>
          <?php endforeach; ?>
        </ul>
        <dl class="totais"><div><dt>Total dos produtos</dt><dd><?= h(brl($pedido['total'])) ?></dd></div><div><dt>Entrada</dt><dd><?= h(brl($pedido['sinal'])) ?></dd></div><div><dt>Na retirada</dt><dd><?= h(brl($pedido['restante'])) ?></dd></div></dl>
        <?php if ($pedido['estado']==='solicitado'): ?>
          <p class="muted">Pedido enviado pela sacola. Combine o pagamento e a retirada com o cliente pelo WhatsApp e confirme aqui quando estiver acertado.</p>
          <form method="post" class="linha-form"><?php formAcao('alterar-pedido'); ?><input type="hidden" name="id" value="<?= h($pedido['codigo']) ?>"><button class="botao principal" name="modo" value="confirmar">Confirmar pedido</button><button class="botao contorno" name="modo" value="cancelar">Cancelar pedido</button></form>
        <?php endif; ?>
        <?php if ($pedido['pago']): ?>
          <p class="alerta ok">Entrada registrada em <?= h(dataLocal((int)$pedido['pago'])) ?> por <?= h($pedido['metodo']) ?>.</p>
          <?php if ($pedido['referencia_pix']): ?><p class="muted">ID da entrada <?= h($pedido['referencia_pix']) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php if ($saldoRecebido): ?>
          <p class="alerta ok">Saldo registrado em <?= h(dataLocal((int)$saldoRecebido['recebido'])) ?> por <?= h($saldoRecebido['metodo']) ?>.</p>
          <?php if ($saldoRecebido['referencia_pix']): ?><p class="muted">ID do saldo <?= h($saldoRecebido['referencia_pix']) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php if (in_array($pedido['estado'],['confirmado','sinal_recebido'],true)): ?>
          <?php $entradaPendente = $pedido['estado']==='confirmado'; ?>
          <p class="muted"><?= $entradaPendente?'A encomenda aguarda a entrada. Confira o crédito antes de registrar o pagamento.':'Combine o pagamento dos outros 50% com o cliente na retirada. Após receber, você pode registrar o saldo aqui.' ?></p>
          <?php if ($entradaPendente && $pedido['confirmado'] === null && (int)$pedido['criado'] < time()-PRAZO_PEDIDO): ?>
            <p class="muted">O prazo para abrir o Pix no site passou. Se houve crédito no banco, confira e registre a entrada abaixo. Sem esse registro, a manutenção exclui o pedido 60 dias após a criação.</p>
          <?php endif; ?>
          <details class="risco"><summary><?= $entradaPendente?'Registrar entrada':'Registrar saldo' ?></summary>
            <?php if ($entradaPendente && $pedido['pix']): ?><p class="muted">Referência da cobrança <?= h(referenciaCobrancaPix($pedido['codigo'],'entrada')) ?></p><?php endif; ?>
            <form method="post" class="formulario">
              <?php formAcao('alterar-pedido'); ?>
              <input type="hidden" name="id" value="<?= h($pedido['codigo']) ?>">
              <label>Forma de pagamento <select name="metodo"><option>Pix</option><option>Dinheiro</option></select></label>
              <label>Valor recebido <input name="valor" inputmode="decimal" required maxlength="12"></label>
              <label>ID da transação no extrato, para Pix <input name="referencia" maxlength="45"></label>
              <label class="check"><input type="checkbox" name="conferi" required> Conferi o crédito desta parcela no banco ou recebi o dinheiro</label>
              <button class="botao principal" name="modo" value="<?= $entradaPendente?'receber':'receber-saldo' ?>"><?= $entradaPendente?'Confirmar entrada recebida':'Confirmar saldo recebido' ?></button>
            </form>
          </details>
          <?php if ($entradaPendente && ($pedido['confirmado'] !== null || (int)$pedido['criado'] >= time()-PRAZO_PEDIDO) && !$pedido['pix'] && !empty($loja['chavePix']) && !empty($loja['recebedorPix'])): ?>
            <form method="post" class="linha-form"><?php formAcao('alterar-pedido'); ?><input type="hidden" name="id" value="<?= h($pedido['codigo']) ?>"><button class="botao contorno" name="modo" value="reativar-pix">Liberar Pix atualizado</button></form>
          <?php endif; ?>
          <?php if ($entradaPendente): ?>
            <form method="post" class="linha-form"><?php formAcao('alterar-pedido'); ?><input type="hidden" name="id" value="<?= h($pedido['codigo']) ?>"><button class="botao contorno" name="modo" value="cancelar">Cancelar pedido</button></form>
          <?php else: ?>
            <details class="risco"><summary>Cancelar pedido</summary>
              <p>Se houver reembolso, faça-o pelo banco.</p>
              <form method="post" class="formulario">
                <?php formAcao('alterar-pedido'); ?><input type="hidden" name="id" value="<?= h($pedido['codigo']) ?>">
                <label class="check"><input type="checkbox" name="confirmar_cancelamento" required> Confirmo o cancelamento após receber a entrada</label>
                <button class="botao excluir" name="modo" value="cancelar">Cancelar pedido</button>
              </form>
            </details>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <section class="painel">
      <h2>Pedidos recentes</h2>
      <?php if (!$recentes): ?><p class="muted">Nenhum pedido registrado.</p><?php endif; ?>
      <?php listaPedidos($recentes,$rotulos); ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
</main>
<?php if ($autenticado): ?></div><?php endif; ?>
</body>
</html>
