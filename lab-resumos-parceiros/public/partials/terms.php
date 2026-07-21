<?php
/**
 * Template dos Termos de Afiliação
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$terms_manager = LRP_Terms::instance();
$terms = $terms_manager->get_terms();
$current_version = $terms['version'];
$sections = $terms['sections'];

// Verifica se é afiliado logado
$is_affiliate = false;
$has_accepted = false;
$affiliate = null;

if (is_user_logged_in()) {
    $affiliate = LRP_Affiliate::get_by_user_id(get_current_user_id());
    if ($affiliate && $affiliate->is_active()) {
        $is_affiliate = true;
        $has_accepted = $terms_manager->has_accepted_current($affiliate->get_id());
    }
}

// Verifica se há notificação de aceite necessário
$show_acceptance_required = $is_affiliate && !$has_accepted;
?>

<div class="lrp-terms-wrapper">
    <!-- Header -->
    <header class="lrp-terms-header">
        <div class="lrp-terms-header-content">
            <div class="lrp-terms-badge">
                <span class="lrp-terms-icon">📋</span>
                <span class="lrp-terms-version">Versão <?php echo esc_html($current_version); ?></span>
            </div>
            <h1 class="lrp-terms-title"><?php echo esc_html($terms['title']); ?></h1>
            <p class="lrp-terms-subtitle">Programa de Parceiros Lab Resumos</p>
            <p class="lrp-terms-updated">
                Última atualização: <?php echo date_i18n('d \d\e F \d\e Y', strtotime($terms['created_at'])); ?>
            </p>
        </div>
        
        <?php if ($show_acceptance_required) : ?>
        <div class="lrp-terms-alert lrp-terms-alert-warning">
            <span class="lrp-alert-icon">⚠️</span>
            <div class="lrp-alert-content">
                <strong>Ação necessária</strong>
                <p>Por favor, leia os termos e clique em "Aceitar" ao final da página para continuar usando o programa.</p>
            </div>
        </div>
        <?php elseif ($has_accepted) : ?>
        <div class="lrp-terms-alert lrp-terms-alert-success">
            <span class="lrp-alert-icon">✅</span>
            <div class="lrp-alert-content">
                <strong>Termos aceitos</strong>
                <p>Você já aceitou esta versão dos termos.</p>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <div class="lrp-terms-layout">
        <!-- Sidebar com Sumário -->
        <aside class="lrp-terms-sidebar">
            <div class="lrp-toc-container">
                <h3 class="lrp-toc-title">
                    <span class="lrp-toc-icon">📑</span>
                    Sumário
                </h3>
                <nav class="lrp-toc">
                    <ul class="lrp-toc-list">
                        <?php foreach ($sections as $index => $section) : ?>
                        <li class="lrp-toc-item">
                            <a href="#<?php echo esc_attr($section['id']); ?>" class="lrp-toc-link" data-section="<?php echo esc_attr($section['id']); ?>">
                                <span class="lrp-toc-number"><?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></span>
                                <span class="lrp-toc-text"><?php echo esc_html(preg_replace('/^\d+\.\s*/', '', $section['title'])); ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                
                <div class="lrp-toc-progress">
                    <div class="lrp-toc-progress-bar">
                        <div class="lrp-toc-progress-fill" id="reading-progress"></div>
                    </div>
                    <span class="lrp-toc-progress-text">Progresso de leitura</span>
                </div>
            </div>
        </aside>

        <!-- Conteúdo Principal -->
        <main class="lrp-terms-content">
            <!-- Introdução -->
            <?php if (!empty($terms['intro'])) : ?>
            <section class="lrp-terms-intro">
                <div class="lrp-intro-card">
                    <div class="lrp-intro-icon">👋</div>
                    <div class="lrp-intro-text">
                        <?php echo wp_kses_post($terms['intro']); ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Seções -->
            <?php foreach ($sections as $section) : ?>
            <section id="<?php echo esc_attr($section['id']); ?>" class="lrp-terms-section" data-section-id="<?php echo esc_attr($section['id']); ?>">
                <div class="lrp-section-header">
                    <h2 class="lrp-section-title">
                        <?php echo esc_html($section['title']); ?>
                    </h2>
                    <a href="#<?php echo esc_attr($section['id']); ?>" class="lrp-section-anchor" title="Link para esta seção">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                    </a>
                </div>
                <div class="lrp-section-content">
                    <?php echo wp_kses_post($section['content']); ?>
                </div>
            </section>
            <?php endforeach; ?>

            <!-- Box de Aceite -->
            <?php if ($is_affiliate) : ?>
            <section class="lrp-terms-acceptance">
                <?php if ($has_accepted) : ?>
                <div class="lrp-acceptance-done">
                    <div class="lrp-acceptance-icon">✅</div>
                    <h3>Você já aceitou estes termos</h3>
                    <p>Obrigado por fazer parte do Programa de Parceiros Lab Resumos!</p>
                    <?php
                    $history = $terms_manager->get_acceptance_history($affiliate->get_id());
                    $last_acceptance = !empty($history) ? $history[0] : null;
                    if ($last_acceptance) :
                    ?>
                    <div class="lrp-acceptance-info">
                        <p><strong>Versão aceita:</strong> <?php echo esc_html($last_acceptance['version']); ?></p>
                        <p><strong>Data do aceite:</strong> <?php echo date_i18n('d/m/Y \à\s H:i', strtotime($last_acceptance['accepted_at'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(get_permalink(get_option('lrp_dashboard_page_id'))); ?>" class="lrp-btn lrp-btn-primary">
                        ← Voltar ao Painel
                    </a>
                </div>
                <?php else : ?>
                <div class="lrp-acceptance-form">
                    <div class="lrp-acceptance-header">
                        <div class="lrp-acceptance-icon">🤝</div>
                        <h3>Aceite os Termos para Continuar</h3>
                        <p>Ao aceitar, você confirma que leu e concorda com todos os termos e condições acima.</p>
                    </div>
                    
                    <form id="lrp-terms-acceptance-form" method="post">
                        <?php wp_nonce_field('lrp_accept_terms', 'lrp_terms_nonce'); ?>
                        <input type="hidden" name="action" value="lrp_accept_terms">
                        <input type="hidden" name="version" value="<?php echo esc_attr($current_version); ?>">
                        
                        <div class="lrp-checkbox-fancy">
                            <input type="checkbox" id="lrp-terms-checkbox" name="accept_terms" required>
                            <label for="lrp-terms-checkbox">
                                <span class="lrp-checkbox-icon"></span>
                                <span class="lrp-checkbox-text">
                                    Li e concordo com os <strong>Termos e Condições do Programa de Parceiros Lab Resumos</strong> (versão <?php echo esc_html($current_version); ?>)
                                </span>
                            </label>
                        </div>
                        
                        <div class="lrp-acceptance-actions">
                            <button type="submit" class="lrp-btn lrp-btn-accept" id="lrp-accept-btn" disabled>
                                <span class="lrp-btn-text">✅ Aceitar Termos</span>
                                <span class="lrp-btn-loading" style="display: none;">
                                    <span class="lrp-spinner"></span> Processando...
                                </span>
                            </button>
                        </div>
                        
                        <p class="lrp-acceptance-note">
                            <span class="lrp-note-icon">📧</span>
                            Você receberá um email de confirmação do aceite.
                        </p>
                    </form>
                </div>
                <?php endif; ?>
            </section>
            <?php else : ?>
            <!-- Não é afiliado -->
            <section class="lrp-terms-cta">
                <div class="lrp-cta-card">
                    <div class="lrp-cta-icon">🚀</div>
                    <h3>Quer ser um Parceiro Lab Resumos?</h3>
                    <p>Junte-se ao nosso programa de afiliados e ganhe comissões por cada venda indicada!</p>
                    <div class="lrp-cta-actions">
                        <?php if (!is_user_logged_in()) : ?>
                        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="lrp-btn lrp-btn-secondary">
                            Fazer Login
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(get_permalink(get_option('lrp_registration_page_id'))); ?>" class="lrp-btn lrp-btn-primary">
                            Quero ser Parceiro →
                        </a>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Footer com informações legais -->
            <footer class="lrp-terms-footer">
                <div class="lrp-footer-info">
                    <p><strong>Lab Resumos</strong> - SOLUÇÕES EDUCACIONAIS INTELIGENTES LTDA</p>
                    <p>Este documento é válido como contrato digital conforme a MP 2.200-2/2001.</p>
                </div>
                <div class="lrp-footer-actions">
                    <button type="button" class="lrp-btn lrp-btn-outline" onclick="window.print()">
                        🖨️ Imprimir
                    </button>
                </div>
            </footer>
        </main>
    </div>
</div>

<script>
(function() {
    // Progresso de leitura
    const progressBar = document.getElementById('reading-progress');
    const sections = document.querySelectorAll('.lrp-terms-section');
    const tocLinks = document.querySelectorAll('.lrp-toc-link');
    
    // Atualiza barra de progresso e seção ativa
    function updateProgress() {
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const progress = (scrollTop / docHeight) * 100;
        
        if (progressBar) {
            progressBar.style.width = Math.min(progress, 100) + '%';
        }
        
        // Atualiza link ativo no sumário
        let currentSection = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop - 150;
            if (scrollTop >= sectionTop) {
                currentSection = section.getAttribute('data-section-id');
            }
        });
        
        tocLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('data-section') === currentSection) {
                link.classList.add('active');
            }
        });
    }
    
    window.addEventListener('scroll', updateProgress);
    updateProgress();
    
    // Scroll suave para links do sumário
    tocLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);
            if (targetSection) {
                targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Atualiza URL sem reload
                history.pushState(null, null, '#' + targetId);
            }
        });
    });
    
    // Checkbox habilita botão de aceite
    const checkbox = document.getElementById('lrp-terms-checkbox');
    const acceptBtn = document.getElementById('lrp-accept-btn');
    
    if (checkbox && acceptBtn) {
        checkbox.addEventListener('change', function() {
            acceptBtn.disabled = !this.checked;
        });
    }
    
    // Form de aceite via AJAX
    const form = document.getElementById('lrp-terms-acceptance-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnText = acceptBtn.querySelector('.lrp-btn-text');
            const btnLoading = acceptBtn.querySelector('.lrp-btn-loading');
            
            // Mostra loading
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';
            acceptBtn.disabled = true;
            
            // Envia via AJAX
            const formData = new FormData(form);
            formData.append('action', 'lrp_accept_terms');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recarrega a página para mostrar confirmação
                    window.location.reload();
                } else {
                    alert(data.data || 'Erro ao aceitar termos. Tente novamente.');
                    btnText.style.display = 'inline';
                    btnLoading.style.display = 'none';
                    acceptBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar. Tente novamente.');
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                acceptBtn.disabled = false;
            });
        });
    }
    
    // Sticky sidebar
    const sidebar = document.querySelector('.lrp-terms-sidebar');
    const tocContainer = document.querySelector('.lrp-toc-container');
    
    if (sidebar && tocContainer && window.innerWidth > 992) {
        const sidebarTop = sidebar.offsetTop;
        
        window.addEventListener('scroll', function() {
            if (window.scrollY > sidebarTop - 20) {
                tocContainer.classList.add('sticky');
            } else {
                tocContainer.classList.remove('sticky');
            }
        });
    }
})();
</script>
