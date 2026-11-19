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
  <link rel="stylesheet" href="assets/estilo.css?v=7">
  <script src="assets/pedido.js?v=4" defer></script>
  <script src="assets/qrcode-2.0.4.js?v=1" defer></script>
  <script src="assets/acompanhar.js?v=3" defer></script>
  <title>Pix da entrada | <?= h($loja['marca']) ?></title>
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
      <h1>Pix da entrada</h1>
    </div>
    <p id="status-pedido" class="so-leitor" role="status"></p>
    <p id="consulta-aviso" class="consulta-aviso" role="alert" hidden></p>
    <button id="consulta-repetir" class="acao-secundaria" type="button" hidden>Tentar novamente</button>
    <noscript><p class="consulta-aviso">Ative o JavaScript para consultar seu pedido e acessar o Pix.</p></noscript>
    <section id="recuperar-pedido" class="painel-pedido recuperar-pedido" hidden>
      <h2>Precisa de ajuda?</h2>
      <p>Abra o Pix da entrada pela sacola ou fale com a loja sobre uma encomenda que você já fez.</p>
      <div class="acoes-acompanhamento">
        <a class="botao botao-verde" href="./">Voltar ao catálogo</a>
        <a class="acao-secundaria" href="<?= h($whats) ?>">Falar com <?= h($loja['responsavel']) ?></a>
      </div>
    </section>
    <div id="pedido-detalhe" class="detalhe-pedido" hidden>
      <section class="painel-pedido painel-pagamento" aria-labelledby="etapa-pedido">
        <div class="pagamento-cabecalho">
          <h2 id="etapa-pedido" tabindex="-1"></h2>
          <strong id="valor-pix" class="valor-pix" hidden></strong>
        </div>
        <p id="orientacao-pedido" class="orientacao-pedido"></p>
        <p id="lembrete-saldo" class="lembrete-saldo" hidden></p>
        <div id="pagamento-ativo" class="pagamento-ativo" hidden>
          <div class="quadro-qr">
            <canvas id="qr-pix" role="img" aria-label="QR Code do Pix da entrada" hidden></canvas>
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
            <a id="whatsapp-pago" class="botao botao-vinho" rel="noreferrer">Já paguei · enviar pedido no WhatsApp</a>
          </div>
        </div>
        <div class="acoes-acompanhamento">
          <a id="whatsapp-loja" class="acao-secundaria" rel="noreferrer">Falar com <?= h($loja['responsavel']) ?> pelo WhatsApp</a>
          <button id="atualizar-pedido" class="acao-secundaria" type="button" hidden>Verificar se a loja confirmou</button>
        </div>
      </section>
      <div class="lado-pedido">
        <section class="painel-pedido resumo-parcelas" aria-labelledby="resumo-titulo">
          <h2 id="resumo-titulo">Resumo do pagamento</h2>
          <dl>
            <div class="parcela-total"><dt>Total dos produtos</dt><dd id="pedido-total"></dd></div>
            <div class="parcela-linha" data-parcela="entrada"><dt>Entrada · 50%<small></small></dt><dd></dd></div>
            <div class="parcela-linha" data-parcela="saldo"><dt>Na retirada · 50%<small></small></dt><dd></dd></div>
          </dl>
          <details>
            <summary>Ver itens da encomenda</summary>
            <ul id="itens-pedido"></ul>
          </details>
        </section>
      </div>
    </div>
  </main>
  <?php require DECOMER_PRIVADO.'/rodape.php'; ?>
</body>
</html>
