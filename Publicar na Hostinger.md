# Publicar a loja De Comer no hPanel

Use o pacote **Loja De Comer - Hostinger.zip** atualizado. A loja usa PHP e MariaDB ou MySQL. A vitrine e o painel foram testados localmente com MySQL 8.4, PHP 8.2 e HTTPS. A conferência no domínio da hospedagem faz parte da instalação.

## 1. Preparar o domínio e o banco

1. No hPanel, escolha o domínio ou subdomínio da loja. Para separar a sessão administrativa de outros sites, use um endereço dedicado.
2. Ative o certificado SSL e **Forçar HTTPS**. Verifique se a conta permite PHP, arquivos fora da pasta pública e acesso SSH. Na versão PHP do domínio, use 8.3 ou mais recente com suporte ativo. Habilite `pdo_mysql`, `gd` com WebP, `fileinfo` e `mbstring`. Para receber fotos de até 8 MB, configure `upload_max_filesize` em 8 MB ou mais, `post_max_size` em 10 MB ou mais e `memory_limit` em 192 MB ou mais.
3. Crie um banco vazio e um usuário exclusivo para a loja. Dê ao usuário acesso somente a esse banco. Anote os nomes completos com o prefixo exibido pelo hPanel e a senha. O host padrão do banco local é `localhost`.

[SSL e HTTPS no hPanel](https://www.hostinger.com/br/support/1583201-como-redirecionar-de-http-para-https-e-vice-versa-hostinger/) · [Criar banco no hPanel](https://www.hostinger.com/br/support/1583542-como-criar-um-novo-banco-de-dados-mysql-na-hostinger/) · [Extensões PHP no hPanel](https://www.hostinger.com/br/support/4667515-como-gerenciar-extensoes-e-opcoes-do-php-no-hpanel-hostinger/) · [Acesso SSH](https://www.hostinger.com/br/support/1583645-como-habilitar-o-acesso-ssh-no-hostinger/)

## 2. Colocar os arquivos nos locais corretos

Extraia o ZIP em uma pasta privada temporária. Ele contém duas pastas.

- Copie **o conteúdo de `site/`** para a pasta pública do domínio, de modo que `index.php`, `.htaccess`, `admin/` e `assets/` fiquem lado a lado.
- Coloque a pasta **`decomer-privado/`** ao lado de `public_html`. Ela deve ficar fora de todas as pastas públicas da conta. A loja procura essa pasta até dois níveis acima da pasta que contém o `index.php`.

Exemplo no domínio dedicado:

```text
dominio/
  public_html/
    index.php
    .htaccess
    admin/
    assets/
  decomer-privado/
    .htaccess
    configurar.php
    banco.exemplo.json
```

Se você já instalou o catálogo HTML anterior nesse endereço, retire da pasta pública o `index.html` antigo, `assets/catalogo.js`, `assets/configuracao.js` e o ZIP anterior. O Gerenciador de Arquivos permite mostrar arquivos ocultos para conferir o `.htaccess`. No plano que exige habilitar regras `.htaccess`, habilite-as antes do teste. [Gerenciador de Arquivos](https://www.hostinger.com/br/support/4548688-como-usar-o-gerenciador-de-arquivos-na-hostinger/) · [Regras .htaccess na Hostinger](https://www.hostinger.com/support/1583307-how-to-create-an-htaccess-file-at-hostinger/)

## 3. Ativar o administrador

No terminal SSH, entre na pasta `decomer-privado` desse domínio e prepare o arquivo do banco.

```sh
umask 077
mkdir -p dados
chmod 700 dados
cp -n banco.exemplo.json dados/banco.json
chmod 600 dados/banco.json
```

Edite `dados/banco.json` pelo Gerenciador de Arquivos, usando o nome do banco, o usuário e a senha criados no hPanel. Guarde as credenciais do banco nessa pasta privada. Depois rode:

```sh
php configurar.php --check
php configurar.php 'email-da-administradora@exemplo.com'
```

O primeiro comando confere extensões, conexão e escrita. No segundo, substitua o endereço de exemplo pelo e-mail da administradora. O comando cria as tabelas, carrega os 20 produtos na primeira instalação e gera a senha inicial. Entregue a senha à administradora por um canal privado. Ela entra em **`/admin/`** por HTTPS com o e-mail e a senha e pode escolher uma nova senha em **Minha conta**. A troca encerra todas as sessões do painel.

Se a instalação anterior usava um aplicativo autenticador, rode `php configurar.php --redefinir 'email-da-administradora@exemplo.com'` após atualizar os arquivos. Isso ativa o login com e-mail e senha no banco existente. Para recuperar uma senha esquecida depois da migração, rode `php configurar.php --redefinir` no SSH. O comando mantém o e-mail cadastrado e gera outra senha.

Para atualizar uma instalação existente para o checkout Pix, faça um backup privado do banco e rode `php configurar.php --atualizar` com os arquivos privados da nova versão antes de disponibilizar o checkout atualizado. O comando cria as tabelas de saldos e contatos e pode ser executado novamente. A configuração do banco, a senha do painel e os pedidos existentes são preservados.

## 4. Operar a loja

- **Produtos** cria, edita, oculta ou exclui produtos. Edite nome, modelo, descrição, preço, ordem, categoria e foto. As fotos são ajustadas à proporção original e ampliáveis na vitrine.
- **Categorias** cria, renomeia e exclui categorias vazias.
- **Página e marca** tem cinco seções com salvamento próprio. Use **Marca e identidade** para logo e dados comerciais, **Vitrine** para textos e destaques e **Encomendas** para disponibilidade e instruções de compra. Preencha os ingredientes e alergênicos na ficha única **Sobre a massa** assim que a Jozyanne confirmar as informações. Complete os dados públicos do fornecedor antes de divulgar a loja. [Decreto nº 7.962/2013, art. 2º](https://legis.senado.leg.br/norma/413161/publicacao/15762397)
- **Pedidos** mostra cada pedido enviado pela sacola, com nome, WhatsApp, itens e total. O cliente toca em **Enviar pedido no WhatsApp** e a conversa abre com a lista do pedido; o site não gera Pix. Combine o pagamento e a retirada na conversa, confirme o pedido no painel e registre os pagamentos depois de conferir o crédito. A busca aceita nome, WhatsApp ou código.
- **Pix** permite editar a chave por telefone e o titular após confirmar a senha do painel. A troca retira os códigos anteriores da consulta. Se a entrada ainda estiver pendente, use **Liberar Pix atualizado** no pedido para mostrar os dados novos. Um QR Code estático já salvo pode continuar aceito no banco até que a chave deixe de estar ativa.
- **WhatsApp da loja** fica em **Página e marca**. Para trocar o número de atendimento, confirme a senha do painel nessa seção.
- Para registrar uma parcela, confira no extrato o crédito, o valor e a referência da parcela quando o banco a mostrar. Informe o ID da transação para Pix. O painel impede usar o mesmo ID em mais de uma parcela ou pedido. [Orientação do Banco Central sobre consultar o extrato](https://bcb.gov.br/meubc/faqs/p/vitima-fez-um-pix-e-caiu-em-um-golpe)

## 5. Verificar antes de divulgar

- Abra a vitrine e o painel em HTTPS. Confira que a versão PHP do terminal e a do site têm as extensões indicadas.
- Faça uma encomenda de teste e confirme o código no painel. Confira a chave e o nome do titular no app do banco. Cancele o pedido de teste pelo painel.
- Confirme que a URL `/decomer-privado/dados/banco.json` e os caminhos de arquivos legados retornam 403 ou 404. O catálogo só deve ser servido pelo PHP. No hPanel, exclua a vitrine, o painel, `acompanhar.php` e `pedidos.php` de cache de página.
- Mantenha backups privados do banco e da pasta de dados. Agende diariamente o equivalente a `php /caminho/decomer-privado/configurar.php --manutencao` no cron do hPanel. A manutenção exclui os pedidos sem pagamento registrado 60 dias após a criação. Registre cada crédito recebido antes desse prazo. Ela também retira o nome e o WhatsApp dos registros após 90 dias. [Cron no hPanel](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/)
