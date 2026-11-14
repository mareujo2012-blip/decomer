<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
try {
    $loja = estadoLoja(banco())['loja'];
} catch (Throwable $e) {
    [$status,$mensagem] = falhaPublica($e);
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensagem);
}
$logo = logoUrl($loja);
$whats = 'https://wa.me/'.$loja['whatsapp'];
$voltarLoja = './';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#faf5ec">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="referrer" content="no-referrer">
  <link rel="icon" href="<?= h($loja['logo'] ? $logo : 'assets/img/icone.png') ?>">
  <link rel="stylesheet" href="assets/estilo.css?v=5">
  <script src="assets/pedido.js?v=3" defer></script>
  <script src="assets/qrcode-2.0.4.js?v=1" defer></script>
  <script src="assets/acompanhar.js?v=1" defer></script>
  <title>Seu pedido | <?= h($loja['marca']) ?></title>
</head>
<body class="pagina-pedido">
  <header class="cabecalho">
    <div class="largura topo-pedido">
      <a class="marca" href="./">
        <img src="<?= h($logo) ?>" width="58" height="58" alt="">
        <span><strong><?= h($loja['marca']) ?></strong><small><?= h($loja['submarca']) ?></small></span>
      </a>
      <a class="voltar-catalogo" href="./"><span aria-hidden="true">←</span> Catálogo</a>
    </div>
  </header>
  <main class="largura tela-pedido">
    <div class="titulo-pedido">
      <h1>Seu pedido</h1>
      <p id="referencia-pedido" class="pedido-referencia" hidden>Referência <strong id="codigo-pedido"></strong></p>
    </div>
    <p id="status-pedido" class="so-leitor" role="status"></p>
    <p id="consulta-aviso" class="consulta-aviso" role="alert" hidden></p>
    <button id="consulta-repetir" class="acao-secundaria" type="button" hidden>Tentar novamente</button>
    <noscript><p class="consulta-aviso">Ative o JavaScript para consultar seu pedido e acessar o Pix.</p></noscript>
    <section id="recuperar-pedido" class="painel-pedido recuperar-pedido" hidden>
      <h2>Consultar pedido</h2>
      <p>Abra o link privado que você guardou ou cole abaixo o código de acesso.</p>
      <form id="form-consulta" class="pedido-form" method="post" action="pedidos.php?acao=consultar">
        <label for="codigo-acesso">Código de acesso</label>
        <input type="text" id="codigo-acesso" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="120" aria-describedby="codigo-erro" required>
        <p id="codigo-erro" class="erro" hidden></p>
        <button type="submit" class="botao botao-verde">Abrir pedido</button>
      </form>
      <a class="link-pedido" href="<?= h($whats) ?>">Preciso de ajuda com meu pedido</a>
    </section>
    <div id="pedido-detalhe" class="detalhe-pedido" hidden>
      <section class="painel-pedido painel-pagamento" aria-labelledby="etapa-pedido">
        <div class="pagamento-cabecalho">
          <h2 id="etapa-pedido" tabindex="-1"></h2>
          <strong id="valor-pix" class="valor-pix" hidden></strong>
        </div>
        <p id="orientacao-pedido" class="orientacao-pedido"></p>
        <div id="pagamento-ativo" class="pagamento-ativo" hidden>
          <div class="quadro-qr">
            <canvas id="qr-pix" role="img" aria-label="QR Code do Pix da parcela disponível" hidden></canvas>
            <p id="qr-ajuda" hidden>Com outro aparelho, escaneie pelo app do banco.</p>
          </div>
          <div class="pix-instrucoes">
            <button id="copiar-pix" class="botao botao-verde" type="button">Copiar código Pix</button>
            <p>No celular, copie o código e abra o app do seu banco. Escolha <strong>Pix Copia e Cola</strong>, cole e confira o titular e o valor.</p>
            <p>Titular no banco <strong id="pix-titular"></strong></p>
            <div id="pix-manual" class="pix-manual" hidden>
              <label for="pix-texto">Código Pix</label>
              <input type="text" id="pix-texto" readonly autocomplete="off">
            </div>
            <p id="pix-feedback" class="feedback-pedido" role="status"></p>
          </div>
        </div>
        <div class="acoes-acompanhamento">
          <a id="whatsapp-loja" class="botao botao-verde" rel="noreferrer" hidden>Combinar pelo WhatsApp</a>
          <button id="atualizar-pedido" class="acao-secundaria" type="button">Atualizar situação</button>
        </div>
      </section>
      <div class="lado-pedido">
        <section class="painel-pedido resumo-parcelas" aria-labelledby="resumo-titulo">
          <h2 id="resumo-titulo">Resumo do pagamento</h2>
          <dl>
            <div class="parcela-total"><dt>Total dos produtos</dt><dd id="pedido-total"></dd></div>
            <div class="parcela-linha" data-parcela="entrada"><dt>Entrada · 50%<small></small></dt><dd></dd></div>
            <div class="parcela-linha" data-parcela="saldo"><dt>Saldo · 50%<small></small></dt><dd></dd></div>
          </dl>
          <details>
            <summary>Ver itens da encomenda</summary>
            <ul id="itens-pedido"></ul>
          </details>
        </section>
        <section class="painel-pedido guardar-acesso" aria-labelledby="guardar-titulo">
          <h2 id="guardar-titulo">Guarde seu link</h2>
          <p>Salve o link nas suas notas ou na sua conversa com a loja. Use o mesmo link para voltar ao pedido e pagar o saldo.</p>
          <button class="acao-secundaria" type="button" id="copiar-link">Copiar link privado</button>
          <div id="link-manual" class="pix-manual" hidden>
            <label for="link-texto">Link privado do pedido</label>
            <input id="link-texto" type="text" readonly autocomplete="off">
          </div>
          <details>
            <summary>Ver código de acesso</summary>
            <p>Você também pode colar este código em <strong>Consultar pedido</strong>.</p>
            <code id="codigo-retorno"></code>
            <button class="acao-secundaria" type="button" id="copiar-codigo">Copiar código de acesso</button>
          </details>
          <p id="acesso-feedback" class="feedback-pedido" role="status"></p>
          <button id="encerrar-consulta" class="link-pedido" type="button">Encerrar consulta neste aparelho</button>
        </section>
      </div>
    </div>
  </main>
  <?php require DECOMER_PRIVADO.'/rodape.php'; ?>
</body>
</html>
