<?php
declare(strict_types=1);

function campoPix(string $id, string $valor): string
{
    return $id.str_pad((string)strlen($valor),2,'0',STR_PAD_LEFT).$valor;
}

function referenciaCobrancaPix(string $codigo, string $parcela): string
{
    return ($parcela === 'entrada' ? 'E' : 'S').substr($codigo,3);
}

function cobrancaPix(array $pedido, string $parcela): ?array
{
    $centavos = (int)$pedido[$parcela === 'entrada' ? 'sinal' : 'restante'];
    $chave = $pedido['pix'] ?? '';
    $recebedor = $pedido['recebedor'] ?? '';
    if ($centavos < 1 || $recebedor === '' || preg_match('/\A\+55[1-9][0-9]9\d{8}\z/D',$chave) !== 1) {
        return null;
    }

    $referencia = referenciaCobrancaPix($pedido['codigo'],$parcela);
    $nome = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$recebedor);
    $nome = trim(preg_replace('/[^A-Z0-9 ]/','',strtoupper($nome ?: '')));
    $valor = sprintf('%d.%02d',intdiv($centavos,100),$centavos % 100);
    $base = campoPix('00','01')
        .campoPix('26',campoPix('00','br.gov.bcb.pix').campoPix('01',$chave))
        .campoPix('52','0000').campoPix('53','986').campoPix('54',$valor).campoPix('58','BR')
        .campoPix('59',substr($nome ?: 'DE COMER',0,25)).campoPix('60','BRASILIA')
        .campoPix('62',campoPix('05',$referencia)).'6304';

    $crc = 0xffff;
    foreach (str_split($base) as $byte) {
        $crc ^= ord($byte) << 8;
        for ($bit = 0; $bit < 8; $bit++) {
            $crc = (($crc << 1) ^ (($crc & 0x8000) ? 0x1021 : 0)) & 0xffff;
        }
    }
    return ['recebedor'=>$recebedor,'copia_cola'=>$base.sprintf('%04X',$crc)];
}
