<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require DECOMER_PRIVADO.'/vitrine.php';
try {
    $pdo = banco();
    $pdo->beginTransaction();
    $config = estadoLoja($pdo);
    $lista = produtos($pdo);
    $pdo->commit();
} catch (Throwable $e) {
    [$status,$mensagem] = falhaPublica($e);
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensagem);
}
$loja = $config['loja'];
$categorias = $config['categorias'];
$ordenados = array_values($lista);
$primeiro = $lista[$loja['destaquePrincipal']] ?? ($ordenados[0] ?? null);
$segundo = $lista[$loja['destaqueSecundario']] ?? ($ordenados[1] ?? null);
$whats = 'https://wa.me/'.$loja['whatsapp'];
$logo = logoUrl($loja);
$titulo = $loja['titulo'];
$marca = $loja['marca'];
$marcaNoTitulo = str_ends_with($titulo,' '.$marca);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="<?= h($loja['descSite']) ?>">
  <meta name="theme-color" content="#f9f3e8">
  <meta name="referrer" content="no-referrer">
  <meta property="og:title" content="<?= h($titulo) ?>">
  <meta property="og:description" content="<?= h($loja['descSite']) ?>">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="pt_BR">
  <link rel="icon" href="<?= h($loja['logo'] ? $logo : 'assets/img/icone.png') ?>">
  <link rel="preload" href="assets/amatic-sc-bold.ttf" as="font" type="font/ttf" crossorigin>
  <link rel="stylesheet" href="assets/estilo.css?v=7">
  <script src="assets/pedido.js?v=3" defer></script>
  <script src="assets/loja.js?v=5" defer></script>
  <script src="assets/iniciar.js" defer></script>
  <title><?= h($titulo.' | '.$loja['submarca']) ?></title>
</head>
<body>
  <a class="pular" href="#catalogo">Pular para o catálogo</a>
  <div class="faixa" id="inicio"><span aria-hidden="true">✦</span> <?= h($loja['faixa']) ?> <span aria-hidden="true">✦</span></div>
  <header class="cabecalho">
    <div class="largura cabecalho-conteudo">
      <a class="marca" href="#inicio">
        <img src="<?= h($logo) ?>" width="58" height="58" alt="">
        <span><strong><?= h($marca) ?></strong><small><?= h($loja['submarca']) ?></small></span>
      </a>
      <button class="sacola-topo" type="button" data-abrir-sacola aria-haspopup="dialog" disabled>
        <span class="icone-sacola" aria-hidden="true"></span><span>Sacola</span>
        <span class="sacola-contador" data-contagem aria-hidden="true">0</span>
      </button>
      <nav aria-label="Navegação principal">
        <a href="#catalogo">Catálogo</a>
        <a href="#encomendas">Como encomendar</a>
        <a href="#massa">Sobre a massa</a>
      </nav>
    </div>
  </header>
  <main>
    <section class="abertura" aria-labelledby="titulo">
      <div class="largura abertura-conteudo">
        <div class="abertura-texto">
          <h1 id="titulo"><?php if ($marcaNoTitulo): ?><?= h(substr($titulo,0,-strlen($marca))) ?><em><?= h($marca) ?></em><?php else: ?><?= h($titulo) ?><?php endif; ?></h1>
          <div class="filete" aria-hidden="true"><span>✧</span></div>
          <p><?= h($loja['chamada']) ?></p>
          <div class="abertura-acoes">
            <a class="botao botao-vinho abertura-cta" href="#catalogo">Escolher biscoitos <span aria-hidden="true">↘</span></a>
          </div>
        </div>
        <div class="abertura-fotos">
          <?php mostrarDestaque($primeiro,true); if ($segundo && ($primeiro['id']??'')!==$segundo['id']) mostrarDestaque($segundo,false); ?>
        </div>
      </div>
    </section>

    <section class="largura vitrine" id="catalogo" aria-labelledby="catalogo-titulo">
      <div class="vitrine-topo">
        <div>
          <h2 id="catalogo-titulo"><?= h($loja['tituloCatalogo']) ?></h2>
          <p><?= h($loja['descCatalogo']) ?></p>
        </div>
        <p class="resultado" id="resultado" aria-live="polite"><?= count($lista) ?> <?= count($lista)===1?'produto':'produtos' ?></p>
      </div>
      <div class="filtros" aria-label="Filtrar por categoria" role="group" hidden>
        <button type="button" data-filtro="todos" aria-pressed="true">Todos</button>
        <?php foreach ($categorias as $id=>$nome): if (!in_array($id,array_column($lista,'categoria'),true)) continue; ?>
          <button type="button" data-filtro="<?= h($id) ?>" aria-pressed="false"><?= h($nome) ?></button>
        <?php endforeach; ?>
      </div>
      <noscript><p class="aviso">Ative o JavaScript para usar a sacola ou <a href="<?= h($whats) ?>">faça a encomenda pelo WhatsApp</a>.</p></noscript>
      <p id="catalogo-pausado" class="aviso" hidden>As encomendas estão pausadas. Consulte o atendimento pelo WhatsApp.</p>
      <div class="produtos">
        <?php mostrarProdutos($lista); ?>
      </div>
    </section>

    <section class="encomendas" id="encomendas" aria-labelledby="encomendas-titulo">
      <div class="largura">
        <h2 id="encomendas-titulo"><?= h($loja['tituloEncomendas']) ?></h2>
        <ol class="etapas">
          <?php for ($etapa=1; $etapa<=3; $etapa++): ?>
            <li><h3><?= h($loja['etapa'.$etapa.'Titulo']) ?></h3><p><?= h($loja['etapa'.$etapa.'Texto']) ?></p></li>
          <?php endfor; ?>
        </ol>
        <div class="retirada">
          <div><h3>Retirada e envio</h3><p><?= h($loja['retirada']) ?></p></div>
          <a class="botao botao-contorno-claro" href="<?= h($whats) ?>">Falar com <?= h($loja['responsavel']) ?> <span aria-hidden="true">↗</span></a>
        </div>
      </div>
    </section>

    <section class="largura massa" id="massa" aria-labelledby="massa-titulo">
      <div>
        <h2 id="massa-titulo"><?= h($loja['tituloMassa']) ?></h2>
        <p><?= h($loja['sobreMassa']) ?></p>
      </div>
      <div class="ingredientes">
        <h3>Ingredientes e alergênicos</h3>
        <div id="ficha-massa" <?= $loja['ingredientes']||$loja['alergenicos']?'':'hidden' ?>>
          <p id="ingredientes" <?= $loja['ingredientes']?'':'hidden' ?>><strong>Ingredientes</strong> <?= h($loja['ingredientes']) ?></p>
          <p id="alergenicos" <?= $loja['alergenicos']?'':'hidden' ?>><strong>Alergênicos</strong> <?= h($loja['alergenicos']) ?></p>
        </div>
        <p id="consulta-massa" <?= $loja['ingredientes']&&$loja['alergenicos']?'hidden':'' ?>><?= h($loja['consultaMassa']) ?></p>
        <a id="contato-massa" href="<?= h($whats) ?>">Consultar pelo WhatsApp <span aria-hidden="true">↗</span></a>
      </div>
    </section>
  </main>

  <?php $voltarLoja = '#inicio'; require DECOMER_PRIVADO.'/rodape.php'; ?>

  <div class="barra-sacola" id="barra-sacola" hidden>
    <button type="button" data-abrir-sacola aria-haspopup="dialog" class="botao botao-verde">
      <span><span class="icone-sacola" aria-hidden="true"></span> <span id="barra-acao">Ver sacola</span> <span id="barra-unidades"></span></span>
      <strong id="barra-total"></strong>
    </button>
  </div>
  <p class="so-leitor" id="status-sacola" role="status" aria-atomic="true"></p>

  <dialog class="sacola" id="sacola" aria-labelledby="sacola-titulo">
    <div class="sacola-cabecalho">
      <div><h2 id="sacola-titulo">Sua sacola</h2><p id="sacola-unidades" aria-live="polite">0 itens</p></div>
      <button type="button" class="fechar" data-fechar aria-label="Fechar sacola" autofocus><span aria-hidden="true">×</span></button>
    </div>
    <div class="sacola-corpo">
      <div id="sacola-vazia" class="sacola-vazia">
        <span class="icone-sacola" aria-hidden="true"></span>
        <p>Sua sacola está vazia.</p>
        <button type="button" class="botao botao-vinho" data-fechar>Voltar ao catálogo</button>
      </div>
      <div id="sacola-com-itens" hidden>
        <ul class="sacola-itens" id="sacola-itens" aria-label="Itens da sacola"></ul>
        <div class="pedido-resumo">
          <dl>
            <div class="pedido-total"><dt>Total dos produtos</dt><dd id="total"></dd></div>
            <div><dt>Entrada agora · 50%</dt><dd id="sinal"></dd></div>
            <div><dt>Na retirada · 50%</dt><dd id="restante"></dd></div>
          </dl>
          <p>O Pix é só da entrada. Combine a retirada e o pagamento do restante com a loja.</p>
        </div>
        <form id="pedido" class="pedido-form" method="post" action="pedidos.php?acao=criar">
          <label for="comprador">Seu nome</label>
          <input id="comprador" name="nome" type="text" required autocomplete="name" maxlength="80" aria-describedby="nome-erro">
          <p class="erro" id="nome-erro" hidden></p>
          <label for="telefone-comprador">Seu WhatsApp com DDD</label>
          <input id="telefone-comprador" name="telefone" type="tel" required autocomplete="tel" maxlength="24" placeholder="(61) 99999-0000" aria-describedby="telefone-erro pedido-nota">
          <p class="erro" id="telefone-erro" hidden></p>
          <p class="pedido-nota" id="pedido-nota">A loja usa seu contato para combinar a encomenda.</p>
          <nav class="pedido-legal" aria-label="Informações antes do pedido">
            <a href="condicoes-de-compra.php" target="_blank" rel="noopener">Condições de Compra<span class="so-leitor"> (nova aba)</span></a>
            <a href="privacidade.php" target="_blank" rel="noopener">Política de Privacidade<span class="so-leitor"> (nova aba)</span></a>
          </nav>
          <button type="submit" class="botao botao-verde botao-whatsapp" disabled>Ver Pix da entrada</button>
        </form>
        <p id="erro-pedido" class="erro" role="alert" hidden></p>
      </div>
    </div>
  </dialog>

  <dialog class="foto-dialogo" id="foto-dialogo" aria-labelledby="foto-titulo">
    <div class="foto-dialogo-topo">
      <div><h2 id="foto-titulo">Foto do produto</h2><p id="foto-modelo" hidden></p></div>
      <button type="button" class="fechar" data-fechar aria-label="Fechar foto" autofocus><span aria-hidden="true">×</span></button>
    </div>
    <div class="foto-dialogo-area"><img id="foto-grande" alt=""></div>
  </dialog>

  <template id="linha-sacola">
    <li class="item-sacola">
      <img width="64" height="78" alt="">
      <div class="item-dados">
        <strong class="item-nome"></strong><span class="item-modelo"></span>
        <div class="item-rodape">
          <div class="quantidade">
            <button type="button" data-ajustar="-1"><span aria-hidden="true">−</span></button>
            <span class="item-quantidade"></span>
            <button type="button" data-ajustar="1"><span aria-hidden="true">+</span></button>
          </div>
          <strong class="item-subtotal"></strong>
        </div>
      </div>
    </li>
  </template>
  <template id="dados-vitrine"><?= h(jsonSeguro(dadosVitrine($config,$lista))) ?></template>
</body>
</html>

