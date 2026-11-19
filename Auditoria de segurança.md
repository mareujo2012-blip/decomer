# Relatório de segurança da loja De Comer

**Data** 13/11/2026  
**Escopo** Vitrine, administração, catálogo, carrinho, pedidos por WhatsApp, Pix, imagens e pacote para a hospedagem.  
**Ambiente verificado** Apache 2.4, PHP-FPM 8.2 e MySQL 8.4, com HTTPS local. Chromium em 320, 390, 768 e 1440 px.  
**Método** Revisão do código, segunda revisão independente, testes HTTP, teste com oito requisições concorrentes e validação dos fluxos em navegador. A seleção de controles seguiu as referências abaixo. [OWASP ASVS 5](https://asvs.owasp.org/) · [Autorização de transações](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html) · [Uploads](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)

## 1. Resultado e limites

A versão HTML permitia gerar uma mensagem com valor adulterado. O teste mudou o valor local de um item de R$ 38,00 para R$ 0,01 e obteve o valor adulterado na mensagem. A versão com painel calcula o pedido no servidor e registra os valores em centavos. Uma mensagem do WhatsApp serve para localizar o pedido. A confirmação usa o registro no painel.

A régua da avaliação original descontava 20 pontos por achado crítico, 10 por alto, 4 por médio e 1 por baixo. A versão HTML marcou 86/100 no recorte de antifraude. A versão com painel e segundo fator marcou 96/100 naquele recorte local. A revisão do login com e-mail e senha descreve os controles e os riscos residuais abaixo. A configuração do domínio, a hospedagem e o crédito bancário ficam fora da avaliação local.

## 1B. Mapa de controles

| Área | Resultado | Evidência |
|---|---|---|
| Preço, sinal e catálogo | Verificado localmente | Cálculo no servidor, catálogo desatualizado rejeitado e registro imutável dos itens e valores. `decomer-privado/app.php` |
| Código e consulta de pedido | Verificado localmente | Código aleatório para o painel e token separado para consulta privada. O token fica fora da URL e do WhatsApp. `site/pedidos.php` |
| Autenticação e sessão | Verificado localmente | Login com e-mail e senha, senha inicial aleatória, hash Argon2id quando disponível, expiração de sessão, revogação após troca de senha e cookie Secure, HttpOnly e SameSite em HTTPS. `decomer-privado/admin.php` |
| Autorização e CSRF | Verificado localmente | Mutações administrativas por POST, sessão válida, token e origem. Alterações da chave Pix, do número de WhatsApp e da senha exigem confirmação da senha atual. `site/admin/index.php` |
| Injeção e script | Verificado localmente | Parâmetros SQL vinculados e saída escapada. Texto de teste com marcação apareceu como texto no site. `decomer-privado/app.php`, `decomer-privado/vitrine.php` |
| Imagens | Verificado localmente | JPEG, PNG e WebP limitados por bytes e pixels, decodificados e gravados como WebP em pasta privada. `decomer-privado/admin.php`, `site/imagem.php` |
| Exposição e transporte | Verificado localmente | HTTPS, HSTS, CSP, bloqueio de arquivos legados, credenciais fora da pasta pública e verificação de permissões. Ativação e extensões do hPanel ainda precisam de conferência. `site/bootstrap.php`, `site/.htaccess` |
| Privacidade e retenção | Controle implementado | Nome opcional, token na sessão do navegador e manutenção agendada de registros. A programação do cron é feita na hospedagem. |
| Disponibilidade | Risco residual | Limites por rede, para a conta administrativa e para a criação global de pedidos. Um volume distribuído pode esgotar uma cota temporariamente. |
| Liquidação Pix | Conferência manual | O painel registra a confirmação feita pela Jozyanne após consultar o banco. A verificação do extrato é parte do atendimento. |

## 2. Achados e correções

### Alto · Valores sob controle do navegador

Antes, `site/assets/pedido.js:14` e `site/assets/loja.js:205` montavam a cobrança a partir do catálogo do navegador e a colocavam em uma mensagem editável. Na versão atual, a API aceita referências, quantidades, revisão do catálogo e nome opcional. Valores externos são rejeitados. A transação consulta os produtos ativos e gera o pedido. No WhatsApp, a referência remete ao total registrado.

### Médio · Pagamento antes da confirmação e reutilização de comprovante

Antes, `site/assets/loja.js:210` copiava a chave da configuração pública. Agora a chave só fica disponível após confirmação, na consulta autorizada pelo token do pedido. A cópia consulta novamente o estado. O painel exige o valor exato do sinal e um identificador de transação único para Pix, preservando a capitalização do ID. A Jozyanne confere o crédito no extrato antes de registrar. [Banco Central](https://bcb.gov.br/meubc/faqs/p/vitima-fez-um-pix-e-caiu-em-um-golpe)

### Médio · Disponibilidade do checkout

`site/pedidos.php` limita solicitações por IP e agrupa IPv6 por prefixo /64. Em `decomer-privado/app.php`, só pedidos válidos consomem o limite global de criação. Requisições repetidas com o mesmo token recuperam o mesmo registro. Tráfego distribuído ainda pode esgotar o limite temporário ou pressionar recursos da hospedagem. O ambiente local verificou a contagem e a concorrência. Capacidade e limites da hospedagem serão observados no domínio publicado.

### Risco residual · Senha do painel

O login usa um fator. Uma senha obtida por phishing ou reutilizada pode dar acesso ao painel e autorizar mudanças após nova digitação. A aplicação gera uma senha inicial aleatória, exige pelo menos 15 caracteres na troca e limita tentativas por rede e por conta. O limite por conta também pode adiar temporariamente o acesso legítimo sob um ataque distribuído. A redefinição por SSH encerra as sessões e inicia novos limites para as novas credenciais. [Autenticação OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)

## 3. Cenários testados

| Cenário | Resultado observado |
|---|---|
| Alterar o preço no pedido ou enviar quantidade negativa, decimal ou em texto | Rejeitado |
| Usar catálogo antigo, código desconhecido ou produto indisponível | Rejeitado |
| Enviar oito criações simultâneas com o mesmo token | Um único registro de pedido |
| Consultar pedido com token incorreto ou fazer mutação sem autenticação | Rejeitado |
| Tentar login sem token, de outra origem ou com e-mail ou senha inválidos | Rejeitado |
| Exceder o limite de login na mesma rede | HTTP 429 com tempo de espera |
| Trocar Pix ou WhatsApp sem confirmar a senha | Rejeitado |
| Trocar senha e reaproveitar sessões anteriores | Sessões anteriores revogadas |
| Migrar o acesso anterior para e-mail e senha | Catálogo, configurações e pedidos preservados |
| Enviar POST de outra origem, formato indevido ou acima do limite | Rejeitado |
| Enviar PHP disfarçado de foto e SVG | Rejeitado |
| Publicar descrição contendo script | Exibida como texto |
| Confirmar Pix, copiar a chave e cancelar o pedido | Chave liberada após confirmação e retirada na consulta seguinte |
| Registrar sinal menor ou repetir ID Pix | Rejeitado |
| Alterar preço, publicar foto, editar logo e ficha da massa | Mudanças refletidas na vitrine |
| Tentar ler pasta privada, configuração antiga ou listar arquivos | Acesso bloqueado no Apache de teste |

## 4. Antes de publicar

Siga o guia do hPanel para configurar o banco, gerar as credenciais no terminal e validar HTTPS, pasta privada e extensões. Complete os dados públicos da fornecedora e a ficha da massa com as informações confirmadas. A confirmação bancária é feita pela loja.
