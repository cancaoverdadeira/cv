<?php
// cancao-verdadeira/includes/cpt/class-cv-fields.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Lista central dos campos da música (CPT musica)
// Motivo  : o mesmo dado aparecia com nomes diferentes em partes do código
//           (_cv_letra x post_content, _cv_favoritos x _cv_favorites), e as
//           telas do admin liam campos que nunca eram gravados. Use sempre
//           as constantes e helpers daqui em vez de digitar o nome do meta.
// SEO     : sync_excerpt() é o "SEO automático" compartilhado entre música e
//           post do blog: a Descrição vira o resumo, que o Rank Math usa como
//           meta description (%excerpt%).
// Mídia   : youtube_id()/youtube_match()/cover_url() — a regra do ID do
//           YouTube estava copiada em 21 lugares (a maioria sem /shorts/).
// Gerado  : 2026-09-23 | Atualizado: 2026-09-24 (v2.31.0, refatoração fase 4)

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Fields {

    // A letra NÃO é um meta: fica em post_content (metabox "Letra da Música").
    const YOUTUBE_URL  = '_cv_youtube_url';
    const AUDIO_URL    = '_cv_audio_url';
    const COMPOSITOR   = '_cv_compositor';
    const ARTISTA      = '_cv_artista';
    const ALBUM        = '_cv_album';
    const ANO          = '_cv_ano';
    const DESCRICAO    = '_cv_descricao';
    const ATIVO        = '_cv_ativo';
    const DESTAQUE     = '_cv_destaque';
    const ESTREIA_DATE = '_cv_estreia_date';
    const ESTREIA_TIME = '_cv_estreia_time';
    const SELECAO_ORDEM = '_cv_selecao_ordem'; // ordem na "Seleção da Canção Verdadeira" (CV_Launch)

    // Contadores calculados (gravados por CV_Ranking / CV_Favorites a partir
    // das tabelas cv_plays, cv_favorites e cv_ratings — não editar à mão).
    const PLAYS_TOTAL  = '_cv_plays_total';
    const FAVORITES    = '_cv_favorites';
    const AVG_RATING   = '_cv_avg_rating';
    const SCORE        = '_cv_score';

    // Letras com menos caracteres que isso contam como "sem letra" no painel.
    const LETRA_MIN_CHARS = 100;

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    // Registra os metas no WordPress (tipo, valor único, só usuários que
    // podem editar a música conseguem gravar). Não expõe nada na REST.
    public static function register() {
        $types = array(
            self::YOUTUBE_URL  => 'string',
            self::AUDIO_URL    => 'string',
            self::COMPOSITOR   => 'string',
            self::ARTISTA      => 'string',
            self::ALBUM        => 'string',
            self::ANO          => 'integer',
            self::DESCRICAO    => 'string',
            self::ATIVO        => 'string',
            self::DESTAQUE     => 'string',
            self::ESTREIA_DATE => 'string',
            self::ESTREIA_TIME => 'string',
            self::SELECAO_ORDEM => 'integer',
            self::PLAYS_TOTAL  => 'integer',
            self::FAVORITES    => 'integer',
            self::AVG_RATING   => 'number',
            self::SCORE        => 'number',
        );
        foreach ( $types as $key => $type ) {
            register_post_meta( 'musica', $key, array(
                'type'          => $type,
                'single'        => true,
                'show_in_rest'  => false,
                'auth_callback' => function( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                },
            ) );
        }
        // Posts do blog usam a mesma Descrição de SEO das músicas (CV_Blog).
        register_post_meta( 'post', self::DESCRICAO, array(
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => false,
            'auth_callback' => function( $allowed, $meta_key, $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            },
        ) );
    }

    // SEO automático: copia a Descrição para o resumo (post_excerpt).
    // Vale para música e para post do blog. Descrição vazia não mexe no resumo.
    public static function sync_excerpt( $post_id ) {
        $descricao = get_post_meta( $post_id, self::DESCRICAO, true );
        if ( '' === trim( (string) $descricao ) ) { return; }
        $descricao = sanitize_textarea_field( $descricao );
        if ( get_post_field( 'post_excerpt', $post_id ) === $descricao ) { return; }
        global $wpdb;
        // Grava direto para não disparar save_post de novo (evita laço).
        $wpdb->update( $wpdb->posts, array( 'post_excerpt' => $descricao ), array( 'ID' => $post_id ) );
        clean_post_cache( $post_id );
    }

    // ── YouTube e capa (fonte única) ────────────────────────────────

    // Regex única: watch?v=, embed/, youtu.be/, shorts/ e live/.
    const YOUTUBE_REGEX = '~(?:[?&]v=|/embed/|youtu\.be/|/shorts/|/live/)([A-Za-z0-9_-]{11})~';

    // ID de 11 caracteres do vídeo, ou '' se o link não for do YouTube.
    public static function youtube_id( $url ) {
        if ( ! is_string( $url ) || '' === $url ) { return ''; }
        return preg_match( self::YOUTUBE_REGEX, $url, $m ) ? $m[1] : '';
    }

    // Troca direta do antigo preg_match(...): devolve array( 0 => ..., 1 => id )
    // quando acha, ou array() — mantém os isset($m[1]) / $m[1] ?? '' existentes.
    public static function youtube_match( $url ) {
        $id = self::youtube_id( $url );
        return '' === $id ? array() : array( 0 => $id, 1 => $id );
    }

    // Miniatura do YouTube (qualidade: default | mqdefault | hqdefault | maxresdefault).
    public static function youtube_thumb( $url, $qualidade = 'mqdefault' ) {
        $id = self::youtube_id( $url );
        return $id ? 'https://img.youtube.com/vi/' . $id . '/' . $qualidade . '.jpg' : '';
    }

    // Capa da música: imagem destacada → miniatura do YouTube → '' (quem chama
    // decide o padrão, ex.: default-cover.svg).
    public static function cover_url( $post_id, $size = 'medium', $qualidade = 'mqdefault' ) {
        $url = get_the_post_thumbnail_url( $post_id, $size );
        if ( $url ) { return $url; }
        return self::youtube_thumb( (string) get_post_meta( $post_id, self::YOUTUBE_URL, true ), $qualidade );
    }

    // Texto da letra, sem HTML.
    public static function letra( $post_id ) {
        $post = get_post( $post_id );
        return $post ? trim( wp_strip_all_tags( $post->post_content ) ) : '';
    }

    public static function has_letra( $post_id ) {
        return mb_strlen( self::letra( $post_id ) ) >= self::LETRA_MIN_CHARS;
    }
}
CV_Fields::init();
