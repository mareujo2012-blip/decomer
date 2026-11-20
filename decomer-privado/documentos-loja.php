<?php
declare(strict_types=1);

try {
    $loja = estadoLoja(banco())['loja'];
} catch (Throwable $e) {
    [$status,$mensagem] = falhaPublica($e);
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensagem);
}

$privacidade = $documento === 'privacidade';
$titulo = $privacidade ? 'Política de Privacidade' : 'Condições de Compra';
$resumo = $privacidade
    ? 'Como a loja usa os dados da sua encomenda e como você pode exercer seus direitos.'
    : 'Como encomendar, confirmar o pagamento, combinar a entrega e pedir atendimento.';
$secoes = $privacidade
    ? ['dados'=>'Dados do pedido','compartilhamento'=>'Serviços externos','retencao'=>'Prazos de guarda',
       'cookies'=>'Cookies e armazenamento','direitos'=>'Seus direitos e contato']
    : ['pedido'=>'Como encomendar','pagamento'=>'Pagamento','entrega'=>'Retirada e envio',
       'cancelamento'=>'Cancelamento e reembolso','atendimento'=>'Atendimento'];
$whats = 'https://wa.me/'.$loja['whatsapp'];
$logo = logoUrl($loja);
$voltarLoja = './';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="<?= h($resumo) ?>">
  <meta name="theme-color" content="#f9f3e8">
  <title><?= h($titulo.' | '.$loja['marca']) ?></title>
  <link rel="icon" href="<?= h($loja['logo'] ? $logo : 'assets/img/icone.png') ?>">
  <link rel="stylesheet" href="assets/estilo.css?v=7">
</head>
<body class="documento">
  <a class="pular" href="#conteudo">Pular para o conteúdo</a>
  <header class="cabecalho documento-cabecalho">
    <div class="largura cabecalho-conteudo">
      <a class="marca" href="./">
        <img src="<?= h($logo) ?>" width="58" height="58" alt="">
        <span><strong><?= h($loja['marca']) ?></strong><small><?= h($loja['submarca']) ?></small></span>
      </a>
      <a class="botao botao-contorno" href="./">Voltar à loja</a>
    </div>
  </header>
  <main id="conteudo">
    <div class="documento-titulo">
      <div class="largura">
        <h1><?= h($titulo) ?></h1>
        <p><?= h($resumo) ?></p>
      </div>
    </div>
    <div class="largura documento-layout">
      <nav class="documento-indice" aria-label="Nesta página">
        <p>Nesta página</p>
        <ul>
          <?php foreach ($secoes as $id=>$rotulo): ?>
            <li><a href="#<?= h($id) ?>"><?= h($rotulo) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <article class="documento-texto">
      <?php if ($privacidade): ?>
        <section id="dados">
          <h2>Dados do pedido</h2>
          <p>A <?= h($loja['razaoSocial'] ?: $loja['marca']) ?> é responsável pelo uso dos dados desta loja. Ao gerar um pedido, o site registra os itens, as quantidades, os valores, a data, o código, o nome e o WhatsApp do comprador. Usamos esses dados para atender à sua solicitação, acompanhar a encomenda e conferir o pagamento.</p>
          <p>Ao combinar a entrega pelo WhatsApp, você pode precisar informar um endereço ou outros dados de contato. A loja registra as referências dos pagamentos que confere no extrato.</p>
        </section>
        <section id="compartilhamento">
          <h2>Serviços externos</h2>
          <p>Ao abrir o link do WhatsApp, o serviço recebe o texto pré-preenchido. A mensagem pré-preenchida inclui seu nome, os itens e o total do pedido. Ao enviar a mensagem, seu número e o conteúdo da conversa ficam disponíveis para a loja. Se houver envio, os dados necessários à entrega podem ser compartilhados com o serviço de transporte escolhido.</p>
          <p>Serviços de hospedagem e segurança da conexão podem processar dados técnicos, como o endereço IP. A loja também usa um identificador derivado do IP para limitar tentativas abusivas de gerar ou consultar pedidos. O pagamento é combinado com a loja pelo WhatsApp e feito no aplicativo do seu banco ou na retirada. A loja não solicita senha bancária ou dados de cartão.</p>
        </section>
        <section id="retencao">
          <h2>Prazos de guarda</h2>
          <p>Na rotina de manutenção, pedidos sem pagamento registrado pela loja são excluídos 60 dias após a criação. O nome e o WhatsApp são removidos dos registros após 90 dias.</p>
          <p>Os demais registros do pedido e do pagamento podem ser mantidos para atender à encomenda, cumprir obrigações legais ou exercer direitos. Os prazos automáticos acima se aplicam ao sistema da loja. As mensagens enviadas pelo WhatsApp são tratadas separadamente.</p>
        </section>
        <section id="cookies">
          <h2>Cookies e armazenamento</h2>
          <ul>
            <li><strong>Sacola.</strong> O site guarda os produtos e as quantidades no armazenamento local do navegador. Você pode remover os itens da sacola ou limpar os dados deste site no navegador.</li>
            <li><strong>Consulta de pedidos anteriores.</strong> Ao abrir o link de um pedido feito antes, o site usa um identificador privado na sessão da aba para preservar a consulta se você recarregar a página.</li>
            <li><strong>Painel da loja.</strong> Um cookie de sessão mantém o acesso da administração após o login.</li>
          </ul>
          <p>A vitrine usa esse armazenamento para operar a sacola e a consulta. Os links do WhatsApp e do Instagram levam a serviços com políticas próprias.</p>
        </section>
        <section id="direitos">
          <h2>Seus direitos e contato</h2>
          <p>Você pode solicitar confirmação sobre o uso de seus dados, acesso, correção e outras medidas previstas na LGPD. Para tratar de privacidade, fale com a loja pelo WhatsApp. Podemos pedir as informações necessárias para verificar que a solicitação se refere ao seu pedido.</p>
          <a class="botao botao-verde" href="<?= h($whats) ?>">Falar com a loja pelo WhatsApp</a>
        </section>
      <?php else: ?>
        <section id="pedido">
          <h2>Como encomendar</h2>
          <p>Escolha os modelos e as quantidades no catálogo. Revise a sacola, preencha seu nome e WhatsApp e toque em <em>Enviar pedido no WhatsApp</em>. A conversa abre com a loja já com a lista do pedido. A loja confirma a disponibilidade e combina com você o pagamento e a retirada. Se precisar de envio, confirme disponibilidade, frete e prazo com a loja antes de pagar.</p>
          <p>Os modelos seguem as fotos do catálogo. A loja recebe encomendas até a data anunciada ou até atingir a capacidade de produção. Consulte as informações sobre ingredientes e alergênicos <a href="./#massa">na seção Sobre a massa</a> antes de encomendar.</p>
        </section>
        <section id="pagamento">
          <h2>Pagamento</h2>
          <p>O site não cobra pelo pedido. A forma e o momento do pagamento são combinados com a loja pelo WhatsApp antes da produção, inclusive se houver pagamento antecipado. Se houver envio, combine também o frete.</p>
          <p>Antes de pagar, confira no app do banco o valor e o nome do titular informados pela loja. A loja confirma o pagamento após conferir o crédito no extrato.</p>
        </section>
        <section id="entrega">
          <h2>Retirada e envio</h2>
          <p><?= h($loja['retirada']) ?></p>
          <p>Confirme a disponibilidade, o prazo e o custo da opção escolhida antes do pagamento.</p>
        </section>
        <section id="cancelamento">
          <h2>Cancelamento e reembolso</h2>
          <p>Para solicitar cancelamento, devolução ou reembolso, fale pelo WhatsApp usado na encomenda e informe seu nome e a data do pedido. Em compras a distância, o art. 49 do Código de Defesa do Consumidor prevê sete dias para exercer o direito de arrependimento, contados da assinatura do contrato ou do recebimento do produto. Quando esse direito se aplica, os valores pagos, inclusive os antecipados, são devolvidos de imediato e atualizados monetariamente.</p>
          <p>Se houver problema com o produto, atraso ou divergência em relação à encomenda, procure a loja pelo mesmo canal para tratar da solução.</p>
        </section>
        <section id="atendimento">
          <h2>Atendimento</h2>
          <p>Para dúvidas sobre a encomenda, entrega ou pagamento, fale com <?= h($loja['responsavel']) ?>.</p>
          <a class="botao botao-verde" href="<?= h($whats) ?>">Falar com a loja pelo WhatsApp</a>
        </section>
      <?php endif; ?>
        <p class="documento-data">Atualizado em <time datetime="2026-11-20">20 de novembro de 2026</time>.</p>
      </article>
    </div>
  </main>
  <?php require DECOMER_PRIVADO.'/rodape.php'; ?>
</body>
</html>
