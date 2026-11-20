(() => {
  'use strict';

  const maximo = 99;
  const reais = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const dinheiro = centavos => reais.format(centavos / 100);

  function limpar(dados, produtos) {
    if (!dados || typeof dados !== 'object' || Array.isArray(dados)) return new Map();
    return new Map(Object.entries(dados).filter(([id, quantidade]) =>
      produtos.has(id) && Number.isInteger(quantidade) && quantidade > 0 && quantidade <= maximo));
  }

  function resumir(carrinho, produtos) {
    const itens = [];
    let quantidade = 0;
    let total = 0;
    for (const [id, unidades] of carrinho) {
      const produto = produtos.get(id);
      if (!produto || !Number.isInteger(unidades) || unidades < 1 || unidades > maximo) continue;
      const subtotal = produto.centavos * unidades;
      itens.push({ produto, unidades, subtotal });
      quantidade += unidades;
      total += subtotal;
    }
    const sinal = Math.ceil(total / 2);
    return { itens, quantidade, total, sinal, restante: total - sinal };
  }

  function linkWhatsApp(telefone, texto) {
    if (!/^[1-9]\d{9,14}$/.test(telefone)) throw new Error('Número de WhatsApp inválido.');
    return 'https://wa.me/' + telefone + '?text=' + encodeURIComponent(texto);
  }

  function validarNome(texto) {
    if (/[\p{C}\p{Zl}\p{Zp}]/u.test(texto)) return null;
    const nome = texto.normalize('NFC').trim().replace(/ +/g, ' ');
    if (nome.length > 80 || (nome && !/^[\p{L}\p{M} .’'-]+$/u.test(nome))) return null;
    return nome;
  }

  async function requisitar(acao, dados) {
    const resposta = await fetch('pedidos.php?acao=' + acao, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-DeComer': 'pedido' },
      body: JSON.stringify(dados), cache: 'no-store', credentials: 'omit', redirect: 'error',
      signal: typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(12000) : undefined,
    });
    const corpo = await resposta.json();
    if (!resposta.ok) {
      const erro = new Error(corpo.erro || 'Serviço indisponível.');
      erro.status = resposta.status;
      erro.tentarEm = Number(resposta.headers.get('Retry-After')) || 0;
      throw erro;
    }
    return corpo;
  }

  function linkPedido(token) {
    const url = new URL('acompanhar.php', location.href);
    url.hash = 'pedido=' + token;
    return url.href;
  }

  const dataPedido = new Intl.DateTimeFormat('pt-BR', { timeZone: 'America/Sao_Paulo', day: '2-digit', month: '2-digit', year: 'numeric' });
  const horaPedido = new Intl.DateTimeFormat('pt-BR', { timeZone: 'America/Sao_Paulo', hour: '2-digit', minute: '2-digit' });

  // Texto que o cliente envia à loja pelo WhatsApp: quem pede, o quê e quanto. Não afirma pagamento:
  // o site não confirma Pix, e a loja combina o pagamento na conversa. O código do pedido fica só no painel.
  function mensagemPedido(dados, { abertura = 'Olá! Quero fazer este pedido.', fechamento = 'Como combinamos o pagamento e a retirada?' } = {}) {
    const nome = (typeof dados.nome === 'string' && dados.nome.trim()) || 'Cliente';
    const itens = (Array.isArray(dados.itens) ? dados.itens : []).map(item =>
      '• ' + item.quantidade + '× ' + item.nome + (item.modelo ? ' · ' + item.modelo : '') + ' — ' + dinheiro(item.subtotal));
    const linhas = [abertura, '', '*Pedido de ' + nome + '*', ...itens, 'Total: ' + dinheiro(dados.total)];
    if (Number.isInteger(dados.criado) && dados.criado > 0) {
      const quando = new Date(dados.criado * 1000);
      linhas.push('Feito em ' + dataPedido.format(quando) + ' às ' + horaPedido.format(quando));
    }
    if (fechamento) linhas.push('', fechamento);
    return linhas.join('\n');
  }

  const pedido = Object.freeze({ limpar, resumir, linkWhatsApp, validarNome, dinheiro, maximo, requisitar, linkPedido, mensagemPedido });
  if (typeof module === 'object' && module.exports) module.exports = pedido;
  else window.PedidoDeComer = pedido;
})();
