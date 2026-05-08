<?php
// ============================================================
//  CONFIGURAÇÃO
// ============================================================
$PASTA_FOTOS  = __DIR__ . '/Fotos';  // Pasta com as fotos
$INTERVALO_MS = 10000;               // Tempo por foto em milissegundos
$TRANSICAO_MS = 900;                 // Duração do fade entre fotos
// ============================================================

$extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];

if (isset($_GET['img'])) {
    $nome    = basename($_GET['img']);
    $caminho = realpath($PASTA_FOTOS . DIRECTORY_SEPARATOR . $nome);
    $base    = realpath($PASTA_FOTOS);
    if ($caminho && $base && strpos($caminho, $base) === 0 && is_file($caminho)) {
        $ext  = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'jpg','jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            'gif'        => 'image/gif',
            'webp'       => 'image/webp',
            'avif'       => 'image/avif',
            'bmp'        => 'image/bmp',
            default      => 'application/octet-stream',
        };
        header("Content-Type: $mime");
        header('Cache-Control: max-age=86400');
        readfile($caminho);
        exit;
    }
    http_response_code(404);
    exit;
}

$fotos = [];
if (is_dir($PASTA_FOTOS)) {
    foreach (scandir($PASTA_FOTOS) as $item) {
        if ($item === '.' || $item === '..') continue;
        $caminho = $PASTA_FOTOS . DIRECTORY_SEPARATOR . $item;
        if (!is_file($caminho)) continue;
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (in_array($ext, $extensoes, true)) $fotos[] = $item;
    }
    natsort($fotos);
    $fotos = array_values($fotos);
}

$fotosJson = json_encode(array_map(fn($f) => "?img=" . rawurlencode($f), $fotos));
$total     = count($fotos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Slideshow</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  html, body {
    width: 100%; height: 100%;
    background: #000;
    overflow: hidden;
    cursor: none;
  }

  .slide {
    position: fixed;
    inset: 0;
    opacity: 0;
    transition: opacity <?= $TRANSICAO_MS ?>ms ease-in-out;
    will-change: opacity;
  }
  .slide.active  { opacity: 1; }
  .slide.leaving { opacity: 0; }

  .slide-bg {
    position: absolute;
    inset: -40px;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    filter: blur(28px) brightness(0.45) saturate(0.7);
  }

  .slide-fg {
    position: absolute;
    inset: 0;
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
  }

  #progress-bar {
    position: fixed;
    bottom: 0; left: 0;
    height: 3px;
    background: rgba(255,255,255,0.5);
    width: 0%;
    z-index: 20;
  }

  #counter {
    position: fixed;
    bottom: 12px; right: 18px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    color: rgba(255,255,255,0.28);
    letter-spacing: 0.15em;
    z-index: 20;
    user-select: none;
  }

  #empty {
    position: fixed; inset: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 14px;
    color: rgba(255,255,255,0.3);
    font-family: 'Courier New', monospace;
    font-size: 13px; letter-spacing: 0.12em;
  }
</style>
</head>
<body>

<?php if ($total === 0): ?>
  <div id="empty">
    <p>NENHUMA IMAGEM ENCONTRADA</p>
    <p style="opacity:.5;font-size:10px"><?= htmlspecialchars(realpath($PASTA_FOTOS) ?: $PASTA_FOTOS) ?></p>
  </div>
<?php else: ?>

  <div id="slide-a" class="slide">
    <div class="slide-bg"></div>
    <div class="slide-fg"></div>
  </div>
  <div id="slide-b" class="slide">
    <div class="slide-bg"></div>
    <div class="slide-fg"></div>
  </div>

  <div id="progress-bar"></div>
  <div id="counter">1 / <?= $total ?></div>

  <script>
    const fotos     = <?= $fotosJson ?>;
    const total     = fotos.length;
    const intervalo = <?= $INTERVALO_MS ?>;
    const transicao = <?= $TRANSICAO_MS ?>;

    const slA     = document.getElementById('slide-a');
    const slB     = document.getElementById('slide-b');
    const bar     = document.getElementById('progress-bar');
    const counter = document.getElementById('counter');

    let atual       = 0;
    let camadaAtiva = slA;
    let camadaProx  = slB;

    function preload(src) {
      return new Promise(resolve => {
        const img = new Image();
        img.onload = img.onerror = () => resolve();
        img.src = src;
      });
    }

    function animarBarra(ms) {
      bar.style.transition = 'none';
      bar.style.width = '0%';
      void bar.offsetWidth;
      // Desconta a transição para a barra chegar em 100% junto com o fade
      bar.style.transition = `width ${ms - transicao}ms linear`;
      bar.style.width = '100%';
    }

    function setSlide(el, src) {
      const url = src ? `url('${src}')` : '';
      el.querySelector('.slide-bg').style.backgroundImage = url;
      el.querySelector('.slide-fg').style.backgroundImage = url;
    }

    async function mostrarFoto(idx) {
      const src = fotos[idx];
      await preload(src);

      setSlide(camadaProx, src);
      camadaProx.classList.add('active');
      camadaAtiva.classList.add('leaving');

      setTimeout(() => {
        camadaAtiva.classList.remove('active', 'leaving');
        setSlide(camadaAtiva, '');
        [camadaAtiva, camadaProx] = [camadaProx, camadaAtiva];
      }, transicao + 50);

      counter.textContent = `${idx + 1} / ${total}`;
      animarBarra(intervalo);
    }

    (async () => {
      await mostrarFoto(0);
      setInterval(() => {
        if (atual === total - 1) {
          location.reload();
          return;
        }
        atual++;
        mostrarFoto(atual);
      }, intervalo);
    })();
  </script>

<?php endif; ?>
</body>
</html>
