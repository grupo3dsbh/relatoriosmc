<?php
session_start();
require_once 'config.php';
require_once 'functions/configuracoes.php';
require_once 'functions/vendas.php';

// Limpa sessão para forçar reload
session_unset();
$_SESSION = [];

// Carrega config atualizado
$config = carregarConfiguracoes();

echo "<h2>DEBUG - Configuração de Pontuação</h2>";
echo "<h3>Config carregado do arquivo:</h3>";
echo "<pre>";
print_r($config);
echo "</pre>";

echo "<h3>Pontos Padrão:</h3>";
echo "<pre>";
print_r($config['pontos_padrao'] ?? 'NÃO ENCONTRADO');
echo "</pre>";

echo "<h3>Ranges configurados:</h3>";
echo "<pre>";
print_r($config['ranges'] ?? 'NÃO ENCONTRADO');
echo "</pre>";

if (isset($config['ranges'][0])) {
    $range = $config['ranges'][0];
    echo "<h3>Black Week - Detalhes:</h3>";
    echo "Nome: " . $range['nome'] . "<br>";
    echo "Ativo: " . ($range['ativo'] ? 'SIM' : 'NÃO') . "<br>";
    echo "Período: " . $range['data_inicio'] . " até " . $range['data_fim'] . "<br>";

    if (isset($range['pontos'])) {
        echo "<h4>Pontos configurados:</h4><pre>";
        print_r($range['pontos']);
        echo "</pre>";
    }
}

// Testa conversão
echo "<h3>Teste de Conversão de Formato:</h3>";
if (isset($config['ranges'][0]['pontos'])) {
    $pontos_convertidos = converterFormatoRange($config['ranges'][0]['pontos']);
    echo "<pre>";
    print_r($pontos_convertidos);
    echo "</pre>";
}

// Testa vendas específicas
echo "<h3>Teste de Vendas Específicas:</h3>";

$vendas_teste = [
    ['data' => '2025-11-20 10:00:00', 'vagas' => 3, 'periodo' => 'Padrão'],
    ['data' => '2025-11-22 09:00:00', 'vagas' => 4, 'periodo' => 'Black Week'],
    ['data' => '2025-11-28 13:00:00', 'vagas' => 2, 'periodo' => 'Black Week'],
];

foreach ($vendas_teste as $teste) {
    $range_identificado = identificarRange($teste['data']);
    $config_pontos_raw = obterConfiguracaoPontosPorData($teste['data']);

    $tem_indices_numericos = isset($config_pontos_raw[1]) && is_numeric(key($config_pontos_raw));
    if ($tem_indices_numericos) {
        $config_pontos = converterPontosPadrao($config_pontos_raw);
    } else {
        $config_pontos = converterFormatoRange($config_pontos_raw);
    }

    $categoria = determinarCategoria($teste['vagas'], false);
    $pontos = obterPontosPorCategoria($categoria, $config_pontos);

    echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>";
    echo "<strong>Venda: {$teste['vagas']} vagas - {$teste['data']}</strong><br>";
    echo "Período esperado: {$teste['periodo']}<br>";
    echo "Período identificado: <strong style='color:" . ($range_identificado === $teste['periodo'] ? 'green' : 'red') . "'>{$range_identificado}</strong><br>";
    echo "Categoria: {$categoria}<br>";
    echo "Pontos calculados: <strong>{$pontos}</strong><br>";
    echo "Config usada: <pre style='font-size:10px;'>" . print_r($config_pontos, true) . "</pre>";
    echo "</div>";
}
?>
