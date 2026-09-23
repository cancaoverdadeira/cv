<?php
// cancao-verdadeira-child/template-parts/blog-styles.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Estilos do Blog, usados por category-blog.php (lista) e single.php (post).
// Só usa os tokens de cores e fontes de cv-variables.css (tema claro), então
// acompanha a paleta sem mexer nos arquivos CSS protegidos.
// Inclui o visual do conteúdo (.cv-page-content), igual ao de page.php.
// Gerado em: 2026-09-23 (tema v15.1.0)

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<style>
.cv-blog-hero { background:linear-gradient(135deg, var(--cv-bg-elevated) 0%, var(--cv-bg) 70%); padding:44px 36px 32px; border-bottom:1px solid var(--cv-border-subtle); }
.cv-blog-hero h1 { font-family:var(--font-display); font-size:38px; font-weight:700; margin:0 0 8px; color:var(--cv-text); }
.cv-blog-hero p  { color:var(--cv-text-muted); font-family:var(--font-body); font-style:italic; font-size:16px; margin:0; max-width:560px; }

.cv-blog-crumbs { display:flex; flex-wrap:wrap; gap:6px; font-size:12px; color:var(--cv-text-dim); margin-bottom:16px; }
.cv-blog-crumbs a { color:var(--cv-text-dim); text-decoration:none; }
.cv-blog-crumbs a:hover { color:var(--cv-gold); }
.cv-blog-crumbs .is-current { color:var(--cv-gold); }
.cv-blog-crumbs span[aria-hidden] { opacity:.5; }

.cv-blog-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:20px; }
.cv-blog-card { background:var(--cv-bg-card); border:1px solid var(--cv-border); border-radius:var(--cv-radius); overflow:hidden; display:flex; flex-direction:column; box-shadow:var(--cv-shadow-card); transition:transform .2s, border-color .2s; }
.cv-blog-card:hover { transform:translateY(-2px); border-color:var(--cv-border-hover); }
.cv-blog-card-img { display:block; aspect-ratio:16/9; background:var(--cv-bg-elevated); overflow:hidden; }
.cv-blog-card-img img { width:100%; height:100%; object-fit:cover; display:block; }
.cv-blog-card-ph { display:flex; align-items:center; justify-content:center; height:100%; font-size:40px; opacity:.5; }
.cv-blog-card-body { padding:16px 18px 18px; display:flex; flex-direction:column; gap:8px; flex:1; }
.cv-blog-card-body time { font-size:12px; color:var(--cv-text-dim); }
.cv-blog-card-body h2, .cv-blog-card-body h3 { font-family:var(--font-display); font-size:19px; line-height:1.3; margin:0; }
.cv-blog-card-body h2 a, .cv-blog-card-body h3 a { color:var(--cv-text); text-decoration:none; }
.cv-blog-card-body h2 a:hover, .cv-blog-card-body h3 a:hover { color:var(--cv-gold); }
.cv-blog-card-body p { color:var(--cv-text-muted); font-size:14px; line-height:1.6; margin:0; flex:1; }
.cv-blog-more { color:var(--cv-gold); font-weight:700; font-size:13px; text-decoration:none; }
.cv-blog-more:hover { color:var(--cv-gold-bright); }

.cv-blog-pages { display:flex; justify-content:center; gap:8px; margin-top:36px; flex-wrap:wrap; }
.cv-blog-pages .page-numbers { display:inline-flex; align-items:center; justify-content:center; min-width:38px; height:38px; padding:0 10px; border-radius:var(--cv-radius-full); font-size:13px; font-weight:700; text-decoration:none; background:var(--cv-bg-card); color:var(--cv-text-muted); border:1px solid var(--cv-border); }
.cv-blog-pages .page-numbers.current { background:var(--cv-gold); color:#FFFFFF; border-color:var(--cv-gold); }
.cv-blog-empty { text-align:center; padding:48px 16px; color:var(--cv-text-muted); }

.cv-blog-post { max-width:760px; margin:0 auto; padding:40px 36px 24px; }
.cv-blog-post-head { margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid var(--cv-border-subtle); }
.cv-blog-post-head h1 { font-family:var(--font-display); font-size:34px; line-height:1.2; font-weight:700; margin:0 0 10px; color:var(--cv-text); }
.cv-blog-meta { display:flex; gap:8px; font-size:13px; color:var(--cv-text-dim); }
.cv-blog-post-img { margin:0 0 28px; }
.cv-blog-post-img img { width:100%; height:auto; border-radius:var(--cv-radius); display:block; }
.cv-blog-content { color:var(--cv-text); line-height:1.8; font-size:16px; }
.cv-blog-content img { max-width:100%; height:auto; border-radius:var(--cv-radius-sm); }

.cv-page-content h2 { font-family:var(--font-display); color:var(--cv-gold); margin:28px 0 12px; font-size:22px; }
.cv-page-content h3 { font-family:var(--font-display); color:var(--cv-text); margin:20px 0 10px; font-size:18px; }
.cv-page-content p  { color:var(--cv-text-muted); margin-bottom:16px; }
.cv-page-content a  { color:var(--cv-gold); }
.cv-page-content a:hover { color:var(--cv-gold-bright); }
.cv-page-content ul, .cv-page-content ol { color:var(--cv-text-muted); padding-left:20px; margin-bottom:16px; }
.cv-page-content li { margin-bottom:6px; }
.cv-page-content strong { color:var(--cv-text); }
.cv-page-content blockquote { border-left:3px solid var(--cv-gold); padding:12px 20px; margin:20px 0; background:rgba(242,165,26,0.07); border-radius:0 var(--cv-radius-sm) var(--cv-radius-sm) 0; color:var(--cv-text-muted); font-style:italic; font-family:var(--font-body); }

@media (max-width: 768px) {
    .cv-blog-hero { padding:32px 16px 24px; }
    .cv-blog-hero h1 { font-size:30px; }
    .cv-blog-post { padding:28px 16px 16px; }
    .cv-blog-post-head h1 { font-size:26px; }
    .cv-blog-grid { grid-template-columns:1fr; }
}
</style>
