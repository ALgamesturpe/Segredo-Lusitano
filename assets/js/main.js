// ============================================================
// SEGREDO LUSITANO — JavaScript Principal
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // --- Navbar scroll ---
  const navbar = document.getElementById('navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });
  }

  // --- Hamburger menu ---
  const ham   = document.getElementById('hamburger');
  const links = document.getElementById('nav-links');
  if (ham && links) {
    ham.addEventListener('click', () => {
      ham.classList.toggle('open');
      links.classList.toggle('open');
    });
    links.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        ham.classList.remove('open');
        links.classList.remove('open');
      });
    });
  }

  // --- Dropdown do utilizador ---
  const dropdownToggle  = document.getElementById('dropdown-toggle');
  const dropdownMenu    = document.getElementById('user-dropdown-menu');
  const dropdownChevron = document.getElementById('dropdown-chevron');

  if (dropdownToggle && dropdownMenu) {
    dropdownToggle.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const isOpen = dropdownMenu.classList.contains('open');
      if (isOpen) {
        dropdownMenu.classList.remove('open');
        if (dropdownChevron) dropdownChevron.classList.remove('rotated');
      } else {
        dropdownMenu.classList.add('open');
        if (dropdownChevron) dropdownChevron.classList.add('rotated');
      }
    });

    document.addEventListener('click', function(e) {
      if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.remove('open');
        if (dropdownChevron) dropdownChevron.classList.remove('rotated');
      }
    });

    dropdownMenu.querySelectorAll('a').forEach(function(a) {
      a.addEventListener('click', function() {
        dropdownMenu.classList.remove('open');
      });
    });
  }

  // --- Flash auto-dismiss ---
  document.querySelectorAll('.flash').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4500);
  });

  // --- Like button (AJAX) ---
  const likeBtn = document.getElementById('like-btn');
  if (likeBtn) {
    likeBtn.addEventListener('click', async () => {
      const localId = likeBtn.dataset.local;
      const res = await fetch(`${SITE_URL}/pages/like.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `local_id=${localId}`
      });
      if (res.status === 401) {
        if (typeof mostrarAvisoLogin === 'function') {
          mostrarAvisoLogin('Precisas de iniciar sessão para dar like.', `${SITE_URL}/pages/login.php`);
        }
        return;
      }
      const data = await res.json();
      likeBtn.classList.toggle('liked', data.liked);
      const countEl = document.getElementById('like-count');
      if (countEl) countEl.textContent = data.total;
    });
  }

  // --- Upload areas com preview de imagem ---
  initAllUploadAreas();

  // --- Mapa mini na página de registo/edição ---
  if (document.getElementById('mini-map')) {
    initMiniMap();
  }

  // --- Contador de caracteres ---
  document.querySelectorAll('[data-maxlength]').forEach(el => {
    const max = parseInt(el.dataset.maxlength);
    const counter = document.querySelector(`[data-counter-for="${el.id}"]`);
    if (counter) {
      const update = () => { counter.textContent = `${el.value.length}/${max}`; };
      el.addEventListener('input', update);
      update();
    }
  });

  // --- Confirm dialogs ---
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

});

// ============================================================
// Upload areas com preview visual de imagem
// ============================================================
function initAllUploadAreas() {
  // Prevenir que o browser navegue para o URL da imagem arrastada
  document.addEventListener('dragover', e => e.preventDefault());
  document.addEventListener('drop',     e => e.preventDefault());

  document.querySelectorAll('.upload-area').forEach(area => {
    const inputId = area.dataset.inputId;
    let input = inputId ? document.getElementById(inputId) : null;
    if (!input) {
      const sib = area.nextElementSibling;
      if (sib && sib.tagName === 'INPUT' && sib.type === 'file') input = sib;
    }
    if (!input) return;

    let preview = area.querySelector('.upload-area-preview');
    if (!preview) {
      preview = document.createElement('img');
      preview.className = 'upload-area-preview';
      preview.style.cssText = 'width:100%;max-height:200px;object-fit:cover;border-radius:8px;margin-top:.75rem;display:none;border:2px solid #40916c;';
      area.appendChild(preview);
    }

    area.addEventListener('click', (e) => {
      if (e.target === preview) return;
      input.click();
    });

    area.addEventListener('dragover', e => {
      e.preventDefault();
      e.stopPropagation();
      area.classList.add('drag');
    });

    area.addEventListener('dragleave', e => {
      e.stopPropagation();
      area.classList.remove('drag');
    });

    area.addEventListener('drop', async e => {
      e.preventDefault();
      e.stopPropagation();
      area.classList.remove('drag');

      const files = e.dataTransfer.files;

      if (files && files.length > 0) {
        // Ficheiro local arrastado diretamente
        const dt = new DataTransfer();
        Array.from(files).forEach(f => dt.items.add(f));
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));

      } else {
        // Imagem arrastada do browser (URL) — buscar como blob
        const url = e.dataTransfer.getData('text/uri-list') || e.dataTransfer.getData('text/plain');
        if (!url || !url.startsWith('http')) return;

        const label = area.querySelector('.upload-label');
        if (label) label.textContent = 'A carregar imagem...';

        try {
          const res  = await fetch(url);
          const blob = await res.blob();
          if (!blob.type.startsWith('image/')) { if (label) label.textContent = 'Só são aceites imagens.'; return; }
          const ext  = blob.type.split('/')[1] || 'jpg';
          const file = new File([blob], 'imagem.' + ext, { type: blob.type });
          const dt   = new DataTransfer();
          dt.items.add(file);
          input.files = dt.files;
          input.dispatchEvent(new Event('change'));
        } catch {
          if (label) label.textContent = 'Não foi possível carregar a imagem. Guarda-a primeiro e arrasta o ficheiro.';
        }
      }
    });

    input.addEventListener('change', () => showUploadPreview(input, area, preview));
  });
}

function showUploadPreview(input, area, preview) {
  const files = Array.from(input.files).filter(f => f.type.startsWith('image/'));
  if (!files.length) return;
  const file = files[0];
  const reader = new FileReader();
  reader.onload = (e) => {
    preview.src = e.target.result;
    preview.style.display = 'block';
    area.classList.add('has-file');
    const label = area.querySelector('.upload-label');
    if (label) {
      label.textContent = files.length > 1
        ? `✓ ${files.length} fotos selecionadas`
        : `✓ ${file.name}`;
    }
    const icon = area.querySelector('.upload-icon');
    if (icon) { icon.className = 'fas fa-check-circle upload-icon'; icon.style.color = '#40916c'; }
  };
  reader.readAsDataURL(file);
}

// ============================================================
// Mapa mini (registo/edição de local) + geolocalização
// ============================================================
function initMiniMap() {
  const latInput = document.getElementById('latitude');
  const lngInput = document.getElementById('longitude');
  const map = L.map('mini-map').setView([39.5, -8.0], 6);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap © CARTO', maxZoom: 18
  }).addTo(map);

  let marker = null;

  function setMarker(lat, lng) {
    latInput.value = parseFloat(lat).toFixed(7);
    lngInput.value = parseFloat(lng).toFixed(7);
    if (marker) marker.remove();
    marker = L.marker([lat, lng], {
      icon: L.divIcon({
        className: '',
        html: `<div style="background:#1a3a2a;border:3px solid #c9a84c;border-radius:50% 50% 50% 0;transform:rotate(-45deg);width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.3);"><i class='fas fa-map-pin' style='transform:rotate(45deg);color:#c9a84c;font-size:.7rem;'></i></div>`,
        iconSize: [32,32], iconAnchor: [16,32]
      })
    }).addTo(map);
    map.setView([lat, lng], Math.max(map.getZoom(), 13));
  }

  if (latInput.value && lngInput.value) {
    setMarker(parseFloat(latInput.value), parseFloat(lngInput.value));
  }

  map.on('click', e => setMarker(e.latlng.lat, e.latlng.lng));

  // --- Botão de geolocalização ---
  const geoBtn = document.getElementById('btn-geolocalizacao');
  if (geoBtn) {
    geoBtn.addEventListener('click', () => {
      if (!navigator.geolocation) { alert('O teu browser não suporta geolocalização.'); return; }
      geoBtn.disabled = true;
      geoBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A localizar...';
      navigator.geolocation.getCurrentPosition(
        pos => {
          setMarker(pos.coords.latitude, pos.coords.longitude);
          geoBtn.disabled = false;
          geoBtn.innerHTML = '<i class="fas fa-crosshairs"></i> Usar Localização Atual';
        },
        err => {
          alert('Não foi possível obter a localização: ' + err.message);
          geoBtn.disabled = false;
          geoBtn.innerHTML = '<i class="fas fa-crosshairs"></i> Usar Localização Atual';
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    });
  }
}

// ============================================================
// Mapa principal (mapa.php)
// ============================================================
function initMainMap(locais) {
  // Verificar se há um local para abrir via URL
  const params = new URLSearchParams(window.location.search);
  const abrirId = params.get('abrir') ? parseInt(params.get('abrir')) : null;

  // Se houver um local para abrir, centrar nele; caso contrário mostrar Portugal
  let localAbrir = null;
  if (abrirId) {
    localAbrir = locais.find(l => l.id === abrirId) || null;
  }

  const viewInicial = localAbrir
    ? [parseFloat(localAbrir.latitude), parseFloat(localAbrir.longitude)]
    : [39.5, -8.0];
  const zoomInicial = localAbrir ? 14 : 7;

  const map = L.map('map').setView(viewInicial, zoomInicial);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a> © <a href="https://carto.com">CARTO</a>',
    maxZoom: 18
  }).addTo(map);

  const makeIcon = (cat) => L.divIcon({
    className: '',
    html: `<div style="background:#1a3a2a;border:3px solid #c9a84c;border-radius:50% 50% 50% 0;transform:rotate(-45deg);width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.3);"><i class='${cat}' style='transform:rotate(45deg);color:#c9a84c;font-size:.7rem;'></i></div>`,
    iconSize: [32,32], iconAnchor: [16,32], popupAnchor: [0,-34]
  });

  locais.forEach(l => {
    const m = L.marker([parseFloat(l.latitude), parseFloat(l.longitude)], { icon: makeIcon(l.icone) });
    m.addTo(map);
    let img = l.foto_capa ? `<img src="${SITE_URL}/uploads/locais/${l.foto_capa}" alt="" style="width:100%;height:90px;object-fit:cover;border-radius:8px;margin-bottom:.5rem;">` : '';
    m.bindPopup(`
      <div style="min-width:200px;font-family:'Outfit',sans-serif;">
        ${img}
        <div style="font-family:'Playfair Display',serif;font-weight:700;font-size:1rem;color:#1a3a2a;">${l.nome}</div>
        <div style="font-size:.8rem;color:#6b7280;margin:.2rem 0 .6rem;">${l.categoria_nome} · ${l.regiao_nome}</div>
        <a href="${SITE_URL}/pages/local.php?id=${l.id}" style="display:inline-flex;align-items:center;gap:.35rem;color:#2d6a4f;font-size:.85rem;font-weight:700;">
          Ver local <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    `);

    // Abrir popup do local pretendido
    if (abrirId && l.id === abrirId) {
      m.openPopup();
    }
  });
}