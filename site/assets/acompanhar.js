(() => {
  'use strict';

  const { dinheiro, linkWhatsApp, linkPedido, requisitar } = window.PedidoDeComer;
  const el = id => document.getElementById(id);
  const chave = 'decomer-pedido-v1:' + new URL('./', location.href).pathname;
  const tokenValido = token => typeof token === 'string' && /^[a-f0-9]{32}(?:[a-f0-9]{16})?$/.test(token);
  const recuperacao = el('recuperar-pedido');
  const detalhe = el('pedido-detalhe');
  const atualizar = el('atualizar-pedido');
  const repetir = el('consulta-repetir');
  const canvas = el('qr-pix');
  const pixTexto = el('pix-texto');
  const copiarPix = el('copiar-pix');
  const parcelas = [...document.querySelectorAll('[data-parcela]')];
  const estadoPedido = {
    solicitado: ['Aguardando confirmação', 'Combine a data e a disponibilidade com a loja. O Pix da entrada aparece aqui depois da confirmação.'],
    confirmado: ['Pague a entrada', 'A loja confirmou a encomenda.'],
    sinal_recebido: ['Pague o saldo', 'A loja confirmou a entrada. Pague o restante na data combinada para a retirada ou o envio.'],
    quitado: ['Pagamento completo', 'A loja confirmou as duas parcelas. Siga o combinado para retirar ou receber a encomenda.'],
    cancelado: ['Pedido cancelado', 'Fale com a loja se tiver alguma dúvida sobre este pedido.'],
  };
  let acesso = null;
  let pendente = null;
  let ultimo = null;
  let copiando = false;
  let proximaConsulta = 0;

  function guardarToken(token) {
    try {
      if (token) sessionStorage.setItem(chave, JSON.stringify({ token }));
      else sessionStorage.removeItem(chave);
      return true;
    } catch {
      return false;
    }
  }

  function limparFragmento() {
    if (new URLSearchParams(location.hash.slice(1)).has('pedido')) {
      history.replaceState(null, '', location.pathname + location.search);
    }
  }

  function lerToken() {
    try {
      return JSON.parse(sessionStorage.getItem(chave))?.token;
    } catch {
      return null;
    }
  }

  function aviso(texto) {
    el('consulta-aviso').textContent = texto;
    el('consulta-aviso').hidden = !texto;
  }

  function limparPix() {
    el('pagamento-ativo').hidden = true;
    el('valor-pix').hidden = true;
    el('pix-manual').hidden = true;
    el('pix-feedback').textContent = '';
    pixTexto.value = '';
    canvas.hidden = el('qr-ajuda').hidden = true;
    canvas.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height);
  }

  function desenharQr(texto) {
    if (typeof window.qrcode !== 'function') return;
    const qr = window.qrcode(0, 'M');
    qr.addData(texto, 'Byte');
    qr.make();
    const n = qr.getModuleCount();
    const escala = Math.floor(226 / (n + 8));
    canvas.width = canvas.height = (n + 8) * escala;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000';
    for (let y = 0; y < n; y++) {
      for (let x = 0; x < n; x++) {
        if (qr.isDark(y, x)) ctx.fillRect((x + 4) * escala, (y + 4) * escala, escala, escala);
      }
    }
    canvas.hidden = el('qr-ajuda').hidden = false;
  }

  function pixAtual(p) {
    if (p?.estado === 'confirmado') return p.pix_entrada;
    if (p?.estado === 'sinal_recebido') return p.pix_saldo;
    return undefined;
  }

  function estadoParcela(p, entrada, valor) {
    if (p.estado === 'cancelado') return 'Cancelada';
    if (valor === 0) return 'Sem valor a pagar';
    if (p.estado === 'quitado' || (entrada && p.estado === 'sinal_recebido')) return 'Pago';
    if (entrada && p.estado === 'solicitado') return 'Após confirmação do pedido';
    if (!entrada && ['solicitado', 'confirmado'].includes(p.estado)) return 'Após confirmação da entrada';
    return pixAtual(p) ? 'Disponível para pagar' : 'Consulte a loja';
  }

  function renderizar(p) {
    const estadoAnterior = ultimo?.estado;
    if (ultimo?.codigo !== p.codigo && Array.isArray(p.itens)) {
      const itens = p.itens.map(item => {
        const linha = document.createElement('li');
        const produto = document.createElement('span');
        const valor = document.createElement('strong');
        produto.textContent = item.quantidade + ' × ' + item.nome + (item.modelo ? ' · ' + item.modelo : '');
        valor.textContent = dinheiro(item.subtotal);
        linha.append(produto, valor);
        return linha;
      });
      el('itens-pedido').replaceChildren(...itens);
    }
    const pix = pixAtual(p);
    if (estadoAnterior !== p.estado || pixTexto.value !== (pix?.copia_cola || '')) limparPix();
    ultimo = p;
    const [etapa, orientacao] = estadoPedido[p.estado];
    el('codigo-pedido').textContent = p.codigo;
    el('referencia-pedido').hidden = false;
    el('etapa-pedido').textContent = etapa;
    el('orientacao-pedido').textContent = (!pix && ['confirmado', 'sinal_recebido'].includes(p.estado))
      ? 'Combine o pagamento desta parcela com a loja.' : orientacao;
    if (estadoAnterior !== p.estado) el('status-pedido').textContent = etapa;
    el('pedido-total').textContent = dinheiro(p.total);
    for (const parcela of parcelas) {
      const entrada = parcela.dataset.parcela === 'entrada';
      const valor = entrada ? p.sinal : p.restante;
      parcela.querySelector('dd').textContent = dinheiro(valor);
      parcela.querySelector('small').textContent = estadoParcela(p, entrada, valor);
      parcela.classList.toggle('parcela-disponivel', !!pix && (entrada ? p.estado === 'confirmado' : p.estado === 'sinal_recebido'));
    }
    const whats = el('whatsapp-loja');
    const texto = p.estado === 'solicitado'
      ? 'Olá! Quero combinar a disponibilidade e a data da encomenda ' + p.codigo + '.'
      : 'Olá! Preciso de ajuda com a encomenda ' + p.codigo + '.';
    whats.href = linkWhatsApp(p.whatsapp, texto);
    whats.textContent = p.estado === 'solicitado' ? 'Combinar pelo WhatsApp' : 'Falar com a loja';
    whats.className = pix || p.estado !== 'solicitado' ? 'acao-secundaria' : 'botao botao-verde';
    whats.hidden = false;
    if (pix) {
      el('pix-titular').textContent = pix.recebedor;
      el('valor-pix').textContent = dinheiro(p.estado === 'confirmado' ? p.sinal : p.restante);
      el('valor-pix').hidden = false;
      if (pixTexto.value !== pix.copia_cola) {
        pixTexto.value = pix.copia_cola;
        desenharQr(pix.copia_cola);
      }
      el('pagamento-ativo').hidden = false;
    }
    el('codigo-retorno').textContent = acesso.toUpperCase().match(/.{1,8}/g).join('-');
    detalhe.hidden = false;
    recuperacao.hidden = true;
    repetir.hidden = true;
    aviso('');
  }

  function mostrarConsulta(texto = '') {
    limparPix();
    ultimo = null;
    el('itens-pedido').replaceChildren();
    detalhe.hidden = el('referencia-pedido').hidden = true;
    recuperacao.hidden = false;
    repetir.hidden = true;
    aviso(texto);
  }

  async function consultar(token) {
    try {
      const pedido = await requisitar('consultar', { token });
      if (acesso !== token || document.hidden) return null;
      guardarToken(token);
      renderizar(pedido);
      return pedido;
    } catch (erro) {
      if (acesso !== token || document.hidden) return null;
      if (erro.status === 404) {
        acesso = null;
        guardarToken(null);
        limparFragmento();
        mostrarConsulta(erro.message);
      } else {
        if (erro.status === 429) proximaConsulta = Date.now() + (erro.tentarEm || 60) * 1000;
        limparPix();
        aviso(erro.status ? erro.message : 'Não foi possível atualizar a situação. Tente novamente.');
        repetir.hidden = !detalhe.hidden;
      }
      return null;
    }
  }

  function consultarAtual() {
    if (!acesso || document.hidden) return Promise.resolve(null);
    if (pendente?.token === acesso) return pendente.promessa;
    if (Date.now() < proximaConsulta) return Promise.resolve(null);
    const requisicao = { token: acesso };
    pendente = requisicao;
    atualizar.disabled = repetir.disabled = true;
    requisicao.promessa = consultar(requisicao.token).finally(() => {
      if (pendente !== requisicao) return;
      pendente = null;
      atualizar.disabled = repetir.disabled = false;
    });
    return requisicao.promessa;
  }

  async function abrir(token) {
    acesso = token;
    // Preserva a recuperação na mesma aba se a rede cair antes da resposta.
    if (guardarToken(token)) limparFragmento();
    mostrarConsulta();
    recuperacao.hidden = true;
    el('link-texto').value = el('acesso-feedback').textContent = '';
    el('link-manual').hidden = true;
    el('status-pedido').textContent = 'Consultando pedido…';
    if (Date.now() < proximaConsulta) {
      aviso('Aguarde alguns minutos antes de consultar de novo.');
      repetir.hidden = false;
      return;
    }
    const pedido = await consultarAtual();
    if (pedido && document.activeElement?.closest('#recuperar-pedido')) el('etapa-pedido').focus();
  }

  async function copiar(texto, feedback, mensagem, campo, painel) {
    try {
      await navigator.clipboard.writeText(texto);
      feedback.textContent = mensagem;
    } catch {
      if (painel) painel.hidden = false;
      if (campo instanceof HTMLInputElement) {
        campo.value = texto;
        campo.focus();
        campo.select();
      } else {
        const selecao = window.getSelection();
        const intervalo = document.createRange();
        intervalo.selectNodeContents(campo);
        selecao.removeAllRanges();
        selecao.addRange(intervalo);
      }
      feedback.textContent = 'Use a opção de copiar do seu aparelho.';
    }
  }

  el('form-consulta').addEventListener('submit', async evento => {
    evento.preventDefault();
    const campo = el('codigo-acesso');
    const token = campo.value.trim().replace(/[\s-]/g, '').toLowerCase();
    const erro = el('codigo-erro');
    erro.hidden = tokenValido(token);
    if (!erro.hidden) {
      erro.textContent = 'Cole o código de acesso que você guardou.';
      campo.setAttribute('aria-invalid', 'true');
      campo.focus();
      return;
    }
    campo.removeAttribute('aria-invalid');
    const botao = evento.currentTarget.querySelector('button');
    botao.disabled = true;
    try {
      await abrir(token);
      if (!detalhe.hidden) campo.value = '';
    } finally {
      botao.disabled = false;
    }
  });

  el('codigo-acesso').addEventListener('input', evento => {
    evento.currentTarget.removeAttribute('aria-invalid');
    el('codigo-erro').hidden = true;
  });

  atualizar.addEventListener('click', async () => {
    if (await consultarAtual()) el('status-pedido').textContent = 'Situação do pedido atualizada.';
  });
  repetir.addEventListener('click', async () => {
    if (await consultarAtual()) el('etapa-pedido').focus();
  });

  copiarPix.addEventListener('click', async () => {
    const token = acesso;
    const texto = pixTexto.value;
    if (!token || !texto || copiando) return;
    copiando = true;
    const feedback = el('pix-feedback');
    feedback.textContent = 'Conferindo a parcela…';
    const valido = consultarAtual().then(p => acesso === token && pixAtual(p)?.copia_cola === texto);
    try {
      if (typeof ClipboardItem === 'function' && navigator.clipboard?.write) {
        const conteudo = valido.then(ok => {
          if (!ok) throw new Error('A parcela mudou.');
          return new Blob([texto], { type: 'text/plain' });
        });
        conteudo.catch(() => {});
        try {
          await navigator.clipboard.write([new ClipboardItem({ 'text/plain': conteudo })]);
          if (acesso === token && pixTexto.value === texto) feedback.textContent = 'Pix copiado. Agora, cole no app do seu banco.';
          return;
        } catch {
          // Alguns navegadores exigem cópia manual após a conferência.
        }
      }
      if (await valido && acesso === token && pixTexto.value === texto) {
        await copiar(texto, feedback, 'Pix copiado. Agora, cole no app do seu banco.', pixTexto, el('pix-manual'));
      } else if (acesso === token && pixTexto.value) {
        feedback.textContent = 'A parcela mudou. Confira a situação e copie o Pix disponível.';
      }
    } finally {
      copiando = false;
    }
  });

  el('copiar-link').addEventListener('click', () => {
    if (acesso) copiar(linkPedido(acesso), el('acesso-feedback'), 'Link copiado. Guarde onde você possa encontrá-lo.', el('link-texto'), el('link-manual'));
  });
  el('copiar-codigo').addEventListener('click', () => {
    const codigo = el('codigo-retorno');
    if (acesso) copiar(codigo.textContent, el('acesso-feedback'), 'Código de acesso copiado.', codigo);
  });
  el('encerrar-consulta').addEventListener('click', () => {
    acesso = null;
    guardarToken(null);
    limparFragmento();
    el('codigo-retorno').textContent = el('codigo-pedido').textContent = '';
    el('link-texto').value = el('acesso-feedback').textContent = '';
    el('link-manual').hidden = true;
    el('guardar-titulo').parentElement.querySelector('details').open = false;
    mostrarConsulta();
    el('codigo-acesso').focus();
  });

  function iniciar() {
    const fragmento = new URLSearchParams(location.hash.slice(1));
    const peloLink = fragmento.has('pedido');
    const token = peloLink ? fragmento.get('pedido') : lerToken();
    if (tokenValido(token)) {
      abrir(token);
    } else {
      if (peloLink) {
        acesso = null;
        guardarToken(null);
        limparFragmento();
      }
      mostrarConsulta(peloLink ? 'Link de acesso inválido. Use o código que você guardou.' : '');
    }
  }

  iniciar();
  window.addEventListener('hashchange', iniciar);
  window.addEventListener('pagehide', limparPix);
  window.addEventListener('pageshow', evento => {
    if (evento.persisted) consultarAtual();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) limparPix();
    else consultarAtual();
  });
  window.setInterval(() => {
    if (!['quitado', 'cancelado'].includes(ultimo?.estado)) consultarAtual();
  }, 60000);
})();
