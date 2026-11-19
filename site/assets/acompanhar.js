(() => {
  'use strict';

  const { dinheiro, linkWhatsApp, requisitar, mensagemPedido } = window.PedidoDeComer;
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
    confirmado: ['Pague 50% agora', 'Este Pix cobre apenas a entrada. Depois de pagar, toque em “Já paguei” para enviar o pedido à loja pelo WhatsApp.'],
    sinal_recebido: ['Entrada confirmada', 'A loja conferiu o pagamento da entrada. Combine a retirada pelo WhatsApp.'],
    quitado: ['Pagamento completo', 'A loja confirmou o pagamento completo. Siga o combinado para retirar ou receber a encomenda.'],
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
    return p?.estado === 'confirmado' ? p.pix_entrada : undefined;
  }

  function estadoParcela(p, entrada, valor) {
    if (p.estado === 'cancelado') return 'Cancelada';
    if (valor === 0) return 'Sem valor a pagar';
    if (p.estado === 'quitado' || (entrada && p.estado === 'sinal_recebido')) return 'Pago';
    if (!entrada) return 'Acerte com a loja na retirada';
    return pixAtual(p) ? 'Pague com Pix' : 'Consulte a loja';
  }

  function renderizar(p) {
    const estadoMudou = ultimo?.estado !== p.estado || ultimo?.entrada_expirada !== p.entrada_expirada;
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
    if (estadoMudou || pixTexto.value !== (pix?.copia_cola || '')) limparPix();
    ultimo = p;
    const [etapa, orientacao] = p.entrada_expirada
      ? ['Prazo do Pix encerrado', 'Fale com a loja se você já pagou ou ainda quiser fazer a encomenda.']
      : estadoPedido[p.estado];
    el('etapa-pedido').textContent = etapa;
    el('orientacao-pedido').textContent = (!pix && p.estado === 'confirmado' && !p.entrada_expirada)
      ? 'O Pix da entrada está indisponível. Fale com a loja antes de pagar.' : orientacao;
    if (estadoMudou) el('status-pedido').textContent = etapa;
    el('pedido-total').textContent = dinheiro(p.total);
    for (const parcela of parcelas) {
      const entrada = parcela.dataset.parcela === 'entrada';
      const valor = entrada ? p.sinal : p.restante;
      parcela.querySelector('dd').textContent = dinheiro(valor);
      parcela.querySelector('small').textContent = estadoParcela(p, entrada, valor);
      parcela.classList.toggle('parcela-disponivel', entrada && !!pix);
    }
    const encerrado = p.estado === 'quitado' || p.estado === 'cancelado';
    const lembrete = el('lembrete-saldo');
    lembrete.hidden = encerrado || p.restante === 0;
    lembrete.textContent = 'Os outros 50% (' + dinheiro(p.restante) + ') são pagos à loja na retirada.';
    const abertura = p.entrada_expirada ? 'Olá! O prazo do Pix deste pedido encerrou e preciso de ajuda.'
      : p.estado === 'sinal_recebido' ? 'Olá! Quero combinar a retirada deste pedido.'
      : encerrado ? 'Olá! Preciso de ajuda com este pedido.'
      : 'Olá! Tenho uma dúvida sobre este pedido.';
    el('whatsapp-loja').href = linkWhatsApp(p.whatsapp, mensagemPedido(p, { abertura }));
    el('whatsapp-pago').href = linkWhatsApp(p.whatsapp, mensagemPedido(p, { pago: true }));
    atualizar.hidden = !pix;
    if (pix) {
      el('pix-titular').textContent = pix.recebedor;
      el('valor-pix').textContent = dinheiro(p.sinal);
      el('valor-pix').hidden = false;
      if (pixTexto.value !== pix.copia_cola) {
        pixTexto.value = pix.copia_cola;
        desenharQr(pix.copia_cola);
      }
      el('pagamento-ativo').hidden = false;
    }
    detalhe.hidden = false;
    recuperacao.hidden = true;
    repetir.hidden = true;
    aviso('');
  }

  function mostrarConsulta(texto = '') {
    limparPix();
    ultimo = null;
    el('itens-pedido').replaceChildren();
    detalhe.hidden = true;
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
        repetir.hidden = false;
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
    el('status-pedido').textContent = 'Consultando pedido…';
    if (Date.now() < proximaConsulta) {
      aviso('Aguarde alguns minutos antes de consultar de novo.');
      repetir.hidden = false;
      return;
    }
    await consultarAtual();
  }

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
    feedback.textContent = 'Conferindo o Pix da entrada…';
    const valido = consultarAtual().then(p => acesso === token && pixAtual(p)?.copia_cola === texto);
    try {
      if (typeof ClipboardItem === 'function' && navigator.clipboard?.write) {
        const conteudo = valido.then(ok => {
          if (!ok) throw new Error('O Pix da entrada mudou.');
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
        try {
          await navigator.clipboard.writeText(texto);
          feedback.textContent = 'Pix copiado. Agora, cole no app do seu banco.';
        } catch {
          el('pix-manual').hidden = false;
          pixTexto.focus();
          pixTexto.select();
          feedback.textContent = 'Use a opção de copiar do seu aparelho.';
        }
      } else if (acesso === token && pixTexto.value) {
        feedback.textContent = 'O Pix da entrada mudou. Confira o valor e tente copiar novamente.';
      }
    } finally {
      copiando = false;
    }
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
      mostrarConsulta(peloLink ? 'Não foi possível abrir esta encomenda. Fale com a loja se precisar de ajuda.' : '');
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
