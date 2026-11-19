(() => {
  'use strict';

  window.iniciarDeComer = function iniciarDeComer({ catalogo, loja }) {
    const { limpar, resumir, validarNome, dinheiro, maximo, requisitar, linkPedido } = window.PedidoDeComer;
    const buscar = seletor => document.querySelector(seletor);
    const produtos = new Map(catalogo.map(produto => [produto.id, produto]));
    const chaveSacola = 'decomer-natal-2026-sacola';
    const sacola = buscar('#sacola');
    const lista = buscar('#sacola-itens');
    const linha = buscar('#linha-sacola');
    const abreSacola = [...document.querySelectorAll('[data-abrir-sacola]')];
    const barra = buscar('#barra-sacola');
    const cartoes = [...document.querySelectorAll('.produto')];
    const adicionar = [...document.querySelectorAll('[data-adicionar]')];
    const nome = buscar('#comprador');
    const erroNome = buscar('#nome-erro');
    const telefone = buscar('#telefone-comprador');
    const erroTelefone = buscar('#telefone-erro');
    const confirmar = buscar('#pedido button[type="submit"]');
    const erroPedido = buscar('#erro-pedido');
    const dialogoFoto = buscar('#foto-dialogo');
    const fotoGrande = buscar('#foto-grande');
    const status = buscar('#status-sacola');
    const resultados = buscar('#resultado');
    const filtros = buscar('.filtros');

    let carrinho = lerSacola();
    let resumo;
    let retornoSacola;
    let retornoFoto;
    let tentativa;
    let enviando = false;

    function lerSacola() {
      try {
        return limpar(JSON.parse(localStorage.getItem(chaveSacola)), produtos);
      } catch {
        return new Map();
      }
    }

    function guardarSacola() {
      try {
        if (carrinho.size) localStorage.setItem(chaveSacola, JSON.stringify(Object.fromEntries(carrinho)));
        else localStorage.removeItem(chaveSacola);
      } catch {
        // A sacola também funciona na sessão quando o navegador bloqueia o armazenamento.
      }
    }

    function mostrarErro(texto) {
      erroPedido.textContent = texto;
      erroPedido.hidden = !texto;
    }

    function quantidadePorExtenso(quantidade) {
      return quantidade === 1 ? '1 item' : quantidade + ' itens';
    }

    function identificar(produto) {
      return produto.modelo ? produto.nome + ', ' + produto.modelo : produto.nome;
    }

    function atualizarSacola(foco) {
      resumo = resumir(carrinho, produtos);
      const { quantidade, itens, total, sinal, restante } = resumo;
      const temItens = quantidade > 0;
      const descr = quantidadePorExtenso(quantidade);
      buscar('[data-contagem]').textContent = quantidade;
      buscar('#barra-unidades').textContent = '(' + descr + ')';
      buscar('#barra-total').textContent = dinheiro(total);
      buscar('#sacola-unidades').textContent = descr;
      buscar('#sacola-vazia').hidden = temItens;
      buscar('#sacola-com-itens').hidden = !temItens;
      barra.hidden = !temItens;
      confirmar.disabled = !temItens || !loja.recebendo || enviando;
      abreSacola[0].setAttribute('aria-label', 'Sacola, ' + descr);
      abreSacola[1].setAttribute('aria-label', 'Ver sacola, ' + descr + ', ' + dinheiro(total));
      for (const botao of adicionar) {
        const unidades = carrinho.get(botao.dataset.adicionar) ?? 0;
        const produto = produtos.get(botao.dataset.adicionar);
        const numero = botao.querySelector('small');
        numero.hidden = unidades === 0;
        numero.textContent = unidades;
        botao.toggleAttribute('data-tem-itens', unidades > 0);
        botao.disabled = !loja.recebendo || unidades >= maximo;
        const contagem = unidades ? ' · ' + unidades + ' na sacola' : '';
        botao.setAttribute('aria-label', 'Adicionar ' + identificar(produto) + contagem);
      }
      const fragmento = document.createDocumentFragment();
      for (const { produto, unidades, subtotal } of itens) {
        const item = linha.content.firstElementChild.cloneNode(true);
        item.dataset.id = produto.id;
        item.querySelector('img').src = produto.fotoPequena;
        item.querySelector('.item-nome').textContent = produto.nome;
        const modelo = item.querySelector('.item-modelo');
        modelo.textContent = produto.modelo;
        modelo.hidden = !produto.modelo;
        const menos = item.querySelector('[data-ajustar="-1"]');
        menos.setAttribute('aria-label', (unidades === 1 ? 'Remover ' : 'Diminuir quantidade de ') + identificar(produto));
        const mais = item.querySelector('[data-ajustar="1"]');
        mais.setAttribute('aria-label', 'Aumentar quantidade de ' + identificar(produto));
        mais.disabled = unidades >= maximo;
        item.querySelector('.item-quantidade').textContent = unidades;
        item.querySelector('.item-subtotal').textContent = dinheiro(subtotal);
        fragmento.append(item);
      }
      lista.replaceChildren(fragmento);
      buscar('#total').textContent = dinheiro(total);
      buscar('#sinal').textContent = dinheiro(sinal);
      buscar('#restante').textContent = dinheiro(restante);
      if (foco) {
        const item = [...lista.children].find(el => el.dataset.id === foco.id);
        const controle = item?.querySelector('[data-ajustar="' + foco.passo + '"]:not(:disabled)');
        (controle || item?.querySelector('button') || lista.querySelector('button') || sacola.querySelector('.fechar')).focus();
      }
    }

    function ajustar(id, passo, peloCarrinho = false) {
      const produto = produtos.get(id);
      if (!produto) return;
      const atual = carrinho.get(id) ?? 0;
      const novo = atual + passo;
      if (novo < 0 || novo > maximo) return;
      if (novo) carrinho.set(id, novo);
      else carrinho.delete(id);
      guardarSacola();
      atualizarSacola(peloCarrinho ? { id, passo } : null);
      status.textContent = novo
        ? identificar(produto) + ': ' + quantidadePorExtenso(novo) + ' na sacola.'
        : identificar(produto) + ' removido da sacola.';
    }

    buscar('#catalogo-pausado').hidden = loja.recebendo;
    filtros.hidden = false;
    filtros.addEventListener('click', evento => {
      const botao = evento.target.closest('[data-filtro]');
      if (!botao) return;
      const filtro = botao.dataset.filtro;
      for (const opcao of filtros.querySelectorAll('[data-filtro]')) {
        opcao.setAttribute('aria-pressed', String(opcao === botao));
      }
      let visiveis = 0;
      for (const cartao of cartoes) {
        cartao.hidden = filtro !== 'todos' && cartao.dataset.categoria !== filtro;
        if (!cartao.hidden) visiveis += 1;
      }
      resultados.textContent = visiveis + (visiveis === 1 ? ' produto' : ' produtos');
    });

    for (const botao of abreSacola) {
      botao.disabled = false;
      botao.addEventListener('click', () => {
        retornoSacola = botao;
        sacola.showModal();
      });
    }
    for (const botao of adicionar) {
      const produto = produtos.get(botao.dataset.adicionar);
      botao.closest('.produto').querySelector('.preco').textContent = dinheiro(produto.centavos);
      botao.disabled = false;
      botao.addEventListener('click', () => ajustar(botao.dataset.adicionar, 1));
    }
    lista.addEventListener('click', evento => {
      const botao = evento.target.closest('[data-ajustar]');
      if (botao) ajustar(botao.closest('[data-id]').dataset.id, Number(botao.dataset.ajustar), true);
    });

    function validarTelefone(texto) {
      if (!/^[0-9+(). -]{1,24}$/.test(texto)) return '';
      const digitos = texto.replace(/[^0-9]/g, '');
      const numero = [10, 11].includes(digitos.length) ? '55' + digitos : digitos;
      return /^55[1-9][0-9](?:9[0-9]{8}|[2-5][0-9]{7})$/.test(numero) ? numero : '';
    }

    const campos = [[nome, erroNome], [telefone, erroTelefone]];
    for (const [campo, aviso] of campos) {
      campo.addEventListener('input', () => {
        campo.removeAttribute('aria-invalid');
        aviso.hidden = true;
      });
    }

    buscar('#pedido').addEventListener('submit', async evento => {
      evento.preventDefault();
      if (!resumo.quantidade || enviando || !loja.recebendo) return;
      const comprador = validarNome(nome.value);
      const contato = validarTelefone(telefone.value);
      const invalidos = [
        [nome, erroNome, comprador ? '' : 'Informe seu nome usando letras e sinais comuns de nome.'],
        [telefone, erroTelefone, contato ? '' : 'Informe um WhatsApp válido com DDD.'],
      ].filter(([, , texto]) => texto);
      for (const [campo, aviso, texto] of invalidos) {
        campo.setAttribute('aria-invalid', 'true');
        aviso.textContent = texto;
        aviso.hidden = false;
      }
      mostrarErro('');
      if (invalidos.length) {
        invalidos[0][0].focus();
        return;
      }
      enviando = true;
      confirmar.disabled = true;
      confirmar.textContent = 'Enviando…';
      try {
        const itens = Object.fromEntries(carrinho);
        const assinatura = JSON.stringify([Object.entries(itens).sort(), comprador, contato, loja.revisao]);
        if (tentativa?.assinatura !== assinatura) {
          const chave = Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
          tentativa = { assinatura, chave };
        }
        const requisicao = tentativa.chave;
        await requisitar('criar', { itens, nome: comprador, telefone: contato, revisao: loja.revisao, requisicao });
        for (const [id, unidades] of Object.entries(itens)) {
          if (carrinho.get(id) === unidades) carrinho.delete(id);
        }
        guardarSacola();
        nome.value = telefone.value = '';
        tentativa = null;
        enviando = false;
        confirmar.textContent = 'Ver Pix da entrada';
        atualizarSacola();
        sacola.close();
        location.assign(linkPedido(requisicao));
      } catch (erro) {
        mostrarErro(erro.status ? erro.message : 'Não foi possível enviar o pedido. Tente novamente.');
        enviando = false;
        confirmar.textContent = 'Ver Pix da entrada';
        atualizarSacola();
      }
    });

    for (const link of document.querySelectorAll('[data-foto]')) {
      link.addEventListener('click', evento => {
        if (evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
        evento.preventDefault();
        const produto = produtos.get(link.dataset.foto);
        if (!produto) return;
        retornoFoto = link;
        buscar('#foto-titulo').textContent = produto.nome;
        buscar('#foto-modelo').textContent = produto.modelo;
        buscar('#foto-modelo').hidden = !produto.modelo;
        fotoGrande.alt = identificar(produto);
        fotoGrande.width = produto.grande.w;
        fotoGrande.height = produto.grande.h;
        fotoGrande.src = produto.fotoGrande;
        dialogoFoto.showModal();
      });
    }

    for (const dialogo of [sacola, dialogoFoto]) {
      dialogo.addEventListener('click', evento => {
        if (evento.target === dialogo || evento.target.closest('[data-fechar]')) dialogo.close();
      });
    }
    sacola.addEventListener('close', () => {
      mostrarErro('');
      (retornoSacola?.closest('[hidden]') ? abreSacola[0] : retornoSacola)?.focus();
    });
    dialogoFoto.addEventListener('close', () => {
      fotoGrande.removeAttribute('src');
      retornoFoto?.focus();
    });

    window.addEventListener('storage', evento => {
      if (evento.key === chaveSacola || evento.key === null) {
        carrinho = lerSacola();
        atualizarSacola();
      }
    });
    atualizarSacola();
  };
})();
