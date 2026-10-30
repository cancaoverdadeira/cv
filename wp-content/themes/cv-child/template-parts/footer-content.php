<?php
// cancao-verdadeira-child/template-parts/footer-content.php
// Gerado em: 2026-06-21 23:45:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Conteúdo do rodapé: seção de newsletter, redes sociais (via CV_Social),
// links do site e copyright. Incluído nos templates que têm footer visível.
// Renderizado ANTES do player fixo — tem padding-bottom para não sobrepor.
// v15.16.0 (25/09/2026): 4ª coluna "Apoie" (Seja nosso parceiro / Seja nosso
// colaborador) e as duas janelas, desenhadas pelo plugin (CV_Apoio).
// v15.17.0: link "🛍️ Loja" na coluna Explorar.
// v15.61.0: formulário com o NOME (template-parts/newsletter-form.php), um só script
// para o rodapé e a janela "📩 Receba novidades"; link da Política de Privacidade
// no fim; o link da Loja saiu (loja escondida desde 26/10).

if ( ! defined( 'ABSPATH' ) ) { exit; }

$site_name = get_bloginfo('name');
$site_desc = get_bloginfo('description');
$ano       = date('Y');

// Redes sociais (via módulo CV_Social do plugin)
$redes_sociais = class_exists('CV_Social') ? CV_Social::get_all() : array();
?>

<!-- Newsletter -->
<section class="cv-newsletter-section" aria-label="Newsletter">
    <div style="max-width:540px;margin:0 auto">
        <div style="font-size:32px;margin-bottom:12px">🎵</div>
        <h2 class="cv-newsletter-title">Receba as novidades no seu e-mail</h2>
        <p class="cv-newsletter-subtitle">
            Músicas novas e letras que tocam o coração, direto na sua caixa de entrada
        </p>
        <?php get_template_part( 'template-parts/newsletter-form' ); // v15.61.0: com o nome ("Olá, Maria!") e o link da privacidade ?>
    </div>
</section>

<!-- Rodapé principal -->
<footer class="cv-footer" role="contentinfo">

    <div class="cv-footer-grid">

        <!-- Coluna: marca -->
        <div>
            <div class="cv-footer-logo">
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <img src="<?php echo esc_url(cv_logo_url()); ?>"
                         alt="<?php echo esc_attr($site_name); ?>"
                         style="height:48px;width:auto" />
                </a>
            </div>
            <p class="cv-footer-desc">
                <?php echo $site_desc ?: 'Letras de músicas sertanejas autorais. Ouça, curta e descubra novos talentos do campo.'; ?>
            </p>

            <!-- Redes sociais -->
            <?php if ( ! empty($redes_sociais) ) : ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px">
                <?php foreach ( $redes_sociais as $key => $rede ) : ?>
                <a href="<?php echo esc_url($rede['url']); ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   title="<?php echo esc_attr($rede['label']); ?>"
                   aria-label="<?php echo esc_attr($site_name . ' no ' . $rede['label']); ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;
                          width:36px;height:36px;border-radius:50%;
                          background:<?php echo esc_attr($rede['color']); ?>;
                          color:#3B2418;font-size:15px;text-decoration:none;
                          transition:opacity .2s,transform .2s"
                   onmouseover="this.style.opacity='.8';this.style.transform='translateY(-2px)'"
                   onmouseout="this.style.opacity='1';this.style.transform='none'">
                    <?php echo $rede['icon']; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Coluna: navegação -->
        <div>
            <h3 class="cv-footer-col-title">Explorar</h3>
            <ul class="cv-footer-links">
                <li><a href="<?php echo esc_url(home_url('/')); ?>">Início</a></li>
                <li><a href="<?php echo esc_url(home_url('/musicas/')); ?>">Todas as Músicas</a></li>
                <li><a href="<?php echo esc_url(home_url('/ranking/')); ?>">Ranking</a></li>
                <li><a href="<?php echo esc_url(home_url('/buscar-musicas/')); ?>">Buscar</a></li>
            </ul>
        </div>

        <!-- Coluna: conta -->
        <div>
            <h3 class="cv-footer-col-title">Sua Conta</h3>
            <ul class="cv-footer-links">
                <?php if ( is_user_logged_in() ) : ?>
                <li><a href="<?php echo esc_url(cv_dashboard_url()); ?>">Minha Área</a></li>
                <li><a href="<?php echo esc_url(home_url('/minhas-playlists/')); ?>">Minhas Playlists</a></li>
                <li><a href="<?php echo esc_url(cv_profile_url()); ?>">Meu Perfil</a></li>
                <li>
                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"
                       style="color:var(--cv-text-dim)">
                        Sair
                    </a>
                </li>
                <?php else : ?>
                <li><a href="<?php echo esc_url(cv_login_url()); ?>">Entrar</a></li>
                <li><a href="<?php echo esc_url(cv_register_url()); ?>">Criar Conta Grátis</a></li>
                <?php endif; ?>
                <li><a href="<?php echo esc_url(home_url('/contato/')); ?>">Contato</a></li>
            </ul>
        </div>

        <?php if ( class_exists( 'CV_Apoio' ) ) : ?>
        <!-- Coluna: apoie (plugin: CV_Apoio) -->
        <?php echo CV_Apoio::coluna_rodape(); ?>
        <?php endif; ?>

    </div>

    <!-- Copyright -->
    <div class="cv-footer-bottom">
        <span>
            © <?php echo esc_html($ano); ?>
            <a href="<?php echo esc_url(home_url('/')); ?>"
               style="color:var(--cv-gold)">
                <?php echo esc_html($site_name); ?>
            </a>
            — Todos os direitos reservados.
        </span>
        <span style="color:var(--cv-text-dim)">
            Letras autorais sertanejas 🤠
            <?php if ( get_privacy_policy_url() ) : ?>
            · <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" style="color:inherit">Política de Privacidade</a>
            <?php endif; ?>
        </span>
    </div>

</footer>

<?php if ( class_exists( 'CV_Apoio' ) ) { echo CV_Apoio::janelas(); } // janelas "Apoie" (abrem pelo rodapé) ?>
<?php if ( class_exists( 'CV_Apoio' ) ) { get_template_part( 'template-parts/novidades' ); } // v15.31.0: janela "✨ Novidades" (abre pelo menu lateral) ?>
<?php if ( class_exists( 'CV_Apoio' ) ) { get_template_part( 'template-parts/newsletter-janela' ); } // v15.61.0: janela "📩 Receba novidades" (abre pelo menu lateral) ?>

<!-- Newsletter JS (inline para garantir execução). v15.61.0: vale para todo .cv-nl-box (rodapé e janela) -->
<script>
jQuery(function($){
    $(document).on('click', '.cv-nl-enviar', function(){
        var $box  = $(this).closest('.cv-nl-box');
        var nome  = $.trim($box.find('.cv-nl-nome').val());
        var email = $.trim($box.find('.cv-nl-email').val());
        var $msg  = $box.find('.cv-nl-msg');
        var $btn  = $(this);

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $msg.css({'color':'var(--cv-error)'}).text('Por favor, informe um e-mail válido.').show();
            return;
        }

        $btn.prop('disabled', true).text('⏳ Inscrevendo...');

        $.post(cvPublic.ajaxUrl, {
            action: 'cv_subscribe',
            nonce:  cvPublic.nonces.newsletter,
            email:  email,
            name:   nome,
        }, function(res){
            if (res.success) {
                $msg.css({'color':'var(--cv-success)'})
                    .text('✓ ' + (res.data.message || 'Inscrição realizada!')).show();
                $box.find('.cv-nl-email').val('');
            } else {
                $msg.css({'color':'var(--cv-error)'})
                    .text(res.data.message || 'Erro ao inscrever.').show();
            }
            $btn.prop('disabled', false).text('Inscrever-se');
        }).fail(function(){
            $msg.css({'color':'var(--cv-error)'}).text('Erro de conexão.').show();
            $btn.prop('disabled', false).text('Inscrever-se');
        });
    });

    // Enter nos campos
    $(document).on('keypress', '.cv-nl-nome, .cv-nl-email', function(e){
        if (e.which === 13) { $(this).closest('.cv-nl-box').find('.cv-nl-enviar').trigger('click'); }
    });
});
</script>
