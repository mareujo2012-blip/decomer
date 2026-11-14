<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__.'/app.php';
require __DIR__.'/admin.php';
umask(0077);

try {
    $primeiro = $argv[1]??'';
    $modo = str_starts_with($primeiro,'--') ? $primeiro : '';
    if (!in_array($modo,['','--redefinir','--manutencao','--check','--atualizar'],true)
        || count($argv) > ($modo === '--redefinir' ? 3 : 2)) {
        fwrite(STDERR,"Uso: php configurar.php email | --redefinir [email] | --check | --atualizar | --manutencao\n");
        exit(1);
    }
    foreach (['pdo_mysql','gd','fileinfo','mbstring','iconv'] as $extensao) {
        if (!extension_loaded($extensao)) throw new RuntimeException('Extensão ausente.');
    }
    if (!(gd_info()['WebP Support']??false)) throw new RuntimeException('GD sem WebP.');
    $pdo = conectarBanco();
    if ($modo === '--check') {
        if (!is_writable(pastaDados())) throw new RuntimeException('Pasta de dados sem escrita.');
        echo 'PHP '.PHP_VERSION." · banco acessível · upload WebP disponível · pasta de dados gravável\n";
        exit;
    }
    if ($modo === '--manutencao') {
        transacao($pdo,fn () => limparPedidosAntigos($pdo,time()));
        echo "Limpeza executada.\n";
        exit;
    }
    $lock = travarConfiguracao();
    try {
        if ($modo === '--atualizar') {
            instalarSchema($pdo);
            transacao($pdo,fn () => $pdo->exec("UPDATE pedidos SET estado='quitado' WHERE estado='sinal_recebido' AND restante=0"));
            echo "Estrutura de dados atualizada.\n";
            exit;
        }
        if (is_file(arquivoAdmin()) && $modo !== '--redefinir') {
            fwrite(STDERR,"O administrador já está configurado. Use --redefinir para trocar as credenciais.\n");
            exit(1);
        }
        $emailEntrada = $modo === '--redefinir' ? ($argv[2]??lerAdmin()['email']??'') : $primeiro;
        $email = emailAdmin($emailEntrada);
        if ($email === null) {
            fwrite(STDERR,"Informe um endereço de e-mail válido para o administrador.\n");
            exit(1);
        }
        inicializarBanco($pdo);
        $senha = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'),'=');
        gravarAdmin($email, hashSenhaAdmin($senha));
    } finally {
        fclose($lock);
    }
    echo "Administrador configurado.\n";
    echo 'E-mail do painel: '.$email."\n";
    echo 'Senha inicial gerada: '.$senha."\n";
    echo "Entre em /admin/ com o e-mail e a senha. Você pode trocar a senha na aba Minha conta.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha ao configurar o painel. Confira PHP, extensões e permissões da pasta privada.'."\n");
    exit(1);
}
