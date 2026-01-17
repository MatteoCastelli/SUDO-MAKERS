<?php function generaRicevutaHTML(array $dati): string
{
$data_pagamento = date('d/m/Y H:i', strtotime($dati['data_pagamento']));
$data_multa = date('d/m/Y', strtotime($dati['data_multa']));
$importo_formattato = number_format($dati['importo'], 2, ',', '.');

$html = <<<HTML
<!DOCTYPE html>
<html lang="it">
<body>

<h1>RICEVUTA DI PAGAMENTO</h1>
<p>Ricevuta N° {$dati['id_pagamento']}</p>

<p>Data Pagamento: {$data_pagamento}</p>
<p>Metodo di Pagamento: {$dati['metodo_pagamento']}</p>
<p>Operatore: {$dati['nome_bibliotecario']} {$dati['cognome_bibliotecario']}</p>

<h2>IMPORTO PAGATO</h2>
<p>€ {$importo_formattato}</p>

</body>
</html>
HTML;

return $html;
}
?>