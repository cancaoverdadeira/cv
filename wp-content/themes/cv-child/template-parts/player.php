<?php
// cancao-verdadeira-child/template-parts/player.php
// Gerado em: 2026-07-28 21:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// v15.0.0 — Player global fixo no rodapé. Motor duplo: YouTube IFrame API
// (padrão) + MP3 nativo (_cv_audio_url), alternável pelo botão de modo.
// WaveSurfer.js foi removido — a forma de onda animada deu lugar à barra
// de progresso simples (já existia como fallback, agora é o modo padrão).
// Controles: play/pause, anterior, próxima, shuffle, repeat, volume,
// alternância de fonte (YouTube/MP3) e fila.
// v15.33.0: no celular a barra vira linha fina no alto e somem 🔀 🔁 e YouTube/MP3 (seção 23 do cv-ajustes.css).

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<!-- Motor invisível do YouTube — não é exibido, só toca o áudio -->
<div id="cv-yt-engine" style="position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;left:-9999px"></div>

<div class="cv-player" id="cv-player" role="region" aria-label="Player de música">

    <!-- Info da música tocando -->
    <div class="cv-player-info" id="cv-player-info">
        <div class="cv-player-cover" id="cv-player-cover">
            <img id="cv-player-cover-img"
                 src="<?php echo esc_url( CV_CHILD_URL . '/assets/img/default-cover.svg' ); ?>"
                 alt="Capa da música"
                 style="width:100%;height:100%;object-fit:cover" />
        </div>
        <div style="min-width:0;flex:1">
            <div class="cv-player-title"  id="cv-player-title">Nenhuma música</div>
            <div class="cv-player-artist" id="cv-player-artist">Selecione uma música</div>
        </div>
    </div>

    <!-- Controles centrais -->
    <div class="cv-player-controls">
        <button class="cv-player-btn" id="cv-btn-shuffle" title="Aleatório" aria-pressed="false">🔀</button>
        <button class="cv-player-btn" id="cv-btn-prev"   title="Anterior"  aria-label="Anterior">⏮</button>
        <button class="cv-player-btn primary" id="cv-btn-play" title="Play/Pause" aria-label="Reproduzir">▶</button>
        <button class="cv-player-btn" id="cv-btn-next"   title="Próxima"   aria-label="Próxima">⏭</button>
        <button class="cv-player-btn" id="cv-btn-repeat" title="Repetir"   aria-pressed="false">🔁</button>
    </div>

    <!-- Barra de progresso + tempos (alimentada pelo motor YouTube ou MP3) -->
    <div class="cv-player-progress" style="flex:1;max-width:480px">
        <div style="display:flex;align-items:center;gap:8px">
            <span id="cv-player-current" style="font-size:11px;color:var(--cv-text-dim);min-width:34px">0:00</span>
            <div id="cv-player-bar-fallback" class="cv-player-bar" style="flex:1">
                <div class="cv-player-bar-fill" id="cv-player-bar-fill" style="width:0%"></div>
            </div>
            <span id="cv-player-duration" style="font-size:11px;color:var(--cv-text-dim);min-width:34px;text-align:right">0:00</span>
        </div>
    </div>

    <!-- Alternar fonte: YouTube (padrão) ou MP3 da biblioteca -->
    <button class="cv-player-btn" id="cv-btn-mode" title="Alternar fonte de áudio"
            style="font-size:11px;font-weight:700;width:auto;padding:0 10px;white-space:nowrap">🎬 YouTube</button>

    <!-- Volume -->
    <div class="cv-player-volume">
        <button class="cv-player-btn" id="cv-btn-mute" title="Mudo" style="font-size:16px">🔊</button>
        <input type="range" id="cv-volume-slider" min="0" max="100" value="80"
               aria-label="Volume" title="Volume" />
    </div>

    <!-- Fila -->
    <button class="cv-player-btn" id="cv-btn-queue" title="Fila" style="font-size:16px;flex-shrink:0">☰</button>

</div>

<!-- Painel da fila -->
<div id="cv-queue-panel"
     style="display:none;position:fixed;bottom:calc(var(--cv-player-h) + 8px);right:16px;
            width:300px;max-height:380px;background:#FFFFFF;border:1px solid var(--cv-border);
            border-radius:var(--cv-radius);z-index:490;overflow:hidden;box-shadow:var(--cv-shadow-lg)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
                border-bottom:1px solid var(--cv-border-subtle)">
        <span style="font-size:13px;font-weight:700;color:var(--cv-text)">🎵 Fila</span>
        <button id="cv-queue-close"
                style="background:none;border:none;color:var(--cv-text-dim);font-size:18px;cursor:pointer">✕</button>
    </div>
    <div id="cv-queue-list" style="overflow-y:auto;max-height:320px;scrollbar-width:thin">
        <p style="color:var(--cv-text-dim);font-size:13px;text-align:center;padding:24px">
            Fila vazia
        </p>
    </div>
</div>
