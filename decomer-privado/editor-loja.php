<?php
declare(strict_types=1);

[$tituloSecao,$descricaoSecao,$botaoSalvar] = $secoes[$secao];
$urlSecao = '?aba=pagina&secao='.$secao;
$definicoes = array_replace(...array_values(camposLoja()));
$versao = versaoEdicaoLoja($loja,$secao);
$valorEnviado = static function (string $campo) use ($reexibirEdicao,$loja): string {
    $valor = $reexibirEdicao && is_string($_POST[$campo]??null) ? $_POST[$campo] : ($loja[$campo]??'');
    return is_string($valor) ? mb_substr($valor,0,2400,'UTF-8') : '';
};
if ($reexibirEdicao && is_string($_POST['versao']??null)) $versao = substr($_POST['versao'],0,64);
$campo = static function (string $chave, int $linhas = 0, string $classe = '') use ($definicoes,$valorEnviado): void {
    [$nome,$max] = $definicoes[$chave];
    $tipo = $definicoes[$chave][3]??'text';
    $obrigatorio = ($definicoes[$chave][2]??true) ? 'required' : '';
    ?>
    <div class="campo <?= h($classe) ?>">
      <label for="cfg-<?= h($chave) ?>"><?= h($nome) ?></label>
      <?php if ($linhas): ?>
        <textarea id="cfg-<?= h($chave) ?>" name="<?= h($chave) ?>" maxlength="<?= $max ?>" rows="<?= $linhas ?>" <?= $obrigatorio ?>><?= h($valorEnviado($chave)) ?></textarea>
      <?php else: ?>
        <input id="cfg-<?= h($chave) ?>" type="<?= h($tipo) ?>" name="<?= h($chave) ?>" maxlength="<?= $max ?>" value="<?= h($valorEnviado($chave)) ?>" <?= $obrigatorio ?>>
      <?php endif; ?>
    </div>
    <?php
};
?>
<header class="editor-titulo">
  <h1>Página e marca</h1>
  <p>Escolha a seção que você quer editar.</p>
</header>
<nav class="secoes-editor" aria-label="Seções da página">
  <?php foreach ($secoes as $slug=>[$rotulo]): ?>
    <a href="?aba=pagina&amp;secao=<?= h($slug) ?>" <?= $slug===$secao?'aria-current="page"':'' ?>><?= h($rotulo) ?></a>
  <?php endforeach; ?>
</nav>
<section class="painel editor-painel" aria-labelledby="titulo-secao">
  <header class="editor-intro">
    <h2 id="titulo-secao"><?= h($tituloSecao) ?></h2>
    <p><?= h($descricaoSecao) ?></p>
  </header>
  <?php if ($erro !== ''): ?>
    <div class="alerta erro" role="alert">
      <p><?= h($erro) ?></p>
      <?php if ($reexibirEdicao && $secao==='marca' && ($_FILES['logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE): ?>
        <p>Selecione o arquivo da logo novamente antes de salvar.</p>
      <?php endif; ?>
      <?php if (http_response_code()===409): ?><a href="<?= h($urlSecao) ?>" target="_blank" rel="noopener">Abrir versão atual em nova aba</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($aviso !== ''): ?><p class="alerta ok" role="status"><?= h($aviso) ?></p><?php endif; ?>
  <form class="editor-form" method="post" action="<?= h($urlSecao) ?>" <?= $secao==='marca'?'enctype="multipart/form-data"':'' ?>>
    <?php formAcao('salvar-secao'); ?>
    <input type="hidden" name="versao" value="<?= h($versao) ?>">
    <?php if ($secao==='marca'): ?>
      <section class="editor-bloco">
        <h3>Logomarca</h3>
        <div class="editor-logomarca">
          <div class="editor-logo-atual"><img src="<?= h('../'.logoUrl($loja)) ?>" width="72" height="72" alt="Logo atual"></div>
          <div class="campo">
            <label for="cfg-logo">Nova logo</label>
            <input id="cfg-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-formatos">
            <p id="logo-formatos" class="muted">JPEG, PNG ou WebP de até 8 MB.</p>
            <?php if ($loja['logo']): ?><label class="check"><input type="checkbox" name="restaurarLogo" <?= $reexibirEdicao&&isset($_POST['restaurarLogo'])?'checked':'' ?>> Restaurar logo original</label><?php endif; ?>
          </div>
        </div>
      </section>
      <section class="editor-bloco">
        <h3>Identidade e contato</h3>
        <div class="campos-editor"><?php foreach (['marca','submarca','responsavel','instagram'] as $chave) $campo($chave); ?></div>
      </section>
      <section class="editor-bloco">
        <h3>Dados comerciais</h3>
        <div class="campos-editor">
          <?php $campo('razaoSocial'); $campo('cnpj'); $campo('endereco',3,'campo-largo'); ?>
        </div>
      </section>
    <?php elseif ($secao==='vitrine'): ?>
      <section class="editor-bloco">
        <h3>Abertura e destaques</h3>
        <div class="campos-editor">
          <?php $campo('titulo',0,'campo-largo'); $campo('chamada',3,'campo-largo'); ?>
          <?php foreach (['destaquePrincipal'=>'Destaque principal','destaqueSecundario'=>'Destaque secundário'] as $chave=>$rotulo): ?>
            <label><?= h($rotulo) ?><select name="<?= h($chave) ?>">
              <option value="">Automático</option>
              <?php foreach ($lista as $p): if (!$p['publicado']) continue; ?>
                <option value="<?= h($p['id']) ?>" <?= $valorEnviado($chave)===$p['id']?'selected':'' ?>><?= h($p['nome'].($p['modelo']?' · '.$p['modelo']:'')) ?></option>
              <?php endforeach; ?>
            </select></label>
          <?php endforeach; ?>
        </div>
      </section>
      <section class="editor-bloco">
        <h3>Catálogo e buscadores</h3>
        <div class="campos-editor"><?php $campo('tituloCatalogo',0,'campo-largo'); $campo('descCatalogo',3); $campo('descSite',3); ?></div>
      </section>
    <?php elseif ($secao==='encomendas'): ?>
      <section class="editor-bloco">
        <h3>Disponibilidade</h3>
        <div class="campos-editor">
          <label class="check editor-check"><input type="checkbox" name="recebendo" <?= ($reexibirEdicao?isset($_POST['recebendo']):$loja['recebendo'])?'checked':'' ?>> Receber novos pedidos</label>
          <?php $campo('prazo'); $campo('faixa',2,'campo-largo'); ?>
        </div>
      </section>
      <section class="editor-bloco">
        <h3>Como encomendar</h3>
        <?php $campo('tituloEncomendas'); ?>
        <ol class="etapas-editor">
          <?php for ($i=1;$i<=3;$i++): ?>
            <li>
              <span class="etapa-editor-rotulo">Etapa <?= $i ?></span>
              <div class="campos-editor"><?php $campo('etapa'.$i.'Titulo'); $campo('etapa'.$i.'Texto',4); ?></div>
            </li>
          <?php endfor; ?>
        </ol>
        <?php $campo('retirada',4); ?>
      </section>
    <?php elseif ($secao==='massa'): ?>
      <div class="editor-bloco campos-editor">
        <?php $campo('tituloMassa',0,'campo-largo'); $campo('sobreMassa',4,'campo-largo');
              $campo('ingredientes',5); $campo('alergenicos',5); $campo('consultaMassa',3,'campo-largo'); ?>
      </div>
    <?php else: ?>
      <div class="editor-bloco campos-editor">
        <div class="campo">
          <label for="cfg-whatsapp">Número do WhatsApp</label>
          <input id="cfg-whatsapp" type="tel" name="whatsapp" required maxlength="15" value="<?= h($valorEnviado('whatsapp')) ?>" aria-describedby="whatsapp-formato">
          <p id="whatsapp-formato" class="muted">Digite apenas números, com código do país e DDD.</p>
        </div>
        <div class="campo">
          <label for="cfg-senha-whatsapp">Senha atual do painel</label>
          <input id="cfg-senha-whatsapp" type="password" name="senha_atual" required maxlength="256" autocomplete="current-password" aria-describedby="seguranca-contato">
          <p id="seguranca-contato" class="muted">Confirme a senha para proteger o número de atendimento.</p>
        </div>
      </div>
    <?php endif; ?>
    <div class="editor-salvar">
      <button class="botao principal" type="submit"><?= h($botaoSalvar) ?></button>
    </div>
  </form>
</section>
