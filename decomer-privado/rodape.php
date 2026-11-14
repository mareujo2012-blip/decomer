<footer class="rodape">
  <div class="largura rodape-conteudo">
    <a class="marca" href="<?= h($voltarLoja) ?>">
      <img src="<?= h($logo) ?>" width="58" height="58" alt="" loading="lazy">
      <span><strong><?= h($loja['marca']) ?></strong><small><?= h($loja['submarca']) ?></small></span>
    </a>
    <div class="contatos">
      <?php if ($loja['instagram']): ?><a href="<?= h('https://www.instagram.com/'.$loja['instagram'].'/') ?>">@<?= h($loja['instagram']) ?> <span aria-hidden="true">↗</span></a><?php endif; ?>
      <a href="<?= h($whats) ?>">WhatsApp <span aria-hidden="true">↗</span></a>
    </div>
    <?php if ($loja['razaoSocial'] || $loja['cnpj'] || $loja['endereco']): ?>
      <div class="dados-loja" id="dados-loja">
        <?php if ($loja['razaoSocial']): ?><p><?= h($loja['razaoSocial']) ?></p><?php endif; ?>
        <?php if ($loja['cnpj']): ?><p>CNPJ <?= h($loja['cnpj']) ?></p><?php endif; ?>
        <?php if ($loja['endereco']): ?><p><?= h($loja['endereco']) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>
    <nav class="rodape-legal" aria-label="Informações da loja">
      <a href="privacidade.php" <?= ($documento??'')==='privacidade'?'aria-current="page"':'' ?>>Política de Privacidade</a>
      <a href="condicoes-de-compra.php" <?= ($documento??'')==='compra'?'aria-current="page"':'' ?>>Condições de Compra</a>
    </nav>
  </div>
</footer>
