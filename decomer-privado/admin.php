<?php
declare(strict_types=1);

const TEMPO_SESSAO = 1800;

function cookieAdmin(): string
{
    return https() ? '__Host-decomer-admin' : 'decomer-admin-local';
}

function iniciarSessao(): void
{
    $pasta = pastaDados().'/sessoes';
    if (!is_dir($pasta) && !@mkdir($pasta, 0700) && !is_dir($pasta)) throw new RuntimeException('Pasta de sessões indisponível.');
    if (is_link($pasta)) throw new RuntimeException('Pasta de sessões inválida.');
    session_save_path($pasta);
    session_name(cookieAdmin());
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)(8*3600));
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>https(),'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) throw new RuntimeException('Não foi possível iniciar a sessão.');
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function verificarCsrf(): void
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf']??'', $_POST['csrf'])) {
        throw new Falha(403, 'Sessão do formulário inválida. Atualize a página.');
    }
    if (!origemFormularioValida()) throw new Falha(403, 'Origem inválida.');
}

function csrfLogin(array $admin): string
{
    $instante = (string)time();
    return $instante.'.'.hash_hmac('sha256','login/'.$instante.'/'.clienteIp(),hex2bin($admin['formulario']));
}

function origemFormularioValida(): bool
{
    $origem = (https()?'https://':'http://').($_SERVER['HTTP_HOST']??'');
    $site = $_SERVER['HTTP_SEC_FETCH_SITE']??'';
    $enviada = $_SERVER['HTTP_ORIGIN']??'';
    // Formulários nativos com no-referrer podem enviar Origin: null.
    return $site === 'same-origin'
        ? in_array($enviada, ['', 'null', $origem], true)
        : $site === '' && $enviada === $origem;
}

function verificarLogin(array $admin): void
{
    $token = $_POST['csrf']??null;
    if (!origemFormularioValida()
        || !is_string($token) || preg_match('/\A(\d{10})\.([a-f0-9]{64})\z/D',$token,$partes) !== 1
        || (int)$partes[1] > time() || (int)$partes[1] < time()-900
        || !hash_equals(hash_hmac('sha256','login/'.$partes[1].'/'.clienteIp(),hex2bin($admin['formulario'])),$partes[2])) {
        throw new Falha(403,'Sessão do formulário inválida. Atualize a página.');
    }
}

function emailAdmin(mixed $valor): ?string
{
    if (!is_string($valor) || strlen($valor) > 254) return null;
    $email = strtolower(trim($valor));
    return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
}

function limiteSenha(string $algoritmo): int
{
    return $algoritmo === PASSWORD_BCRYPT ? 72 : 256;
}

function senhaConfere(mixed $senha, string $hash): bool
{
    $algoritmo = password_get_info($hash)['algo'];
    return is_string($senha) && strlen($senha) <= limiteSenha($algoritmo)
        && !str_contains($senha, "\0") && password_verify($senha, $hash);
}

function hashSenhaAdmin(string $senha): string
{
    $algoritmo = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    if (strlen($senha) > limiteSenha($algoritmo)) throw new Falha(422, 'Use uma senha mais curta.');
    if (str_contains($senha, "\0") || !mb_check_encoding($senha, 'UTF-8')
        || mb_strlen($senha, 'UTF-8') < 15 || trim($senha) === '') {
        throw new Falha(422, 'Use uma senha com pelo menos 15 caracteres.');
    }
    return password_hash($senha, $algoritmo);
}

function lerAdmin(): ?array
{
    $arquivo = arquivoAdmin();
    clearstatcache(true, $arquivo);
    if (!is_file($arquivo)) return null;
    if (!adminConfigurado()) throw new RuntimeException('Configuração administrativa inválida.');
    $admin = json_decode(file_get_contents($arquivo),true,8,JSON_THROW_ON_ERROR);
    if (isset($admin['totp']) && !isset($admin['email'])) return null;
    foreach (['geracao'=>48,'formulario'=>64] as $campo=>$comprimento) {
        if (!is_string($admin[$campo]??null) || strlen($admin[$campo]) !== $comprimento || !ctype_xdigit($admin[$campo])) {
            throw new RuntimeException('Configuração administrativa inválida.');
        }
    }
    $email = emailAdmin($admin['email']??null);
    if ($email === null || $email !== $admin['email'] || !is_string($admin['senha']??null)
        || password_get_info($admin['senha'])['algo'] === null) {
        throw new RuntimeException('Configuração administrativa inválida.');
    }
    return $admin;
}

/** @return resource */
function travarConfiguracao()
{
    $lock = fopen(pastaDados().'/.configuracao.lock', 'c');
    if (!$lock) throw new RuntimeException('Não foi possível abrir a trava de configuração.');
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        throw new RuntimeException('Configuração ocupada.');
    }
    return $lock;
}

function gravarAdmin(string $email, string $hash): void
{
    $novo = [
        'email'=>$email, 'senha'=>$hash,
        'geracao'=>bin2hex(random_bytes(24)), 'formulario'=>bin2hex(random_bytes(32)),
    ];
    $temp = tempnam(pastaDados(), 'admin-');
    if (!$temp) throw new RuntimeException('Falha ao criar arquivo de credenciais.');
    try {
        $dados = jsonSeguro($novo)."\n";
        if (file_put_contents($temp, $dados, LOCK_EX) !== strlen($dados) || !chmod($temp, 0600)
            || !rename($temp, arquivoAdmin())) throw new RuntimeException('Falha ao salvar credenciais.');
    } finally {
        if (is_file($temp)) unlink($temp);
    }
}

function adminAutenticado(?array $admin): bool
{
    $agora = time();
    if (!$admin || !is_string($_SESSION['geracao'] ?? null) || !hash_equals($admin['geracao'],$_SESSION['geracao'])
        || ($_SESSION['atividade'] ?? 0) < $agora-TEMPO_SESSAO || ($_SESSION['inicio'] ?? 0) < $agora-8*3600) {
        unset($_SESSION['geracao'], $_SESSION['atividade'], $_SESSION['inicio']);
        return false;
    }
    $_SESSION['atividade'] = $agora;
    return true;
}

function autenticar(PDO $pdo, array $admin, mixed $email, mixed $senha): bool
{
    $rede = 'login:rede:'.hash('sha256', $admin['geracao'].'/'.clienteIp());
    $conta = 'login:conta:'.$admin['geracao'];
    transacao($pdo, fn () => limites($pdo, [[$rede,8,900],[$conta,40,300]]));
    $endereco = emailAdmin($email);
    if (!senhaConfere($senha,$admin['senha']) || $endereco === null || !hash_equals($admin['email'],$endereco)) return false;
    transacao($pdo, function () use ($pdo,$rede,$conta): void {
        consulta($pdo,'DELETE FROM limites WHERE chave IN (?,?)',[$rede,$conta]);
        evento($pdo,'Entrada no painel','administrador');
    });
    if (session_status() !== PHP_SESSION_ACTIVE) iniciarSessao();
    if (!session_regenerate_id(true)) throw new RuntimeException('Sessão indisponível.');
    $_SESSION = ['geracao'=>$admin['geracao'],'inicio'=>time(),'atividade'=>time(),'csrf'=>bin2hex(random_bytes(32))];
    return true;
}

function comAcessoAtual(array $admin, callable $acao): void
{
    $lock = travarConfiguracao();
    try {
        $atual = lerAdmin();
        if (!$atual || !hash_equals($atual['geracao'], $admin['geracao'])) {
            throw new Falha(409, 'O acesso mudou. Entre novamente no painel.');
        }
        $acao();
    } finally {
        fclose($lock);
    }
}

function comSenhaConfirmada(PDO $pdo, array $admin, mixed $senha, callable $acao): void
{
    $rede = 'confirma:rede:'.hash('sha256', $admin['geracao'].'/'.clienteIp());
    $conta = 'confirma:conta:'.$admin['geracao'];
    transacao($pdo, fn () => limites($pdo, [[$rede,8,900],[$conta,24,300]]));
    if (!senhaConfere($senha, $admin['senha'])) throw new Falha(403, 'Informe a senha atual do painel.');

    comAcessoAtual($admin, function () use ($pdo,$rede,$conta,$acao): void {
        transacao($pdo, fn () => consulta($pdo,'DELETE FROM limites WHERE chave IN (?,?)',[$rede,$conta]));
        $acao();
    });
}

function trocarSenha(PDO $pdo, array $admin): void
{
    $nova = $_POST['nova_senha']??null;
    $confirmacao = $_POST['confirmar_senha']??null;
    if (!is_string($nova) || !is_string($confirmacao) || $nova !== $confirmacao) {
        throw new Falha(422, 'Digite a mesma senha nova nos dois campos.');
    }
    comSenhaConfirmada($pdo, $admin, $_POST['senha_atual']??null, function () use ($admin,$nova): void {
        if (senhaConfere($nova, $admin['senha'])) throw new Falha(422, 'Escolha uma senha diferente da atual.');
        gravarAdmin($admin['email'], hashSenhaAdmin($nova));
    });
    try {
        transacao($pdo, fn () => evento($pdo,'Alterou a senha do painel','administrador'));
    } catch (Throwable $erro) {
        falhaPublica($erro);
    }
}

function encerrarSessao(): void
{
    $_SESSION = [];
    setcookie(session_name(),'', ['expires'=>1,'path'=>'/','secure'=>https(),'httponly'=>true,'samesite'=>'Strict']);
    session_destroy();
}

function camposLoja(): array
{
    return [
        'Identidade e contato'=>[
            'marca'=>['Nome da loja',55], 'submarca'=>['Subtítulo da marca',75], 'responsavel'=>['Nome no atendimento',50],
            'instagram'=>['Usuário do Instagram, sem @',30,false],
            'razaoSocial'=>['Razão social ou nome comercial',100,false], 'cnpj'=>['CNPJ',24,false], 'endereco'=>['Endereço comercial',200,false],
        ],
        'Página e encomendas'=>[
            'faixa'=>['Faixa de avisos',140], 'prazo'=>['Data limite das encomendas',10,false,'date'],
            'titulo'=>['Título da abertura',65], 'chamada'=>['Texto da abertura',180], 'descSite'=>['Descrição para buscadores',200],
            'tituloCatalogo'=>['Título do catálogo',65], 'descCatalogo'=>['Introdução do catálogo',200],
            'tituloEncomendas'=>['Título das instruções',65],
            'etapa1Titulo'=>['Título da primeira etapa',60], 'etapa1Texto'=>['Texto da primeira etapa',360,false],
            'etapa2Titulo'=>['Título da segunda etapa',60], 'etapa2Texto'=>['Texto da segunda etapa',360,false],
            'etapa3Titulo'=>['Título da terceira etapa',60], 'etapa3Texto'=>['Texto da terceira etapa',360,false],
            'retirada'=>['Texto de retirada e envio',600],
        ],
        'Sobre a massa'=>[
            'tituloMassa'=>['Título da seção',65], 'sobreMassa'=>['Validade e conservação',450],
            'ingredientes'=>['Ingredientes',1200,false], 'alergenicos'=>['Alergênicos',700,false],
            'consultaMassa'=>['Texto enquanto faltar informação',250],
        ],
    ];
}

function secoesEdicaoLoja(): array
{
    return [
        'marca'=>['Marca e identidade','Nome, logomarca e dados comerciais que aparecem na loja.','Salvar identidade','Identidade salva.',
            ['marca','submarca','responsavel','instagram','razaoSocial','cnpj','endereco','logo']],
        'vitrine'=>['Vitrine','Edite a abertura, a apresentação do catálogo e os produtos em destaque.','Salvar vitrine','Vitrine salva.',
            ['titulo','chamada','descSite','tituloCatalogo','descCatalogo','destaquePrincipal','destaqueSecundario']],
        'encomendas'=>['Encomendas','Controle a disponibilidade e edite as orientações de compra e entrega.','Salvar encomendas','Configurações de encomendas salvas.',
            ['faixa','prazo','recebendo','tituloEncomendas','etapa1Titulo','etapa1Texto','etapa2Titulo','etapa2Texto','etapa3Titulo','etapa3Texto','retirada']],
        'massa'=>['Sobre a massa','Edite a ficha que os clientes consultam antes de encomendar.','Salvar ficha da massa','Ficha da massa salva.',
            ['tituloMassa','sobreMassa','ingredientes','alergenicos','consultaMassa']],
        'whatsapp'=>['WhatsApp','Defina o número que recebe as mensagens e as novas encomendas.','Salvar WhatsApp','WhatsApp salvo.',
            ['whatsapp']],
    ];
}

function versaoEdicaoLoja(array $loja, string $secao): string
{
    $dados = [];
    foreach (secoesEdicaoLoja()[$secao][4] as $campo) $dados[$campo] = $loja[$campo]??null;
    return hash('sha256',jsonSeguro($dados));
}

function valorInteiro(mixed $valor, int $minimo, int $maximo): int
{
    if (!is_string($valor) || preg_match('/\A[0-9]{1,8}\z/D',$valor) !== 1 || (int)$valor < $minimo || (int)$valor > $maximo) {
        throw new Falha(422, 'Informe um número dentro do intervalo indicado.');
    }
    return (int)$valor;
}

function precoCampo(mixed $valor, int $limite = MAX_PRECO): int
{
    if (!is_string($valor) || preg_match('/\A([0-9]{1,8})(?:[,.]([0-9]{1,2}))?\z/D',trim($valor),$partes) !== 1) {
        throw new Falha(422, 'Informe o preço em reais, como 38,00.');
    }
    $centavos = (int)$partes[1]*100+(int)str_pad($partes[2]??'',2,'0');
    if ($centavos < 1 || $centavos > $limite) throw new Falha(422,'Valor fora do intervalo permitido.');
    return $centavos;
}

function enviarFoto(?array $arquivo): ?array
{
    if (!$arquivo || ($arquivo['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
    $origem = $arquivo['tmp_name'] ?? null;
    if (($arquivo['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($origem) || !is_uploaded_file($origem)
        || filesize($origem) > 8*1024*1024) throw new Falha(422,'Envie uma imagem de até 8 MB.');
    $dim = @getimagesize($origem);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($origem);
    if (!$dim || !in_array($mime,['image/jpeg','image/png','image/webp'],true) || $dim['mime'] !== $mime
        || $dim[0] < 32 || $dim[1] < 32 || $dim[0] > 6000 || $dim[1] > 6000 || $dim[0]*$dim[1] > 16000000) {
        throw new Falha(422,'Use JPEG, PNG ou WebP, com até 16 megapixels.');
    }
    $fonte = @imagecreatefromstring(file_get_contents($origem));
    if (!$fonte) throw new Falha(422,'Não foi possível ler a imagem.');
    if (!imageistruecolor($fonte)) imagepalettetotruecolor($fonte);
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($origem);
        $orientacao = (int)($exif['Orientation'] ?? 1);
        if (in_array($orientacao,[2,4,5,7],true)) imageflip($fonte,$orientacao===4?IMG_FLIP_VERTICAL:IMG_FLIP_HORIZONTAL);
        $angulo = [3=>180,5=>90,6=>-90,7=>-90,8=>90][$orientacao] ?? 0;
        if ($angulo !== 0) {
            $rotacionada = imagerotate($fonte,$angulo,0);
            if (!$rotacionada) throw new Falha(422,'Não foi possível corrigir a orientação da imagem.');
            $fonte = $rotacionada;
        }
    }
    $dir = pastaDados().'/fotos';
    if (!is_dir($dir) && !@mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('Pasta de fotos indisponível.');
    if (is_link($dir)) throw new RuntimeException('Pasta de fotos inválida.');
    $id = bin2hex(random_bytes(16));
    $foto = ['id'=>$id];
    try {
        foreach (['400'=>['pequena',400,540],'940'=>['grande',940,1240]] as $size => [$nome,$maxW,$maxH]) {
            $fator = min(1,$maxW/imagesx($fonte),$maxH/imagesy($fonte));
            $w = max(1,(int)round(imagesx($fonte)*$fator));
            $h = max(1,(int)round(imagesy($fonte)*$fator));
            $saida = imagecreatetruecolor($w,$h);
            imagealphablending($saida,false);
            imagesavealpha($saida,true);
            imagefill($saida,0,0,imagecolorallocatealpha($saida,0,0,0,127));
            imagecopyresampled($saida,$fonte,0,0,0,0,$w,$h,imagesx($fonte),imagesy($fonte));
            $destino = $dir.'/'.$id.'-'.$size.'.webp';
            if (!imagewebp($saida,$destino,88) || !is_file($destino) || filesize($destino) === 0) throw new RuntimeException('Falha ao gravar foto.');
            $foto[$nome] = ['w'=>$w,'h'=>$h];
        }
        return $foto;
    } catch (Throwable $erro) {
        removerFoto($foto);
        throw $erro;
    }
}

function removerFoto(?array $foto): void
{
    if (!isset($foto['id']) || preg_match('/\A[a-f0-9]{32}\z/D',$foto['id']) !== 1) return;
    foreach (['400','940'] as $t) {
        $arquivo = pastaDados().'/fotos/'.$foto['id'].'-'.$t.'.webp';
        if (is_file($arquivo)) @unlink($arquivo);
    }
}

function mudarCatalogo(PDO $pdo): void
{
    $pdo->exec('UPDATE loja SET revisao=revisao+1 WHERE id=1');
}

function salvarProduto(PDO $pdo): void
{
    $id = textoCampo($_POST['id']??'',25,false);
    $nome = textoCampo($_POST['nome']??null,90);
    $modelo = textoCampo($_POST['modelo']??'',85,false);
    $descricao = textoCampo($_POST['descricao']??'',650,false);
    $categoria = textoCampo($_POST['categoria']??null,32);
    $centavos = precoCampo($_POST['preco']??null);
    $posicao = valorInteiro($_POST['posicao']??null,0,9999);
    $publicado = isset($_POST['publicado']) ? 1 : 0;
    $fotoNova = enviarFoto($_FILES['foto']??null);
    try {
        $fotoAnterior = transacao($pdo,function () use ($pdo,$id,$nome,$modelo,$descricao,$categoria,$centavos,$posicao,$publicado,$fotoNova): ?array {
            if (!isset(estadoLoja($pdo)['categorias'][$categoria])) throw new Falha(422,'Selecione uma categoria válida.');
            $atual = $id === '' ? null : consulta($pdo,'SELECT foto FROM produtos WHERE id=?',[$id])->fetch();
            if ($id !== '' && !$atual) throw new Falha(404,'Produto não encontrado.');
            if ($id === '' && (int)$pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn() >= MAX_PRODUTOS) {
                throw new Falha(422,'O catálogo atingiu o limite de produtos.');
            }
            if (!$atual && !$fotoNova) throw new Falha(422,'Envie uma foto para cadastrar o produto.');
            $foto = $fotoNova ? jsonSeguro($fotoNova) : $atual['foto'];
            $dados = [$nome,$modelo,$centavos,$categoria,$descricao,$posicao,$publicado,$foto];
            $codigo = $id ?: 'DC-'.strtoupper(bin2hex(random_bytes(4)));
            if ($atual) consulta($pdo,'UPDATE produtos SET nome=?,modelo=?,centavos=?,categoria=?,descricao=?,posicao=?,publicado=?,foto=? WHERE id=?',[...$dados,$codigo]);
            else consulta($pdo,'INSERT INTO produtos (nome,modelo,centavos,categoria,descricao,posicao,publicado,foto,id) VALUES (?,?,?,?,?,?,?,?,?)',[...$dados,$codigo]);
            mudarCatalogo($pdo);
            evento($pdo,$atual?'Editou produto':'Criou produto',$codigo);
            return $atual && $fotoNova ? json_decode($atual['foto'],true,8,JSON_THROW_ON_ERROR) : null;
        });
    } catch (Throwable $erro) {
        removerFoto($fotoNova);
        throw $erro;
    }
    removerFoto($fotoAnterior);
}

function excluirProduto(PDO $pdo): void
{
    if (!isset($_POST['exclusao'])) throw new Falha(422,'Confirme a exclusão do produto.');
    $id = textoCampo($_POST['id']??null,25);
    $foto = transacao($pdo,function () use ($pdo,$id): array {
        $atual = consulta($pdo,'SELECT foto FROM produtos WHERE id=?',[$id])->fetch();
        if (!$atual) throw new Falha(404,'Produto não encontrado.');
        consulta($pdo,'DELETE FROM produtos WHERE id=?',[$id]);
        mudarCatalogo($pdo);
        evento($pdo,'Excluiu produto',$id);
        return json_decode($atual['foto'],true,8,JSON_THROW_ON_ERROR);
    });
    removerFoto($foto);
}

function salvarLoja(PDO $pdo, string $secao): void
{
    $secoes = secoesEdicaoLoja();
    if (!isset($secoes[$secao]) || $secao === 'whatsapp') {
        throw new Falha(422,'Seção de edição inválida.');
    }
    $campos = $secoes[$secao][4];
    $novos = [];
    foreach (camposLoja() as $grupo) {
        foreach ($grupo as $campo => $def) {
            if (in_array($campo,$campos,true)) {
                try {
                    $novos[$campo] = textoCampo($_POST[$campo]??null,$def[1],$def[2]??true);
                } catch (Falha $erro) {
                    throw new Falha($erro->http,'Revise “'.$def[0].'”. '.$erro->getMessage());
                }
            }
        }
    }
    if (!empty($novos['instagram']) && preg_match('/\A[a-zA-Z0-9._]{1,30}\z/D',$novos['instagram']) !== 1) {
        throw new Falha(422,'Informe o usuário do Instagram sem @, com letras, números, pontos ou sublinhados.');
    }
    if (!empty($novos['prazo']) && (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$novos['prazo'],$d)
        || !checkdate((int)$d[2],(int)$d[3],(int)$d[1]))) {
        throw new Falha(422,'Informe uma data limite válida.');
    }
    if ($secao === 'encomendas') $novos['recebendo'] = isset($_POST['recebendo']);
    $destaques = ['destaquePrincipal','destaqueSecundario'];
    if ($secao === 'vitrine') {
        foreach ($destaques as $campo) $novos[$campo] = textoCampo($_POST[$campo]??'',25,false);
    }
    $editarLogo = $secao === 'marca';
    $fotoNova = $editarLogo ? enviarFoto($_FILES['logo']??null) : null;
    try {
        $anterior = transacao($pdo,function () use ($pdo,$novos,$fotoNova,$editarLogo,$destaques,$secao): ?array {
            $atual = estadoLoja($pdo)['loja'];
            verificarVersaoEdicao($atual,$secao);
            if (isset($novos['destaquePrincipal'])) {
                $lista = produtos($pdo);
                foreach ($destaques as $campo) {
                    if ($novos[$campo] !== '' && !isset($lista[$novos[$campo]])) throw new Falha(422,'Selecione um destaque publicado.');
                }
            }
            $logo = $fotoNova ?? ($editarLogo && isset($_POST['restaurarLogo']) ? null : $atual['logo']);
            $final = array_replace($atual,$novos,['logo'=>$logo]);
            if ($final === $atual) return null;
            consulta($pdo,'UPDATE loja SET configuracao=?, revisao=revisao+1 WHERE id=1',[jsonSeguro($final)]);
            evento($pdo,'Editou a página',secoesEdicaoLoja()[$secao][0]);
            return $logo !== $atual['logo'] ? $atual['logo'] : null;
        });
    } catch (Throwable $erro) {
        removerFoto($fotoNova);
        throw $erro;
    }
    removerFoto($anterior);
}

function verificarVersaoEdicao(array $loja, string $secao): void
{
    $versao = $_POST['versao']??null;
    if (!is_string($versao) || !hash_equals(versaoEdicaoLoja($loja,$secao),$versao)) {
        throw new Falha(409,'Esta seção foi alterada em outra sessão. Copie suas alterações se precisar e abra a versão atual antes de salvar.');
    }
}

function salvarWhatsApp(PDO $pdo, array $admin): void
{
    $numero = textoCampo($_POST['whatsapp']??null,15);
    if (preg_match('/\A[1-9][0-9]{9,14}\z/D',$numero) !== 1) {
        throw new Falha(422,'Informe o número de WhatsApp com código do país e DDD.');
    }
    comSenhaConfirmada($pdo, $admin, $_POST['senha_atual']??null, function () use ($pdo,$numero): void {
        transacao($pdo, function () use ($pdo,$numero): void {
            $loja = estadoLoja($pdo)['loja'];
            verificarVersaoEdicao($loja,'whatsapp');
            if ($loja['whatsapp'] === $numero) return;
            $loja['whatsapp'] = $numero;
            consulta($pdo,"UPDATE pedidos SET whatsapp=? WHERE estado IN ('solicitado','confirmado','sinal_recebido')",[$numero]);
            consulta($pdo,'UPDATE loja SET configuracao=?, revisao=revisao+1 WHERE id=1',[jsonSeguro($loja)]);
            evento($pdo,'Alterou WhatsApp da loja','loja');
        });
    });
}

function salvarCategoria(PDO $pdo): void
{
    $id = textoCampo($_POST['id']??'',32,false);
    $nome = textoCampo($_POST['nome']??null,46);
    $excluir = ($_POST['modo']??'') === 'excluir';
    transacao($pdo,function () use ($pdo,$id,$nome,$excluir): void {
        $cats = estadoLoja($pdo)['categorias'];
        if ($id !== '' && !isset($cats[$id])) throw new Falha(404,'Categoria não encontrada.');
        if ($excluir) {
            if ($id === '' || consulta($pdo,'SELECT 1 FROM produtos WHERE categoria=? LIMIT 1',[$id])->fetchColumn()) {
                throw new Falha(409,'Mova os produtos antes de excluir esta categoria.');
            }
            unset($cats[$id]);
        } else {
            if ($id === '' && count($cats) >= 30) throw new Falha(422,'Limite de categorias atingido.');
            $id = $id ?: 'cat-'.bin2hex(random_bytes(6));
            $cats[$id] = $nome;
        }
        consulta($pdo,'UPDATE loja SET categorias=?, revisao=revisao+1 WHERE id=1',[jsonSeguro((object)$cats)]);
        evento($pdo,$excluir?'Excluiu categoria':'Salvou categoria',$id);
    });
}

function salvarPix(PDO $pdo, array $admin): void
{
    $pix = textoCampo($_POST['pix']??'',20,false);
    $recebedor = textoCampo($_POST['recebedor']??'',100,false);
    if ($pix !== '' && (preg_match('/\A\+55[1-9][0-9]9\d{8}\z/D',$pix) !== 1 || $recebedor === '')) {
        throw new Falha(422,'Informe a chave por telefone e o nome do titular conforme o banco.');
    }
    comSenhaConfirmada($pdo, $admin, $_POST['senha_atual']??null, function () use ($pdo,$pix,$recebedor): void {
        transacao($pdo, function () use ($pdo,$pix,$recebedor): void {
            $loja = estadoLoja($pdo)['loja'];
            if ($loja['chavePix'] === $pix && ($loja['recebedorPix']??'') === $recebedor) return;
            $pdo->exec("UPDATE pedidos SET pix=NULL, recebedor=NULL WHERE estado IN ('confirmado','sinal_recebido')");
            $loja['chavePix'] = $pix;
            $loja['recebedorPix'] = $recebedor;
            consulta($pdo,'UPDATE loja SET configuracao=? WHERE id=1',[jsonSeguro($loja)]);
            evento($pdo,'Alterou configuração Pix','loja');
        });
    });
}

function conferirPagamento(PDO $pdo, int $esperado): array
{
    $metodo = $_POST['metodo']??'';
    if (!isset($_POST['conferi']) || !in_array($metodo,['Pix','Dinheiro'],true)
        || precoCampo($_POST['valor']??null,MAX_PRECO*MAX_PRODUTOS*MAX_QUANTIDADE) !== $esperado) {
        throw new Falha(422,'Confira o crédito e informe o valor exato desta parcela.');
    }
    if ($metodo === 'Dinheiro') return [$metodo,null];

    $ref = textoCampo($_POST['referencia']??null,45);
    if (preg_match('/\A[A-Za-z0-9]{32}\z/D',$ref) !== 1) {
        throw new Falha(422,'Informe o EndToEndId do Pix conforme consta no extrato.');
    }
    if (consulta($pdo,'SELECT 1 FROM pedidos WHERE referencia_pix=? UNION ALL SELECT 1 FROM saldos_pedidos WHERE referencia_pix=? LIMIT 1',[$ref,$ref])->fetchColumn()) {
        throw new Falha(409,'Este crédito Pix já foi registrado em uma parcela.');
    }
    return [$metodo,$ref];
}

function alterarPedido(PDO $pdo): void
{
    $id = textoCampo($_POST['id']??null,27);
    $acao = $_POST['modo']??'';
    transacao($pdo,function () use ($pdo,$id,$acao): void {
        $pedido = consulta($pdo,'SELECT * FROM pedidos WHERE codigo=?',[$id])->fetch();
        if (!$pedido) throw new Falha(404,'Pedido não encontrado.');
        $agora = time();
        if ($acao === 'confirmar' && $pedido['estado'] === 'solicitado') {
            if ($pedido['criado'] < $agora-PRAZO_PEDIDO) throw new Falha(409,'Solicitação vencida. Peça um novo pedido.');
            $loja = estadoLoja($pdo)['loja'];
            $pix = $loja['chavePix'];
            $recebedor = $loja['recebedorPix']??'';
            if ($pix !== '' && $recebedor === '') throw new Falha(422,'Preencha o nome do recebedor na seção Pix do painel.');
            consulta($pdo,"UPDATE pedidos SET estado='confirmado', confirmado=?, pix=?, recebedor=? WHERE codigo=?",
                [$agora,$pix,$recebedor,$id]);
            evento($pdo,'Confirmou pedido',$id);
            return;
        }
        if ($acao === 'receber' && $pedido['estado'] === 'confirmado') {
            [$metodo,$ref] = conferirPagamento($pdo,(int)$pedido['sinal']);
            $estado = (int)$pedido['restante'] === 0 ? 'quitado' : 'sinal_recebido';
            consulta($pdo,'UPDATE pedidos SET estado=?, pago=?, metodo=?, referencia_pix=? WHERE codigo=?',
                [$estado,$agora,$metodo,$ref,$id]);
            evento($pdo,'Registrou o recebimento do sinal',$id);
            return;
        }
        if ($acao === 'receber-saldo' && $pedido['estado'] === 'sinal_recebido' && (int)$pedido['restante'] > 0) {
            [$metodo,$ref] = conferirPagamento($pdo,(int)$pedido['restante']);
            consulta($pdo,'INSERT INTO saldos_pedidos (codigo,recebido,metodo,referencia_pix) VALUES (?,?,?,?)',[$id,$agora,$metodo,$ref]);
            consulta($pdo,"UPDATE pedidos SET estado='quitado' WHERE codigo=?",[$id]);
            evento($pdo,'Registrou o recebimento do saldo',$id);
            return;
        }
        if ($acao === 'reativar-pix' && in_array($pedido['estado'],['confirmado','sinal_recebido'],true) && !$pedido['pix']) {
            $loja = estadoLoja($pdo)['loja'];
            if (empty($loja['chavePix']) || empty($loja['recebedorPix'])) throw new Falha(422,'Configure a chave e o titular na seção Pix.');
            consulta($pdo,'UPDATE pedidos SET pix=?, recebedor=? WHERE codigo=?',[$loja['chavePix'],$loja['recebedorPix'],$id]);
            evento($pdo,'Liberou o Pix atualizado no pedido',$id);
            return;
        }
        if ($acao === 'cancelar' && in_array($pedido['estado'],['solicitado','confirmado','sinal_recebido'],true)) {
            if ($pedido['estado'] === 'sinal_recebido' && !isset($_POST['confirmar_cancelamento'])) {
                throw new Falha(422,'Confirme o cancelamento do pedido que já recebeu a entrada.');
            }
            consulta($pdo,"UPDATE pedidos SET estado='cancelado', pix=NULL, recebedor=NULL WHERE codigo=?",[$id]);
            evento($pdo,'Cancelou pedido',$id);
            return;
        }
        throw new Falha(409,'O pedido já mudou de estado. Atualize a página.');
    });
}
