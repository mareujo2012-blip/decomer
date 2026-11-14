<?php
declare(strict_types=1);

function dadosVitrine(array $config, array $produtos): array
{
    $loja = $config['loja'];
    $publico = [
        'responsavel'=>$loja['responsavel'], 'whatsapp'=>$loja['whatsapp'],
        'recebendo'=>encomendasAbertas($loja) && adminConfigurado(),
        'revisao'=>$config['revisao'],
    ];
    $lista = [];
    foreach ($produtos as $p) {
        $p['fotoPequena'] = fotoUrl($p['foto'],'400');
        $p['fotoGrande'] = fotoUrl($p['foto']);
        $p['grande'] = $p['foto']['grande'];
        unset($p['foto']);
        $lista[] = $p;
    }
    return ['catalogo'=>$lista,'loja'=>$publico];
}

function mostrarProdutos(array $lista): void
{
    foreach (array_values($lista) as $i => $p):
        $foto = $p['foto'];
        $pequena = fotoUrl($foto,'400');
        $grande = fotoUrl($foto);
        $nome = $p['nome'].($p['modelo'] ? ' · '.$p['modelo'] : '');
        ?>
        <article class="produto" data-categoria="<?= h($p['categoria']) ?>">
          <a class="produto-foto" data-foto="<?= h($p['id']) ?>" href="<?= h($grande) ?>" aria-label="Ampliar foto de <?= h($nome) ?>">
            <img src="<?= h($pequena) ?>" srcset="<?= h($pequena) ?> <?= $foto['pequena']['w'] ?>w, <?= h($grande) ?> <?= $foto['grande']['w'] ?>w" sizes="(max-width: 599px) calc(46vw - 16px), (max-width: 899px) calc(31vw - 22px), (max-width: 1231px) calc(25vw - 45px), 260px" width="<?= $foto['pequena']['w'] ?>" height="<?= $foto['pequena']['h'] ?>" <?= $i>1?'loading="lazy"':'' ?> decoding="async" alt="<?= h($nome) ?>">
            <span class="ampliar" aria-hidden="true">↗</span>
          </a>
          <div class="produto-dados">
            <h3><?= h($p['nome']) ?></h3>
            <?php if ($p['modelo'] !== ''): ?><p class="modelo"><?= h($p['modelo']) ?></p><?php endif; ?>
            <?php if ($p['descricao'] !== ''): ?><p class="descricao"><?= h($p['descricao']) ?></p><?php endif; ?>
            <p class="preco"><?= h(brl($p['centavos'])) ?></p>
            <button class="botao adicionar" type="button" data-adicionar="<?= h($p['id']) ?>" disabled><span aria-hidden="true">+</span> Adicionar <small aria-hidden="true" hidden></small></button>
          </div>
        </article>
    <?php endforeach;
}

function mostrarDestaque(?array $p, bool $principal): void
{
    if (!$p) return;
    $f = $p['foto'];
    $pequena = fotoUrl($f,'400');
    $grande = fotoUrl($f);
    ?>
    <div class="abertura-foto-<?= $principal?'principal':'menor' ?>">
      <img src="<?= h($principal?$grande:$pequena) ?>"
           <?php if ($principal): ?>srcset="<?= h($pequena) ?> <?= $f['pequena']['w'] ?>w, <?= h($grande) ?> <?= $f['grande']['w'] ?>w" sizes="(max-width: 599px) clamp(135px, 39vw, 220px), (max-width: 1079px) 28vw, 310px" fetchpriority="high"<?php endif; ?>
           width="<?= $f[$principal?'grande':'pequena']['w'] ?>" height="<?= $f[$principal?'grande':'pequena']['h'] ?>"
           alt="<?= h($p['nome'].($p['modelo']?' · '.$p['modelo']:'')) ?>">
    </div>
    <?php
}
