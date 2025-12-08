<?php
// pages/top20.php - Top 20 Consultores com Impressão

require_once 'functions/vendas.php';

/**
 * Exibe nome do consultor com apelido (se existir) e tooltip com nome original
 */
function exibirNomeConsultor($nome_original) {
    $apelidos = carregarApelidosConsultores();

    if (isset($apelidos[$nome_original])) {
        $apelido = $apelidos[$nome_original]['apelido'];
        return '<span title="' . htmlspecialchars($nome_original) . '" data-toggle="tooltip" style="cursor: help; border-bottom: 1px dotted #999;">'
               . htmlspecialchars($apelido)
               . '</span>';
    }

    return htmlspecialchars($nome_original);
}

// Carrega configurações
$config = $_SESSION['config_sistema'] ?? carregarConfiguracoes();
$periodo_config = $config['periodo_relatorio'] ?? obterPeriodoRelatorio();
$campos_visiveis = $config['campos_visiveis_consultores'] ?? $_SESSION['campos_visiveis_consultores'];

// Inicializa variáveis
$vendas_processadas = null;
$mensagem_erro = null;

// Lista arquivos disponíveis
$arquivos_vendas = listarCSVs('vendas');

// Processa automaticamente com período configurado
if (!empty($arquivos_vendas)) {
    // Pega o arquivo mais recente
    $arquivo_selecionado = $arquivos_vendas[0]['caminho'];

    // Aplica filtros do admin
    $filtros = [
        'data_inicial' => $periodo_config['data_inicial'],
        'data_final' => $periodo_config['data_final'],
        'primeira_parcela_paga' => $periodo_config['apenas_primeira_parcela'] ?? false,
        'apenas_vista' => $periodo_config['apenas_vista'] ?? false,
        'status' => $periodo_config['filtro_status'] ?? 'Ativo'
    ];

    if (file_exists($arquivo_selecionado)) {
        $vendas_processadas = processarVendasComRanges($arquivo_selecionado, $filtros);

        // ===== APLICA REGRA DO DIA 08 (remove canceladas e sem 1ª parcela) =====
        $regra_dia08 = aplicarRegraDia08(
            $vendas_processadas['vendas'],
            $filtros['data_inicial'],
            $filtros['data_final']
        );

        // Se aplicou filtro, recalcula pontuação dos consultores
        if ($regra_dia08['aplicar_filtro']) {
            // Reagrupa vendas por consultor
            $vendas_processadas = processarVendasComRanges($arquivo_selecionado, $filtros);

            // Filtra novamente as vendas processadas
            $regra_dia08 = aplicarRegraDia08(
                $vendas_processadas['vendas'],
                $filtros['data_inicial'],
                $filtros['data_final']
            );
        }

        // Determina se é relatório FINAL ou TEMPORÁRIO
        $nome_arquivo_base = basename($arquivo_selecionado);
        $nome_amigavel = gerarNomeAmigavel($nome_arquivo_base);
        $tipo_relatorio = $regra_dia08['aplicar_filtro'] ? 'FINAL' : 'TEMPORÁRIO';

        // Ordena e pega top 20
        // Prioriza consultores com vendas ativas, depois por pontos
        usort($vendas_processadas['por_consultor'], function($a, $b) {
            // Prioridade 1: Consultores com vendas ativas no topo
            if ($a['vendas_ativas'] > 0 && $b['vendas_ativas'] == 0) return -1;
            if ($a['vendas_ativas'] == 0 && $b['vendas_ativas'] > 0) return 1;

            // Prioridade 2: Pontos
            if ($b['pontos'] != $a['pontos']) {
                return $b['pontos'] <=> $a['pontos'];
            }

            // Desempate: Quantidade
            return $b['quantidade'] <=> $a['quantidade'];
        });

        $vendas_processadas['por_consultor'] = array_slice($vendas_processadas['por_consultor'], 0, 20);
    } else {
        $mensagem_erro = "Arquivo não encontrado!";
    }
} else {
    $mensagem_erro = "Nenhum arquivo de vendas disponível!";
}

// Determina se DIP está ativo
$dip_ativo = ($_SESSION['config_premiacoes']['vendas_para_dip'] > 0 && 
              $_SESSION['config_premiacoes']['vendas_para_dip'] < 200);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Top 20 Consultores - Aquabeat</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        }

        .trophy-gold { color: #FFD700; }
        .trophy-silver { color: #C0C0C0; }
        .trophy-bronze { color: #CD7F32; }

        .container.mt-4 {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
        }

        .search-highlight {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }

        #searchInput {
            font-size: 1.1em;
            padding: 12px;
        }
    </style>
</head>
<body>

<div class="container mt-4">

    <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= $mensagem_erro ?>
        </div>
    <?php endif; ?>

    <?php if ($vendas_processadas): ?>
    
    <!-- Cabeçalho com Logo -->
    <div class="text-center mb-4">
        <?php if (temLogo()): ?>
            <img src="<?= getLogoURL() ?>" alt="Aquabeat" style="max-height: 100px;">
        <?php endif; ?>
        <h2 class="mt-3">Top 20 Consultores - Ranking</h2>
        <p class="text-muted">
            Período: <?= date('d/m/Y', strtotime($periodo_config['data_inicial'])) ?>
            até <?= date('d/m/Y', strtotime($periodo_config['data_final'])) ?>
        </p>
        <p class="text-info">
            <small>
                <i class="fas fa-info-circle"></i>
                Filtros aplicados: Status <?= $filtros['status'] ?>
                <?php if ($filtros['primeira_parcela_paga']): ?>
                    | Apenas 1ª Parcela Paga
                <?php endif; ?>
                <?php if ($filtros['apenas_vista']): ?>
                    | Apenas à Vista
                <?php endif; ?>
            </small>
        </p>
    </div>

    <!-- Notificação de Relatório FINAL ou TEMPORÁRIO -->
    <?php if (isset($nome_amigavel)): ?>
    <div class="alert alert-<?= $tipo_relatorio === 'FINAL' ? 'success' : 'warning' ?> mb-3">
        <h5>
            <i class="fas fa-<?= $tipo_relatorio === 'FINAL' ? 'check-circle' : 'clock' ?>"></i>
            Relatório: <strong><?= htmlspecialchars($nome_amigavel) ?></strong>
            <span class="badge badge-<?= $tipo_relatorio === 'FINAL' ? 'success' : 'warning' ?> ml-2"><?= $tipo_relatorio ?></span>
        </h5>

        <?php if ($tipo_relatorio === 'FINAL'): ?>
            <p class="mb-0">
                ✅ Este é o <strong>ranking oficial final</strong> para premiação.
                <?php if ($regra_dia08['removidas_canceladas'] > 0 || $regra_dia08['removidas_sem_pagamento'] > 0): ?>
                    <br>
                    <strong>Cotas removidas do ranking:</strong>
                    <?php if ($regra_dia08['removidas_canceladas'] > 0): ?>
                        <span class="badge badge-danger"><?= $regra_dia08['removidas_canceladas'] ?> canceladas</span>
                    <?php endif; ?>
                    <?php if ($regra_dia08['removidas_sem_pagamento'] > 0): ?>
                        <span class="badge badge-warning"><?= $regra_dia08['removidas_sem_pagamento'] ?> sem 1ª parcela</span>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="mb-0">
                ⚠️ <strong>Atenção:</strong> Este ranking é <strong>temporário</strong>!
                As posições e pontuações <strong>podem mudar</strong> até o dia 08 do próximo mês.
                <br>O ranking final será disponibilizado após essa data.
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Barra de Busca -->
    <div class="card mb-4 no-print">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="fas fa-search"></i> Buscar Consultor no Top 20
            </h5>
        </div>
        <div class="card-body">
            <div class="input-group input-group-lg">
                <div class="input-group-prepend">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                </div>
                <input type="text" class="form-control" id="searchInput"
                       placeholder="Digite o nome do consultor para encontrar sua posição...">
                <div class="input-group-append">
                    <button class="btn btn-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                </div>
            </div>
            <small class="text-muted mt-2 d-block">
                <i class="fas fa-lightbulb"></i>
                Digite qualquer parte do nome para filtrar e destacar a posição no ranking
            </small>
            <div id="searchResult" class="mt-2"></div>
        </div>
    </div>

    <!-- Botes de Ação -->
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-success btn-lg">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <a href="?page=home" class="btn btn-secondary btn-lg">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
    
    <!-- Tabela Top 20 -->
    <table class="table table-bordered table-hover" id="rankingTable">
        <thead class="thead-dark">
            <tr>
                <th class="text-center">Pos.</th>
                <th>Consultor</th>
                <?php if ($campos_visiveis['pontos']): ?>
                    <th class="text-center">Pontos</th>
                <?php endif; ?>
                <?php if ($campos_visiveis['vendas']): ?>
                    <th class="text-center">Vendas</th>
                <?php endif; ?>
                <?php if ($campos_visiveis['valor_total']): ?>
                    <th class="text-right">Valor Total</th>
                <?php endif; ?>
                <?php if ($campos_visiveis['valor_pago']): ?>
                    <th class="text-right">Valor Pago</th>
                <?php endif; ?>
                <?php if ($campos_visiveis['cotas_sap']): ?>
                    <th class="text-center">SAPs</th>
                    <?php if ($dip_ativo): ?>
                        <th class="text-center">DIPs</th>
                    <?php endif; ?>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vendas_processadas['por_consultor'] as $index => $consultor): ?>
            <tr data-consultor="<?= strtolower(htmlspecialchars($consultor['consultor'])) ?>">
                <td class="text-center">
                    <strong><?= $index + 1 ?>º</strong>
                    <?php if ($index < 3): ?>
                        <i class="fas fa-trophy <?= ['trophy-gold', 'trophy-silver', 'trophy-bronze'][$index] ?>"></i>
                    <?php endif; ?>
                </td>
                <td><strong><?= exibirNomeConsultor($consultor['consultor']) ?></strong></td>

                <?php if ($campos_visiveis['pontos']): ?>
                    <td class="text-center">
                        <span class="badge badge-primary"><?= $consultor['pontos'] ?> pts</span>
                    </td>
                <?php endif; ?>
                
                <?php if ($campos_visiveis['vendas']): ?>
                    <td class="text-center"><?= $consultor['quantidade'] ?></td>
                <?php endif; ?>
                
                <?php if ($campos_visiveis['valor_total']): ?>
                    <td class="text-right">R$ <?= number_format($consultor['venda'], 2, ',', '.') ?></td>
                <?php endif; ?>
                
                <?php if ($campos_visiveis['valor_pago']): ?>
                    <td class="text-right">R$ <?= number_format($consultor['pago'], 2, ',', '.') ?></td>
                <?php endif; ?>
                
                <?php if ($campos_visiveis['cotas_sap']): ?>
                    <td class="text-center">
                        <strong><?= $consultor['saps'] ?></strong>
                    </td>
                    <?php if ($dip_ativo): ?>
                    <td class="text-center">
                        <strong><?= $consultor['dips'] ?></strong>
                    </td>
                    <?php endif; ?>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    
    
    <?php endif; ?>

</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script de Busca -->
<script>
$(document).ready(function() {
    const $searchInput = $('#searchInput');
    const $clearBtn = $('#clearSearch');
    const $searchResult = $('#searchResult');
    const $tableRows = $('#rankingTable tbody tr');

    // Função de busca
    function performSearch() {
        const searchTerm = $searchInput.val().toLowerCase().trim();

        // Remove destaques anteriores
        $tableRows.removeClass('search-highlight');

        if (searchTerm === '') {
            // Mostra todas as linhas
            $tableRows.show();
            $searchResult.html('');
            return;
        }

        let foundCount = 0;
        let firstMatch = null;

        $tableRows.each(function() {
            const $row = $(this);
            const consultorName = $row.data('consultor');

            if (consultorName.indexOf(searchTerm) !== -1) {
                $row.show();
                $row.addClass('search-highlight');
                foundCount++;

                if (!firstMatch) {
                    firstMatch = $row;
                }
            } else {
                $row.hide();
            }
        });

        // Atualiza mensagem de resultado
        if (foundCount > 0) {
            $searchResult.html(
                `<div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle"></i>
                    <strong>${foundCount}</strong> consultor(es) encontrado(s)
                </div>`
            );

            // Scroll até o primeiro resultado
            if (firstMatch) {
                $('html, body').animate({
                    scrollTop: firstMatch.offset().top - 150
                }, 500);
            }
        } else {
            $searchResult.html(
                `<div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    Nenhum consultor encontrado com "<strong>${$searchInput.val()}</strong>"
                </div>`
            );
        }
    }

    // Eventos
    $searchInput.on('keyup', performSearch);

    $clearBtn.on('click', function() {
        $searchInput.val('');
        performSearch();
        $searchInput.focus();
    });

    // Enter para buscar
    $searchInput.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            performSearch();
        }
    });
});
</script>

<!-- Fogos de Artifício e Mensagem para Relatório FINAL -->
<?php if (isset($tipo_relatorio) && $tipo_relatorio === 'FINAL'): ?>
    <?php $mensagem_parabens = obterMensagemAleatoriaParabens(); ?>

    <style>
        #fireworksCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9998;
        }

        #congratsModal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.5);
            text-align: center;
            max-width: 600px;
            animation: modalBounce 0.8s ease-out;
        }

        @keyframes modalBounce {
            0% { transform: translate(-50%, -50%) scale(0.3); opacity: 0; }
            50% { transform: translate(-50%, -50%) scale(1.05); }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }

        #congratsModal h2 {
            font-size: 2.5em;
            color: #28a745;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        #congratsModal p {
            font-size: 1.3em;
            color: #333;
            margin-bottom: 30px;
        }

        .trophy-animation {
            font-size: 5em;
            animation: trophyFloat 2s ease-in-out infinite;
        }

        @keyframes trophyFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        @media print {
            #fireworksCanvas, #congratsModal { display: none !important; }
        }
    </style>

    <canvas id="fireworksCanvas"></canvas>
    <div id="congratsModal" style="display: none;">
        <div class="trophy-animation">🏆</div>
        <h2><?= htmlspecialchars($mensagem_parabens['titulo']) ?></h2>
        <p><?= htmlspecialchars($mensagem_parabens['mensagem']) ?></p>
        <button onclick="closeCongratsModal()" class="btn btn-success btn-lg">
            <i class="fas fa-check"></i> Continuar
        </button>
    </div>

    <script>
        // Classe para Partícula de Fogo de Artifício
        class FireworkParticle {
            constructor(x, y, color) {
                this.x = x;
                this.y = y;
                this.color = color;
                this.velocity = {
                    x: (Math.random() - 0.5) * 8,
                    y: (Math.random() - 0.5) * 8
                };
                this.gravity = 0.15;
                this.opacity = 1;
                this.decay = Math.random() * 0.015 + 0.010;
            }

            update() {
                this.velocity.y += this.gravity;
                this.x += this.velocity.x;
                this.y += this.velocity.y;
                this.opacity -= this.decay;
            }

            draw(ctx) {
                ctx.save();
                ctx.globalAlpha = this.opacity;
                ctx.fillStyle = this.color;
                ctx.beginPath();
                ctx.arc(this.x, this.y, 3, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();
            }
        }

        // Configuração do Canvas
        const canvas = document.getElementById('fireworksCanvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        let particles = [];
        let fireworksActive = true;
        let animationId;

        // Cores vibrantes para fogos
        const colors = ['#ff0844', '#ffb900', '#00e5ff', '#00ff88', '#b900ff', '#ff006e'];

        // Criar explosão de fogos
        function createFirework(x, y) {
            const particleCount = 50;
            const color = colors[Math.floor(Math.random() * colors.length)];

            for (let i = 0; i < particleCount; i++) {
                particles.push(new FireworkParticle(x, y, color));
            }
        }

        // Animação dos fogos
        function animate() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Atualizar e desenhar partículas
            particles = particles.filter(particle => {
                particle.update();
                particle.draw(ctx);
                return particle.opacity > 0;
            });

            // Criar novos fogos aleatoriamente
            if (fireworksActive && Math.random() < 0.08) {
                const x = Math.random() * canvas.width;
                const y = Math.random() * canvas.height * 0.5;
                createFirework(x, y);
            }

            if (fireworksActive) {
                animationId = requestAnimationFrame(animate);
            }
        }

        // Fechar modal e parar fogos
        function closeCongratsModal() {
            document.getElementById('congratsModal').style.display = 'none';
            fireworksActive = false;
            if (animationId) {
                cancelAnimationFrame(animationId);
            }
            // Fade out do canvas
            setTimeout(() => {
                canvas.style.transition = 'opacity 1s';
                canvas.style.opacity = '0';
                setTimeout(() => canvas.remove(), 1000);
            }, 100);
        }

        // Iniciar animação quando página carregar
        window.addEventListener('load', function() {
            // Aguarda 500ms para garantir que a página carregou
            setTimeout(() => {
                // Mostrar modal
                document.getElementById('congratsModal').style.display = 'block';

                // Iniciar fogos
                animate();

                // Fechar automaticamente após 8 segundos
                setTimeout(closeCongratsModal, 8000);
            }, 500);
        });

        // Redimensionar canvas
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    </script>
<?php endif; ?>

</body>
</html>