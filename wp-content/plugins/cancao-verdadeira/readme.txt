=== Canção Verdadeira ===
Contributors: cancaoverdadeira
Tags: music, lyrics, sertanejo, ranking, playlist, player, youtube
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 2.52.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plataforma completa de letras musicais sertanejas com ranking dinâmico, player YouTube, playlists e shortcodes para Elementor.

== Descrição ==

O plugin Canção Verdadeira transforma um WordPress comum em uma plataforma profissional de letras musicais sertanejas, inspirada em plataformas modernas de streaming.

= Funcionalidades principais =

* Custom Post Type "Música" com metaboxes completos (compositor, artista, álbum, ano, URL YouTube, SEO)
* Sem gêneros: o site é todo sertanejo (taxonomias removidas na v2.26.0)
* Sistema de ranking dinâmico com fórmula ponderada (plays, favoritos, avaliações)
* Ranking por período: diário, semanal e mensal com indicadores ↑↓
* Contagem de plays com antifraude (IP + 30 segundos)
* Sistema de favoritos e avaliação por estrelas (1–5)
* Playlists do usuário: criar, renomear, excluir, adicionar e reordenar músicas
* Newsletter com integração MailerLite API v3 e fallback local
* Conquistas/badges automáticos (13 tipos)
* Trending em tempo real (últimos 15 minutos)
* Notificações in-app para usuários
* Recomendação automática: melhores ranqueadas que o usuário ainda não ouviu
* Importador de músicas via URL do YouTube
* WhatsApp flutuante configurável com pulso de atenção
* Botões de compartilhamento social (WhatsApp, Facebook, Twitter/X, Telegram)
* Schema.org MusicComposition para SEO (compatível com Rank Math)
* Níveis de permissão customizados: Editor, Gerente e Master
* Logs de ações administrativas com retenção de 90 dias
* Exportação de usuários e assinantes em CSV
* REST API completa: /cv/v1/musicas, /ranking, /busca, /trending
* Shortcodes para Elementor: [cv_grid_musicas], [cv_ranking], [cv_newsletter], [cv_share], [cv_recomendacoes], [cv_ranking_periodo]
* Compatibilidade com LiteSpeed Cache e WP Rocket

= Requisitos =

* WordPress 6.0 ou superior
* PHP 7.2 ou superior (testado até PHP 8.2)
* MySQL 5.7 ou superior
* Elementor Pro (recomendado, não obrigatório)
* Rank Math SEO (recomendado, não obrigatório)

= Shortcodes disponíveis =

**Grade de músicas**
`[cv_grid_musicas limite="12" ordem="novas" colunas="3" destaque="nao" titulo=""]`

**Ranking geral**
`[cv_ranking limite="10" tipo="top" titulo="🏆 Ranking" layout="lista"]`

**Ranking por período**
`[cv_ranking_periodo periodo="semanal" limite="10" titulo="" layout="lista"]`
Períodos: `diario`, `semanal`, `mensal`

**Recomendações personalizadas**
`[cv_recomendacoes limite="6" titulo="Recomendado para você" colunas="3"]`

**Newsletter**
`[cv_newsletter titulo="Receba as novidades" botao="Assinar Grátis 🎵"]`

**Compartilhamento social**
`[cv_share redes="whatsapp,facebook,twitter,telegram,copiar" layout="botoes"]`

== Instalação ==

1. Faça o upload do arquivo ZIP pelo painel do WordPress em **Plugins > Adicionar novo > Enviar plugin**
2. Ative o plugin
3. Acesse **Canção Verdadeira > Aparência** para configurar logo e banner
4. Acesse **Canção Verdadeira > Configurações** para configurar MailerLite e WhatsApp
5. Cadastre as primeiras músicas em **Canção Verdadeira > Músicas > Nova Música**

== Perguntas frequentes ==

= O plugin funciona sem o tema Canção Verdadeira? =
O plugin é independente do tema. Os shortcodes funcionam em qualquer tema WordPress. Para a experiência visual completa (player global, parallax, dark mode), use o tema Canção Verdadeira.

= Como configurar o WP-Cron para localhost? =
Em ambiente local sem tráfego, o WP-Cron pode não disparar automaticamente. Recalcule o ranking manualmente via **Canção Verdadeira > Ranking > Recalcular Ranking**.

= Quantas tabelas o plugin cria no banco de dados? =
São 9 tabelas com o prefixo do WordPress: `cv_plays_log`, `cv_favorites`, `cv_ratings`, `cv_playlists`, `cv_playlist_items`, `cv_ranking_cache`, `cv_subscribers`, `cv_lyric_comments`, `cv_action_logs`.

= O que acontece ao desinstalar o plugin? =
Ao excluir o plugin pelo painel, o arquivo `uninstall.php` remove automaticamente todas as tabelas, opções, roles customizados e transients criados pelo plugin.

== Capturas de tela ==

1. Dashboard administrativo com KPIs e gráfico de plays
2. Cadastro de música com metaboxes completos
3. Página de ranking com indicadores de tendência
4. Gerenciamento de playlists no painel
5. Página de configurações (MailerLite, WhatsApp, Player)

== Changelog ==

= 1.9.1 =
* Corrigido: versão no cabeçalho do plugin desatualizada
* Corrigido: dois nonces sem localização (cv_notif_nonce, cv_lyric_comment_nonce)
* Corrigido: LiteSpeed Cache purge com transient keys incorretos
* Corrigido: do_action('cv_playlist_created') não disparava no método create()
* Corrigido: clear_user_recommendations limpava apenas 3 tamanhos fixos
* Corrigido: roles criados apenas na reativação — agora criados automaticamente no init
* Corrigido: recalculate_periods() parava com cv_ranking_cache vazio
* Corrigido: export CSV via base64+JSON — alterado para download direto via headers
* Adicionado: campos de capa e "fixar na home" na gestão de playlists
* Adicionado: imagem placeholder default-cover.svg
* Adicionado: uninstall.php para limpeza completa ao excluir
* Adicionado: shortcode [cv_recomendacoes]
* Adicionado: shortcode [cv_ranking_periodo] com indicadores ↑↓
* Adicionado: gráfico de plays dos últimos 14 dias no dashboard admin
* Adicionado: busca, KPIs por gênero e export CSV na página de Assinantes
* Adicionado: nonce de segurança em cv_get_recommendations
* Adicionado: compatibilidade com esquemas de cores do WordPress no admin.css
* Adicionado: readme.txt

= 1.9.0 =
* Adicionado: ranking por período (diário, semanal, mensal)
* Adicionado: indicadores de tendência ↑↓= no ranking
* Adicionado: sistema de logs de ações administrativas
* Adicionado: níveis de permissão customizados (Editor, Gerente, Master)
* Adicionado: recomendação automática baseada em histórico e gênero favorito
* Adicionado: exportação de usuários em CSV

= 1.8.0 =
* Adicionado: shortcode [cv_share] com 5 redes sociais
* Adicionado: REST API /musicas/{id}, /generos, /busca, /plays
* Adicionado: endpoints AJAX cv_get_playlist_queue e cv_get_genre_queue
* Adicionado: busca, ações e export CSV na página de Usuários do admin

= 1.7.1 =
* Adicionado: shortcode [cv_generos] com layouts grade, lista e pills
* Adicionado: busca AJAX com autocomplete (migrada do tema para o plugin)
* Adicionado: WhatsApp flutuante aprimorado (mensagem, tooltip, delay configuráveis)
* Corrigido: página de Playlists no painel (era placeholder vazio)

= 1.6.0 =
* Adicionado: class-cv-public.php — registra cv-cover, enfileira JS/CSS e localiza nonces
* Adicionado: shortcodes [cv_grid_musicas], [cv_ranking], [cv_newsletter]
* Corrigido: gêneros com acentuação correta e slugs limpos

= 1.5.0 =
* Versão inicial com CPT, taxonomias, ranking, plays, favoritos, playlists, conquistas e trending

== Notas de atualização ==

Ao atualizar de versões anteriores à 1.9.0, vá em **Plugins > Desativar > Ativar** para criar as novas tabelas e roles. Não é necessário reinstalar.
