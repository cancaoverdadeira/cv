<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-roles.php
// Página "Permissões": papéis e permissões de acesso.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Roles {

    public static function render() {
        // Salvar permissão
        if ( isset( $_POST['cv_save_role'] ) && check_admin_referer( 'cv_role_save' ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                $target_uid  = absint( $_POST['cv_role_user_id'] );
                $target_role = sanitize_text_field( isset($_POST['cv_role_value']) ? $_POST['cv_role_value'] : '' );
                $allowed     = array( 'subscriber', 'cv_editor', 'cv_gerente', 'cv_master' );
                if ( in_array( $target_role, $allowed, true ) && $target_uid ) {
                    $user = new WP_User( $target_uid );
                    $user->set_role( $target_role );
                    $saved_role = true;
                }
            }
        }

        $users = get_users( array( 'number' => 200, 'orderby' => 'display_name' ) );

        $roles_def = array(
            'subscriber'    => array(
                'label' => 'Ouvinte', 'icon' => '🎧', 'cor' => '#3498db',
                'desc'  => 'Acessa o site, ouve músicas, cria playlists e favorita. Sem acesso ao painel admin.',
                'perms' => array('Ouvir músicas', 'Criar playlists', 'Favoritar', 'Avaliar com estrelas'),
            ),
            'cv_editor'  => array(
                'label' => 'Editor CV', 'icon' => '✏️', 'cor' => '#1DB954',
                'desc'  => 'Pode criar e editar músicas no painel. Ideal para colaboradores de conteúdo.',
                'perms' => array('Tudo do Ouvinte', 'Criar músicas', 'Editar músicas', 'Painel admin (restrito)'),
            ),
            'cv_gerente'  => array(
                'label' => 'Gerente CV', 'icon' => '🎛️', 'cor' => '#B8700C',
                'desc'  => 'Editor com poderes extras: excluir músicas, gerenciar playlists e usuários.',
                'perms' => array('Tudo do Editor', 'Excluir músicas', 'Gerenciar playlists', 'Ver usuários'),
            ),
            'cv_master'  => array(
                'label' => 'Master CV', 'icon' => '👑', 'cor' => '#9b59b6',
                'desc'  => 'Gerente + acesso a configurações, logs e recalcular ranking. Sem acesso ao WP nativo.',
                'perms' => array('Tudo do Gerente', 'Configurações', 'Ver logs', 'Recalcular ranking'),
            ),
            'administrator' => array(
                'label' => 'Administrador', 'icon' => '🔑', 'cor' => '#e74c3c',
                'desc'  => 'Acesso total ao WordPress. Não altere administradores por aqui — use o painel nativo do WP.',
                'perms' => array('Acesso total WP', 'Todos os painéis', 'Instalar plugins', 'Alterar temas'),
            ),
        );

        // Contagem por role
        $role_counts = array();
        foreach ($roles_def as $rk => $rd) {
            $role_counts[$rk] = count( get_users( array('role' => $rk, 'fields' => 'ID') ) );
        }
        ?>
        <div class="wrap" id="cv-roles-exec">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-roles-exec {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --bord:#F3E6D3;
            --text:#3B2418; --muted:#C9A27E;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; padding-bottom:48px;
        }
        #cv-roles-exec * { box-sizing:border-box; }
        .cv-rol-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-rol-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-rol-title span { color:var(--gold); }
        /* Cards de role */
        .cv-rol-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; margin-bottom:28px; }
        .cv-rol-card { background:var(--card); border:2px solid var(--bord); border-radius:14px; padding:18px; transition:border-color .2s,transform .15s; }
        .cv-rol-card:hover { transform:translateY(-2px); }
        .cv-rol-card-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
        .cv-rol-icon { font-size:28px; }
        .cv-rol-name { font-size:15px; font-weight:700; color:#3B2418; }
        .cv-rol-count { font-size:11px; margin-top:2px; }
        .cv-rol-desc { font-size:12px; color:var(--muted); line-height:1.5; margin-bottom:12px; }
        .cv-rol-perms { display:flex; flex-direction:column; gap:4px; }
        .cv-rol-perm { font-size:11px; color:var(--muted); display:flex; align-items:center; gap:6px; }
        .cv-rol-perm::before { content:'✓'; font-size:10px; font-weight:700; }
        /* Formulário de atribuição */
        .cv-rol-assign-box { background:var(--card); border:1px solid var(--bord); border-radius:14px; padding:22px; }
        .cv-rol-assign-title { font-size:15px; font-weight:700; color:#3B2418; margin:0 0 18px; }
        .cv-rol-assign-grid { display:grid; grid-template-columns:1fr 1fr auto; gap:12px; align-items:end; }
        @media (max-width:800px) { .cv-rol-assign-grid { grid-template-columns:1fr; } }
        .cv-rol-field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        .cv-rol-select { width:100%; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-rol-select:focus { border-color:var(--gold); }
        .cv-rol-select option { background:#F8F0E4; }
        .cv-rol-btn-save { background:var(--gold); color:#3B2418; border:none; padding:10px 20px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; white-space:nowrap; font-family:inherit; transition:opacity .2s; }
        .cv-rol-btn-save:hover { opacity:.85; }
        .cv-rol-notice { background:rgba(29,185,84,.08); border:1px solid rgba(29,185,84,.3); color:#137B38; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:18px; }
        .cv-rol-warn { font-size:11px; color:var(--muted); margin-top:14px; padding:10px 14px; background:rgba(231,76,60,.06); border:1px solid rgba(231,76,60,.15); border-radius:8px; }
        </style>

        <div class="cv-rol-topbar">
            <h1 class="cv-rol-title">🔐 <span>Permissões</span></h1>
        </div>
        <?php echo CV_Admin::btn_voltar(); ?>

        <?php if ( isset($saved_role) && $saved_role ) : ?>
        <div class="cv-rol-notice">✅ Permissão atualizada com sucesso!</div>
        <?php endif; ?>

        <!-- Cards de níveis -->
        <div class="cv-rol-grid">
        <?php foreach ($roles_def as $rk => $rd) :
            $cor   = $rd['cor'];
            $count = isset($role_counts[$rk]) ? $role_counts[$rk] : 0;
        ?>
        <div class="cv-rol-card" style="border-color:<?php echo esc_attr($cor); ?>33">
            <div class="cv-rol-card-top">
                <span class="cv-rol-icon"><?php echo $rd['icon']; ?></span>
                <div>
                    <div class="cv-rol-name" style="color:<?php echo esc_attr($cor); ?>"><?php echo esc_html($rd['label']); ?></div>
                    <div class="cv-rol-count" style="color:<?php echo esc_attr($cor); ?>99">
                        <?php echo $count; ?> usuário<?php echo $count !== 1 ? 's' : ''; ?>
                    </div>
                </div>
            </div>
            <div class="cv-rol-desc"><?php echo esc_html($rd['desc']); ?></div>
            <div class="cv-rol-perms">
            <?php foreach ($rd['perms'] as $perm) : ?>
                <div class="cv-rol-perm" style="--perm-cor:<?php echo esc_attr($cor); ?>;color:<?php echo esc_attr($cor); ?>aa">
                    <?php echo esc_html($perm); ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- Formulário de atribuição -->
        <div class="cv-rol-assign-box">
            <div class="cv-rol-assign-title">👤 Atribuir Nível a um Usuário</div>
            <form method="post">
                <?php wp_nonce_field( 'cv_role_save' ); ?>
                <div class="cv-rol-assign-grid">
                    <div class="cv-rol-field">
                        <label>Usuário</label>
                        <select name="cv_role_user_id" class="cv-rol-select">
                            <option value="">Selecione o usuário...</option>
                            <?php foreach ($users as $u) :
                                $roles_u = $u->roles;
                                $current = ! empty($roles_u) ? $roles_u[0] : 'subscriber';
                                $cur_label = isset($roles_def[$current]) ? $roles_def[$current]['label'] : $current;
                            ?>
                            <option value="<?php echo (int)$u->ID; ?>">
                                <?php echo esc_html($u->display_name); ?> — <?php echo esc_html($cur_label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cv-rol-field">
                        <label>Novo nível</label>
                        <select name="cv_role_value" class="cv-rol-select">
                            <?php foreach ($roles_def as $rk => $rd) :
                                if ($rk === 'administrator') { continue; } // não expor admin aqui
                            ?>
                            <option value="<?php echo esc_attr($rk); ?>">
                                <?php echo $rd['icon']; ?> <?php echo esc_html($rd['label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="cv_save_role" value="1" class="cv-rol-btn-save">
                            ✅ Atribuir
                        </button>
                    </div>
                </div>
                <div class="cv-rol-warn">
                    ⚠️ Administradores WordPress não aparecem na lista acima. Para alterar administradores, use o painel nativo do WordPress em Usuários → Todos os Usuários.
                </div>
            </form>
        </div>
        </div><!-- wrap -->
        <?php
    }
}
