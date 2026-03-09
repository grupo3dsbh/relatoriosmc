<?php
/**
 * Auto-save para configurações
 * Salva automaticamente quando um campo é alterado
 */

session_start();
require_once '../config.php';
require_once '../functions/configuracoes.php';

header('Content-Type: application/json');

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

// Recebe os dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Carrega configurações atuais
$config = carregarConfiguracoes();

// Garante que os arrays necessários existam
if (!isset($config['ranges'])) {
    $config['ranges'] = [];
}
if (!isset($config['tipos_premiacao'])) {
    $config['tipos_premiacao'] = [];
}

// Processa de acordo com o tipo de campo
$fieldType = $data['field_type'] ?? '';
$fieldName = $data['field_name'] ?? '';
$fieldValue = $data['field_value'] ?? '';

try {
    switch ($fieldType) {
        case 'acesso':
            // Campos de acesso
            if (!isset($config['acesso'])) {
                $config['acesso'] = [];
            }

            switch ($fieldName) {
                case 'relatorio_padrao':
                    $config['acesso']['relatorio_padrao'] = $fieldValue;
                    break;
                case 'senha_godmode':
                    $config['acesso']['senha_godmode'] = trim($fieldValue);
                    break;
                case 'senha_admin_setores':
                    $config['acesso']['senha_admin_setores'] = trim($fieldValue);
                    break;
                case 'senha_filtro':
                    $config['acesso']['senha_filtro'] = trim($fieldValue);
                    break;
                case 'manutencao_ativo':
                    $config['acesso']['manutencao_ativo'] = (bool)$fieldValue;
                    break;
                case 'manutencao_permitir_godmode':
                    $config['acesso']['manutencao_permitir_godmode'] = (bool)$fieldValue;
                    break;
            }
            break;

        case 'periodo':
            // Campos de período
            if (!isset($config['periodo_relatorio'])) {
                $config['periodo_relatorio'] = [];
            }

            switch ($fieldName) {
                case 'data_inicial':
                    $config['periodo_relatorio']['data_inicial'] = $fieldValue;
                    break;
                case 'data_final':
                    $config['periodo_relatorio']['data_final'] = $fieldValue;
                    break;
                case 'status':
                    $config['periodo_relatorio']['filtro_status'] = $fieldValue;
                    break;
                case 'primeira_parcela':
                    $config['periodo_relatorio']['apenas_primeira_parcela'] = (bool)$fieldValue;
                    break;
                case 'apenas_vista':
                    $config['periodo_relatorio']['apenas_vista'] = (bool)$fieldValue;
                    break;
            }
            break;

        case 'pontos_padrao':
            // Pontos padrão
            if (!isset($config['pontos_padrao'])) {
                $config['pontos_padrao'] = [];
            }
            $config['pontos_padrao'][$fieldName] = intval($fieldValue);
            break;

        case 'premiacao':
            // Configurações de premiação
            if (!isset($config['premiacao'])) {
                $config['premiacao'] = [];
            }

            switch ($fieldName) {
                case 'mensagem':
                    $config['premiacao']['mensagem'] = trim($fieldValue);
                    break;
                case 'dia_limite_primeira_parcela':
                    $config['premiacao']['dia_limite_primeira_parcela'] = intval($fieldValue);
                    break;
                case 'exibir_aviso':
                    $config['premiacao']['exibir_aviso'] = (bool)$fieldValue;
                    break;
            }
            break;

        case 'campos_visiveis':
            // Campos visíveis para consultores
            if (!isset($config['campos_visiveis_consultores'])) {
                $config['campos_visiveis_consultores'] = [];
            }
            $config['campos_visiveis_consultores'][$fieldName] = (bool)$fieldValue;
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de campo não reconhecido']);
            exit;
    }

    // Salva as configurações
    $resultado = salvarConfiguracoes($config);

    if ($resultado['sucesso']) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuração salva automaticamente',
            'field_name' => $fieldName,
            'field_value' => $fieldValue
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $resultado['mensagem']
        ]);
    }

} catch (Exception $e) {
    error_log("Erro no auto-save: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $e->getMessage()
    ]);
}
?>
