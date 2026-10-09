<?php
// cancao-verdadeira/includes/user/class-cv-perfil-campos.php
// Criado em: 08/10/2026 (plugin v2.64.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Coloca campos no formulário de perfil do Ultimate Member (form "profile",
// que vinha sem nenhum campo): Cidade, Estado e "Como conheci o site".
// v2.65.0 (versão 2): "Minha música favorita" saiu daqui (era texto livre) e
// virou a escolha sentimento → música em CV_Musica_Favorita. Roda uma vez por versão da lista (opção
// cv_perfil_campos_versao) e só acrescenta o campo que ainda não existe:
// se o Eduardo mudar um rótulo pelo construtor do UM, a mudança fica.
// Privacidade: Cidade e Estado aparecem no perfil para todos; "Como conheci o site" só para o dono do perfil e a equipe.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Perfil_Campos {

    const VERSAO = 2; // sobe quando a lista de campos mudar (v2: saiu a música favorita em texto)

    public static function init() {
        add_action( 'init', array( __CLASS__, 'garantir_campos' ), 31 );
    }

    /** Os 27 estados, pelo nome (é o que aparece e o que fica gravado). */
    public static function estados() {
        return array(
            'Acre', 'Alagoas', 'Amapá', 'Amazonas', 'Bahia', 'Ceará', 'Distrito Federal',
            'Espírito Santo', 'Goiás', 'Maranhão', 'Mato Grosso', 'Mato Grosso do Sul',
            'Minas Gerais', 'Pará', 'Paraíba', 'Paraná', 'Pernambuco', 'Piauí',
            'Rio de Janeiro', 'Rio Grande do Norte', 'Rio Grande do Sul', 'Rondônia',
            'Roraima', 'Santa Catarina', 'São Paulo', 'Sergipe', 'Tocantins',
        );
    }

    /** Opções de "Como conheci o site". */
    public static function origens() {
        return array(
            'YouTube', 'WhatsApp', 'Facebook', 'Instagram',
            'Indicação de um(a) amigo(a)', 'Pesquisa no Google', 'Outro',
        );
    }

    /** Os campos no formato que o Ultimate Member grava em _um_custom_fields. */
    private static function campos() {
        $base = array(
            'required'   => 0,
            'editable'   => 1,
            'in_row'     => '_um_row_1',
            'in_sub_row' => 0,
            'in_column'  => 1,
            'in_group'   => '',
        );
        return array(
            'cv_cidade' => array_merge( $base, array(
                'title'       => 'Cidade',
                'metakey'     => 'cv_cidade',
                'type'        => 'text',
                'label'       => 'Cidade',
                'placeholder' => 'Ex.: Belo Horizonte',
                'max_chars'   => 60,
                'public'      => 1,
                'position'    => 1,
            ) ),
            'cv_estado' => array_merge( $base, array(
                'title'       => 'Estado',
                'metakey'     => 'cv_estado',
                'type'        => 'select',
                'label'       => 'Estado',
                'placeholder' => 'Escolha o seu estado',
                'options'     => self::estados(),
                'public'      => 1,
                'position'    => 2,
            ) ),
            'cv_como_conheceu' => array_merge( $base, array(
                'title'       => 'Como conheci o site',
                'metakey'     => 'cv_como_conheceu',
                'type'        => 'select',
                'label'       => 'Como conheci o site',
                'placeholder' => 'Escolha uma opção',
                'options'     => self::origens(),
                'public'      => -1, // só o dono do perfil e a equipe
                'position'    => 3,
            ) ),
        );
    }

    /**
     * Uma vez por versão: acrescenta ao formulário de perfil os campos que
     * ainda não estão lá. Não muda nem apaga campos que já existem.
     */
    public static function garantir_campos() {
        if ( (int) get_option( 'cv_perfil_campos_versao', 0 ) >= self::VERSAO ) { return; }

        $forms   = get_option( 'um_core_forms', array() );
        $form_id = isset( $forms['profile'] ) ? (int) $forms['profile'] : 0;
        if ( ! $form_id || 'um_form' !== get_post_type( $form_id ) ) { return; }

        $atuais = get_post_meta( $form_id, '_um_custom_fields', true );
        if ( ! is_array( $atuais ) ) { $atuais = array(); }
        if ( ! isset( $atuais['_um_row_1'] ) ) {
            $atuais['_um_row_1'] = array( 'type' => 'row', 'id' => '_um_row_1', 'sub_rows' => 1, 'cols' => 1 );
        }

        // Os novos entram depois dos que já existem (posição continua a contagem)
        $maior = 0;
        foreach ( $atuais as $campo ) {
            if ( isset( $campo['position'] ) ) { $maior = max( $maior, (int) $campo['position'] ); }
        }
        $mudou = false;
        // v2: a música favorita em texto livre sai do formulário (agora é CV_Musica_Favorita)
        if ( isset( $atuais['cv_musica_favorita'] ) && 'text' === $atuais['cv_musica_favorita']['type'] ) {
            unset( $atuais['cv_musica_favorita'] );
            $mudou = true;
        }
        foreach ( self::campos() as $chave => $campo ) {
            if ( isset( $atuais[ $chave ] ) ) { continue; }
            $campo['position'] = $maior + (int) $campo['position'];
            $atuais[ $chave ]  = $campo;
            $mudou = true;
        }
        if ( $mudou ) { update_post_meta( $form_id, '_um_custom_fields', $atuais ); }

        update_option( 'cv_perfil_campos_versao', self::VERSAO, false );
    }
}

CV_Perfil_Campos::init();
