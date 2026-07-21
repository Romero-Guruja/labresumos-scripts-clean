<?php
/**
 * WPCode snippet #1283 — Mensagem de Acesso aos Materiais - Página Minha Conta
 * location: everywhere | auto_insert: 1 | priority: 10
 * Espelho read-only exportado de wp_options.wpcode_snippets em 2026-07-21.
 * Fonte de runtime AINDA e o wp_options (nao este arquivo) - ver README.md.
 */
/**
 * Snippet: Banner de Acesso aos Materiais - HERO (v5)
 * Com paleta de cores oficial Lab Resumos
 * 
 * Inserir via WPCode
 * Local: Página "Minha Conta" do WooCommerce
 */

// Remove versões anteriores
remove_action('woocommerce_account_dashboard', 'lab_banner_acesso_materiais', 5);
remove_action('woocommerce_account_dashboard', 'lab_banner_acesso_materiais_v2', 5);
remove_action('woocommerce_account_dashboard', 'lab_banner_acesso_materiais_v3', 5);
remove_action('woocommerce_before_account_navigation', 'lab_banner_hero_materiais', 5);

// Adiciona ANTES do conteúdo padrão do WooCommerce
add_action('woocommerce_before_account_navigation', 'lab_banner_hero_materiais_v5', 5);
function lab_banner_hero_materiais_v5() {
    $user = wp_get_current_user();
    $primeiro_nome = $user->first_name ?: $user->display_name;
    ?>
    <div class="lab-hero-wrapper">
        <div class="lab-hero-card">
            <!-- Background Elements -->
            <div class="lab-hero-bg">
                <div class="lab-hero-gradient"></div>
                <div class="lab-hero-pattern"></div>
                <div class="lab-hero-glow lab-hero-glow-1"></div>
                <div class="lab-hero-glow lab-hero-glow-2"></div>
            </div>
            
            <!-- Content -->
            <div class="lab-hero-content">
                <div class="lab-hero-left">
                    <div class="lab-hero-badge">
                        <span class="lab-hero-badge-dot"></span>
                        Área do Aluno
                    </div>
                    
                    <h1 class="lab-hero-title">
                        Olá, <span class="lab-hero-name"><?php echo esc_html($primeiro_nome); ?></span>! 👋
                    </h1>
                    
                    <p class="lab-hero-subtitle">
                        Seus materiais de estudo estão prontos e te esperando. 
                        Acesse agora seus <strong>resumos</strong>, <strong>flashcards</strong> e <strong>questões comentadas</strong>.
                    </p>
                    
                    <div class="lab-hero-actions">
                        <a href="https://aluno.labresumos.com.br/my/courses.php" target="_blank" class="lab-hero-btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
                            </svg>
                            Acessar Meus Materiais
                            <svg class="lab-hero-arrow" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                        
                        <div class="lab-hero-hint">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 16v-4M12 8h.01"/>
                            </svg>
                            Acesso exclusivo para alunos
                        </div>
                    </div>
                </div>
                
                <div class="lab-hero-right">
                    <div class="lab-hero-illustration">
                        <div class="lab-hero-card-stack">
                            <div class="lab-hero-card-item lab-hero-card-1">
                                <div class="lab-hero-card-icon">📚</div>
                                <span>Resumos</span>
                            </div>
                            <div class="lab-hero-card-item lab-hero-card-2">
                                <div class="lab-hero-card-icon">🎴</div>
                                <span>Flashcards</span>
                            </div>
                            <div class="lab-hero-card-item lab-hero-card-3">
                                <div class="lab-hero-card-icon">✍️</div>
                                <span>Questões</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* 
     * Paleta Lab Resumos:
     * Amarelo Queimado: #F1CC00
     * Amarelo Vibrante: #FEEF4C
     * Preto Profundo: #333B49
     * Azul Celeste: #0475CF
     * Branco Gelo: #F3F1E8
     * Azul Claro: #A0DDFC
     * Azul Turquesa: #2EBAE5
     */

    /* Wrapper */
    .lab-hero-wrapper {
        width: 100%;
        margin-bottom: 40px;
    }

    /* Card Principal - Preto Profundo como base */
    .lab-hero-card {
        position: relative;
        background: linear-gradient(145deg, #333B49 0%, #3d4556 50%, #333B49 100%);
        border-radius: 24px;
        padding: 48px 56px;
        overflow: hidden;
        box-shadow: 
            0 20px 60px rgba(51, 59, 73, 0.4),
            0 8px 24px rgba(0, 0, 0, 0.2),
            inset 0 1px 0 rgba(255, 255, 255, 0.05);
    }

    /* Background Elements */
    .lab-hero-bg {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
    }

    .lab-hero-gradient {
        position: absolute;
        top: 0;
        right: 0;
        width: 60%;
        height: 100%;
        background: linear-gradient(135deg, transparent 0%, rgba(46, 186, 229, 0.08) 100%);
    }

    .lab-hero-pattern {
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        background-size: 24px 24px;
    }

    .lab-hero-glow {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
    }

    /* Glow com Amarelo Queimado */
    .lab-hero-glow-1 {
        width: 300px;
        height: 300px;
        top: -100px;
        right: 10%;
        background: rgba(241, 204, 0, 0.2);
    }

    /* Glow com Azul Turquesa */
    .lab-hero-glow-2 {
        width: 200px;
        height: 200px;
        bottom: -80px;
        left: 20%;
        background: rgba(46, 186, 229, 0.15);
    }

    /* Content Layout */
    .lab-hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 48px;
    }

    .lab-hero-left {
        flex: 1;
        max-width: 580px;
    }

    .lab-hero-right {
        flex-shrink: 0;
    }

    /* Badge - Azul Celeste */
    .lab-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: rgba(4, 117, 207, 0.2);
        border: 1px solid rgba(4, 117, 207, 0.4);
        color: #A0DDFC;
        font-size: 0.8em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        padding: 10px 18px;
        border-radius: 50px;
        margin-bottom: 24px;
    }

    /* Dot com Azul Turquesa */
    .lab-hero-badge-dot {
        width: 8px;
        height: 8px;
        background: #2EBAE5;
        border-radius: 50%;
        animation: lab-pulse 2s ease-in-out infinite;
        box-shadow: 0 0 12px rgba(46, 186, 229, 0.6);
    }

    @keyframes lab-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.3); }
    }

    /* Title */
    .lab-hero-title {
        color: #ffffff;
        font-size: 2.5em;
        font-weight: 700;
        line-height: 1.2;
        margin: 0 0 16px 0;
        letter-spacing: -0.03em;
    }

    /* Nome com gradiente Amarelo */
    .lab-hero-name {
        background: linear-gradient(135deg, #F1CC00 0%, #FEEF4C 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .lab-hero-wrapper .lab-hero-subtitle {
        color: rgba(255, 255, 255, 0.85);
        font-size: 1.15em;
        line-height: 1.7;
        margin: 0 0 32px 0;
    }

    .lab-hero-wrapper .lab-hero-subtitle strong {
        color: #FEEF4C !important;
        font-weight: 600;
    }

    /* Actions */
    .lab-hero-actions {
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
    }

    /* Botão com Amarelo Queimado */
    .lab-hero-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, #F1CC00 0%, #e0bd00 100%);
        color: #333B49 !important;
        font-weight: 700;
        font-size: 1.05em;
        padding: 18px 32px;
        border-radius: 14px;
        text-decoration: none !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 
            0 8px 24px rgba(241, 204, 0, 0.35),
            0 0 0 0 rgba(241, 204, 0, 0.4);
    }

    .lab-hero-btn-primary:hover {
        transform: translateY(-3px);
        background: linear-gradient(135deg, #FEEF4C 0%, #F1CC00 100%);
        box-shadow: 
            0 12px 32px rgba(241, 204, 0, 0.5),
            0 0 0 4px rgba(241, 204, 0, 0.2);
        color: #333B49 !important;
    }

    .lab-hero-arrow {
        transition: transform 0.3s ease;
        stroke: #333B49;
    }

    .lab-hero-btn-primary:hover .lab-hero-arrow {
        transform: translateX(4px);
    }

    .lab-hero-hint {
        display: flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85em;
    }

    /* Illustration - Card Stack */
    .lab-hero-illustration {
        width: 280px;
        height: 220px;
        position: relative;
    }

    .lab-hero-card-stack {
        position: relative;
        width: 100%;
        height: 100%;
    }

    /* Cards com Branco Gelo */
    .lab-hero-card-item {
        position: absolute;
        background: #F3F1E8;
        border-radius: 16px;
        padding: 20px 28px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        transition: transform 0.3s ease;
    }

    /* Texto dos cards em Preto Profundo */
    .lab-hero-card-item span {
        font-weight: 600;
        color: #333B49;
        font-size: 1em;
    }

    .lab-hero-card-icon {
        font-size: 1.5em;
    }

    .lab-hero-card-1 {
        top: 0;
        left: 0;
        transform: rotate(-3deg);
        z-index: 3;
    }

    .lab-hero-card-2 {
        top: 70px;
        left: 40px;
        transform: rotate(2deg);
        z-index: 2;
    }

    .lab-hero-card-3 {
        top: 140px;
        left: 10px;
        transform: rotate(-1deg);
        z-index: 1;
    }

    .lab-hero-card:hover .lab-hero-card-1 {
        transform: rotate(-3deg) translateY(-5px);
    }

    .lab-hero-card:hover .lab-hero-card-2 {
        transform: rotate(2deg) translateX(10px);
    }

    .lab-hero-card:hover .lab-hero-card-3 {
        transform: rotate(-1deg) translateY(5px);
    }

    /* Responsivo */
    @media (max-width: 1024px) {
        .lab-hero-card {
            padding: 40px 36px;
        }

        .lab-hero-right {
            display: none;
        }

        .lab-hero-title {
            font-size: 2em;
        }
    }

    @media (max-width: 640px) {
        .lab-hero-card {
            padding: 32px 24px;
            border-radius: 20px;
        }

        .lab-hero-badge {
            font-size: 0.7em;
            padding: 8px 14px;
        }

        .lab-hero-title {
            font-size: 1.6em;
        }

        .lab-hero-subtitle {
            font-size: 1em;
        }

        .lab-hero-actions {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .lab-hero-btn-primary {
            justify-content: center;
            padding: 18px 24px;
            font-size: 1em;
        }

        .lab-hero-hint {
            justify-content: center;
        }
    }
    </style>
    <?php
}