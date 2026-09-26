<?php
// cv-migracao/includes/class-cvm-verificacoes.php
// Projeto : Canção Verdadeira — plugin provisório da migração
// Função  : monta a lista de conferência mostrada no painel.
//           antes()  → no computador, ANTES de fazer o backup (dados de teste,
//                      plugins sem uso, senhas, endereço secreto do login...).
//           depois() → no site no ar, DEPOIS de restaurar (endereço, restos de
//                      cv.local, PHP, cache, links, pasta privada...).
//           Cada item: grupo, titulo, estado (ok | aviso | erro | info),
//           detalhe, e opcionalmente acao (botão seguro do painel) ou link.
//           Só LÊ o site; as ações ficam em CVM_Painel::acao().

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CVM_Verificacoes {

    private static function item( $grupo, $titulo, $estado, $detalhe, $extra = array() ) {
        return array_merge( array( 'grupo' => $grupo, 'titulo' => $titulo, 'estado' => $estado, 'detalhe' => $detalhe ), $extra );
    }

    private static function versao_plugin( $arquivo ) {
        if ( ! function_exists( 'get_plugin_data' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $p = WP_PLUGIN_DIR . '/' . $arquivo;
        if ( ! file_exists( $p ) ) { return ''; }
        $d = get_plugin_data( $p, false, false );
        return $d['Version'];
    }

    private static function slug_login() {
        $s = get_option( 'whl_page' );
        return is_string( $s ) && '' !== $s ? $s : '';
    }

    // ═════════════════════ ANTES (no computador) ═════════════════════
    public static function antes() {
        global $wpdb;
        if ( ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $l = array();

        $tema = wp_get_theme();
        $l[] = self::item( '1. Versões', 'Anote estas versões', 'info',
            sprintf( 'WordPress %s · PHP %s · plugin Canção Verdadeira %s · tema %s %s · UpdraftPlus %s. No site no ar, o WordPress precisa estar nesta versão (ou mais nova) ANTES da restauração, e o PHP em 8.1 ou mais novo.',
                get_bloginfo( 'version' ), PHP_VERSION, self::versao_plugin( 'cancao-verdadeira/cancao-verdadeira.php' ), $tema->get( 'Name' ), $tema->get( 'Version' ), self::versao_plugin( 'updraftplus/updraftplus.php' ) ) );

        $l[] = self::item( '1. Versões', 'Este plugin vai junto no backup', 'ok',
            'Deixe o "CV Migração" ATIVO até fazer o backup: é ele que arruma o endereço lá no site no ar.' );

        // Plugins
        if ( is_plugin_active( 'query-monitor/query-monitor.php' ) ) {
            $l[] = self::item( '2. Plugins', 'Query Monitor está ativo', 'aviso',
                'É uma ferramenta de programador: deixa o site mais lento e mostra detalhes técnicos. Desative antes do backup (ele fica instalado).', array( 'acao' => 'desativar_qm', 'rotulo' => 'Desativar o Query Monitor' ) );
        } else {
            $l[] = self::item( '2. Plugins', 'Query Monitor desativado', 'ok', 'Certo: não vai ligado para o site no ar.' );
        }
        $inativos = array();
        foreach ( get_plugins() as $f => $p ) { if ( ! is_plugin_active( $f ) ) { $inativos[ $f ] = $p['Name']; } }
        if ( $inativos ) {
            $perigo = isset( $inativos['wp-file-manager/file_folder_manager.php'] );
            $l[] = self::item( '2. Plugins', count( $inativos ) . ' plugins desativados (sem uso)', $perigo ? 'erro' : 'aviso',
                'Plugins desativados não fazem falta e deixam o backup maior. ' . ( $perigo ? 'O "WP File Manager" já teve falhas graves de segurança: APAGUE. ' : '' ) . 'Recomendo apagar em Plugins → Desativados: ' . implode( ', ', $inativos ) . '.',
                array( 'link' => admin_url( 'plugins.php?plugin_status=inactive' ), 'rotulo' => 'Abrir Plugins desativados' ) );
        } else {
            $l[] = self::item( '2. Plugins', 'Nenhum plugin desativado', 'ok', 'Backup sem peso extra.' );
        }

        // Pessoas e acesso
        $admins = get_users( array( 'role' => 'administrator', 'fields' => array( 'user_login', 'user_email', 'ID' ) ) );
        $nomes = array(); foreach ( $admins as $a ) { $nomes[] = $a->user_login . ' (' . $a->user_email . ')'; }
        $l[] = self::item( '3. Acesso', 'Quem vai conseguir entrar no painel lá', 'info',
            'Depois da restauração, os usuários e as SENHAS do site no ar passam a ser os deste computador. Confirme que você sabe a senha de um destes administradores: ' . implode( '; ', $nomes ) . '.' );
        $slug = self::slug_login();
        $l[] = self::item( '3. Acesso', 'Endereço do painel depois da subida', $slug ? 'info' : 'aviso',
            $slug ? 'O login vai abrir em: https://cancaoverdadeira.com.br/' . $slug . '/ (o mesmo "endereço secreto" daqui). Anote em papel.' : 'O WPS Hide Login não tem endereço secreto: o painel abre em /wp-admin.' );
        $errado = get_users( array( 'search' => '*cancaoverdadedira*', 'search_columns' => array( 'user_email' ) ) );
        if ( $errado ) {
            $l[] = self::item( '3. Acesso', 'E-mail com erro de digitação', 'aviso',
                'O usuário ' . $errado[0]->user_login . ' está com o e-mail "' . $errado[0]->user_email . '" (tem um "d" a mais: o certo é cancaoverdadeira). Corrija antes: sem isso não dá para recuperar a senha lá.',
                array( 'link' => admin_url( 'user-edit.php?user_id=' . (int) $errado[0]->ID ), 'rotulo' => 'Corrigir o e-mail' ) );
        }

        // Conteúdo de teste
        $cifra_ex = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_cv_cifra' AND meta_value LIKE %s", '%Introdução: G D Em C%' ) );
        foreach ( $cifra_ex as $pid ) {
            $l[] = self::item( '4. Conteúdo', 'Cifra de EXEMPLO em "' . get_the_title( $pid ) . '"', 'aviso',
                'Foi colocada só para ver as cores. Troque pelos acordes reais ou apague o texto da caixa "🎸 Cifra simples".', array( 'link' => get_edit_post_link( $pid, 'raw' ), 'rotulo' => 'Editar a música' ) );
        }
        $sujas = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'musica' AND post_status IN ('publish','draft','future') AND post_content LIKE '%## Tags%'" );
        foreach ( $sujas as $pid ) {
            $l[] = self::item( '4. Conteúdo', 'Texto de SEO dentro da letra de "' . get_the_title( $pid ) . '"', 'aviso',
                'No fim da letra há "Ficha técnica", hashtags e "## Tags e palavras-chave". Aparece para o visitante e no "Cantar junto". Apague esse trecho da letra.', array( 'link' => get_edit_post_link( $pid, 'raw' ), 'rotulo' => 'Editar a música' ) );
        }
        if ( ! $cifra_ex && ! $sujas ) { $l[] = self::item( '4. Conteúdo', 'Sem dados de teste conhecidos', 'ok', 'Nenhuma cifra de exemplo nem texto de SEO nas letras.' ); }

        $l[] = self::item( '5. Site', 'Google pode ver o site', '1' === (string) get_option( 'blog_public' ) ? 'ok' : 'erro',
            '1' === (string) get_option( 'blog_public' ) ? 'A opção "Evitar que mecanismos de busca indexem" está desligada. Certo.' : 'A opção "Evitar que mecanismos de busca indexem este site" está LIGADA: o site sumiria do Google. Desligue em Configurações → Leitura.',
            array( 'link' => admin_url( 'options-reading.php' ), 'rotulo' => 'Configurações → Leitura' ) );

        $premium = class_exists( 'UpdraftPlus_Addons_Migrator' ) || defined( 'UDADDONS2_DIR' );
        $l[] = self::item( '5. Site', 'UpdraftPlus: grátis ou Premium?', 'info',
            $premium ? 'Este UpdraftPlus tem o Premium/Migrator: na restauração marque "Search and replace site location in the database".' : 'Este UpdraftPlus é o GRÁTIS: ele não troca o endereço. Tudo bem, o CV Migração faz isso lá (passo "Troca do endereço").' );

        $privado = dirname( untrailingslashit( ABSPATH ) ) . '/cv-privado';
        $n = is_dir( $privado ) ? count( self::arquivos( $privado ) ) : 0;
        $l[] = self::item( '5. Site', 'Pasta privada (contratos e comprovantes)', 'info',
            'Fica FORA do site e por isso NÃO vai no backup. Hoje tem ' . $n . ' arquivo(s) (só testes). Lá, o próprio plugin cria a pasta quando precisar.' );
        return $l;
    }

    // ═════════════════════ DEPOIS (no site no ar) ═════════════════════
    public static function depois() {
        global $wpdb;
        if ( ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $l = array();
        $home = home_url(); $host = (string) wp_parse_url( $home, PHP_URL_HOST );

        $ok_end = CVM_Endereco::host_e_destino( $host );
        $l[] = self::item( '1. Endereço', 'Endereço do site', $ok_end ? ( 0 === strpos( $home, 'https://' ) ? 'ok' : 'aviso' ) : 'erro',
            $ok_end ? ( 'O site responde em ' . $home . '.' . ( 0 === strpos( $home, 'https://' ) ? '' : ' Está sem "https": peça à Hostnet para ligar o certificado SSL.' ) )
                    : 'O endereço gravado é ' . $home . ', e não o do site no ar. Abra o site pelo endereço de verdade (https://cancaoverdadeira.com.br) para o plugin corrigir.' );
        $corr = get_option( 'cvm_endereco_corrigido' );
        if ( $corr ) { $l[] = self::item( '1. Endereço', 'Endereço corrigido automaticamente', 'info', 'No primeiro acesso, o plugin trocou http://cv.local por ' . $corr . '.' ); }

        $restos = 0;
        foreach ( array( $wpdb->options => 'option_value', $wpdb->posts => 'post_content', $wpdb->postmeta => 'meta_value' ) as $t => $c ) {
            $restos += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$c} LIKE %s", '%' . $wpdb->esc_like( CVM_ORIGEM_HOST ) . '%' ) );
        }
        $l[] = self::item( '1. Endereço', 'Restos de "cv.local" no banco', $restos ? 'aviso' : 'ok',
            $restos ? 'Ainda há cerca de ' . $restos . ' lugares com o endereço do computador (imagens e links quebrados). Use a "Troca do endereço" logo abaixo: primeiro a prévia, depois aplicar.' : 'Nenhum resto encontrado nas tabelas principais.' );

        $php = PHP_VERSION;
        $l[] = self::item( '2. Servidor', 'Versão do PHP: ' . $php, version_compare( $php, '8.1', '>=' ) ? 'ok' : ( version_compare( $php, '7.4', '>=' ) ? 'aviso' : 'erro' ),
            version_compare( $php, '8.1', '>=' ) ? 'Boa versão.' : 'Peça à Hostnet (ou troque no painel de lá) o PHP 8.2, o mesmo do computador. Versões antigas podem quebrar o Elementor e outros plugins.' );
        $l[] = self::item( '2. Servidor', 'WordPress ' . get_bloginfo( 'version' ), 'info', 'Deve ser a mesma versão (ou mais nova) do computador. Se o painel pedir "Atualizar banco de dados", pode aceitar.' );
        $l[] = self::item( '2. Servidor', 'Google pode ver o site', '1' === (string) get_option( 'blog_public' ) ? 'ok' : 'erro',
            '1' === (string) get_option( 'blog_public' ) ? 'Certo.' : 'Desligue "Evitar que mecanismos de busca indexem" em Configurações → Leitura.', array( 'link' => admin_url( 'options-reading.php' ), 'rotulo' => 'Configurações → Leitura' ) );

        if ( is_plugin_active( 'query-monitor/query-monitor.php' ) ) {
            $l[] = self::item( '3. Arrumação', 'Query Monitor ligado no site no ar', 'aviso', 'Deixa o site mais lento. Desative.', array( 'acao' => 'desativar_qm', 'rotulo' => 'Desativar o Query Monitor' ) );
        }
        $l[] = self::item( '3. Arrumação', 'Links permanentes (endereços das páginas)', 'info',
            'Grava de novo as regras de endereço do servidor (arquivo .htaccess). Faça uma vez depois da troca do endereço.', array( 'acao' => 'refazer_links', 'rotulo' => 'Refazer os links' ) );
        $l[] = self::item( '3. Arrumação', 'Limpar os caches (WP Rocket, Elementor e WordPress)', 'info',
            'Tira do cache as páginas antigas e manda o Elementor refazer os estilos. Faça por último.' . ( defined( 'WP_CACHE' ) && WP_CACHE ? '' : ' Obs.: WP_CACHE não está ligado no wp-config: abra WP Rocket → Configurações e salve uma vez.' ),
            array( 'acao' => 'limpar_cache', 'rotulo' => 'Limpar os caches' ) );

        $privado = dirname( untrailingslashit( ABSPATH ) ) . '/cv-privado';
        $pode = is_dir( $privado ) ? wp_is_writable( $privado ) : wp_is_writable( dirname( $privado ) );
        $l[] = self::item( '4. Pasta privada', 'Contratos e comprovantes fora do site', is_dir( $privado ) && $pode ? 'ok' : ( $pode ? 'info' : 'aviso' ),
            is_dir( $privado ) ? 'A pasta existe fora da área pública (' . $privado . ').' : ( $pode ? 'A pasta ainda não existe; pode criar agora (ou o plugin cria no primeiro envio).' : 'A hospedagem não deixa criar pasta fora do site. Tudo bem: o plugin usa uploads/cv-privado com bloqueio e nomes aleatórios.' ),
            ( ! is_dir( $privado ) && $pode ) ? array( 'acao' => 'criar_privado', 'rotulo' => 'Criar a pasta privada' ) : array() );

        $slug = self::slug_login();
        $l[] = self::item( '5. Últimos passos', 'Endereço do painel', 'info', $slug ? 'O login é ' . home_url( '/' . $slug . '/' ) . ' — guarde em papel.' : 'O painel abre em ' . admin_url() );
        $l[] = self::item( '5. Últimos passos', 'UpdraftPlus do site no ar', 'aviso',
            'As configurações vieram do computador (inclusive a conta do Google Drive). Abra UpdraftPlus → Configurações, confira o horário dos backups e faça um "Backup agora" para ter o primeiro backup do site novo.', array( 'link' => admin_url( 'options-general.php?page=updraftplus' ), 'rotulo' => 'Abrir UpdraftPlus' ) );
        $l[] = self::item( '5. Últimos passos', 'Google Site Kit, MailerLite e PIX', 'info', 'Conecte o Site Kit (login pessoal do Google), crie os grupos do MailerLite e cadastre o PIX pelo painel do Canção Verdadeira.' );
        $l[] = self::item( '5. Últimos passos', 'Quando tudo estiver ✅', 'info', 'Vá em Plugins, desative e apague o "CV Migração (provisório)". Ele não é mais necessário.' );
        return $l;
    }

    private static function arquivos( $dir ) {
        $r = array();
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $f ) { if ( $f->isFile() && 'index.php' !== $f->getFilename() && '.htaccess' !== $f->getFilename() ) { $r[] = $f->getPathname(); } }
        return $r;
    }
}
