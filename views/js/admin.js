(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState !== 'loading') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  }

  ready(function () {
    var root = document.getElementById('abw-config');
    if (!root) {
      return;
    }

    var mode = document.getElementById('abw_mode');
    var variant = document.getElementById('abw_variant');
    var fileInput = document.getElementById('abw_file');
    var opacity = document.getElementById('abw_opacity');
    var scale = document.getElementById('abw_scale');
    var margin = document.getElementById('abw_margin');
    var overlayContainer = document.getElementById('abw_preview_overlays');
    var watermarkUrl = root.getAttribute('data-watermark-url');
    var endpoint = root.getAttribute('data-batch-endpoint');
    var running = false;

    function addPreviewWatermark(left, top, alignRight, rotate) {
      var image = document.createElement('img');
      image.src = watermarkUrl;
      image.alt = '';
      image.className = 'abw-preview-watermark';
      image.style.width = scale.value + '%';
      image.style.opacity = String(Number(opacity.value) / 100);
      image.style.top = top + '%';
      if (rotate) {
        image.style.transform = 'rotate(22deg)';
        image.style.transformOrigin = 'center center';
      }
      if (alignRight) {
        image.style.right = left + '%';
      } else {
        image.style.left = left + '%';
      }
      overlayContainer.appendChild(image);
    }

    function updatePreview() {
      overlayContainer.innerHTML = '';
      document.getElementById('abw_opacity_value').textContent = opacity.value + '%';
      document.getElementById('abw_scale_value').textContent = scale.value + '%';
      document.getElementById('abw_margin_value').textContent = margin.value + '%';

      var watermarkHeightEstimate = Number(scale.value) / 4;
      if (mode.value === 'center') {
        addPreviewWatermark((100 - Number(scale.value)) / 2, (100 - watermarkHeightEstimate) / 2, false, false);
      } else if (mode.value === 'diagonal') {
        addPreviewWatermark(Number(margin.value), 16, false, true);
        addPreviewWatermark((100 - Number(scale.value)) / 2, (100 - watermarkHeightEstimate) / 2, false, true);
        addPreviewWatermark(Number(margin.value), 78 - watermarkHeightEstimate, true, true);
      } else {
        addPreviewWatermark(Number(margin.value), 100 - watermarkHeightEstimate - Number(margin.value), true, false);
      }
    }

    [mode, opacity, scale, margin].forEach(function (control) {
      control.addEventListener('input', updatePreview);
      control.addEventListener('change', updatePreview);
    });

    variant.addEventListener('change', function () {
      var attribute = 'data-watermark-' + variant.value + '-url';
      watermarkUrl = root.getAttribute(attribute) || root.getAttribute('data-watermark-url');
      updatePreview();
    });

    fileInput.addEventListener('change', function () {
      if (!fileInput.files || !fileInput.files[0]) {
        return;
      }
      var reader = new FileReader();
      reader.onload = function (event) {
        watermarkUrl = event.target.result;
        updatePreview();
      };
      reader.readAsDataURL(fileInput.files[0]);
    });
    updatePreview();

    function request(action, data) {
      var body = new URLSearchParams(data || {});
      body.set('ajax', '1');
      body.set('action', action);
      return fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString()
      }).then(function (response) {
        return response.json();
      });
    }

    function renderStatus(status) {
      var bar = document.getElementById('abw_progress_bar');
      var text = document.getElementById('abw_progress_text');
      bar.style.width = status.percent + '%';
      text.textContent = status.processed + '/' + status.total + ' (' + status.percent + '%; errori: ' + status.errors + ')';
    }

    function showMessage(message, error) {
      var box = document.getElementById('abw_batch_message');
      box.className = 'alert ' + (error ? 'alert-danger' : 'alert-info');
      box.textContent = message;
      box.style.display = 'block';
    }

    function runNextStep() {
      if (!running) {
        return;
      }
      request('step').then(function (payload) {
        if (!payload.success) {
          throw new Error(payload.message || 'Operazione non riuscita.');
        }
        renderStatus(payload.status);
        if (payload.status.running) {
          window.setTimeout(runNextStep, 120);
        } else {
          running = false;
          if (payload.status.errors > 0) {
            showMessage('Elaborazione terminata con ' + payload.status.errors + ' errori. Controlla il registro e ripeti la pulizia prima di disinstallare.', true);
          } else {
            showMessage('Elaborazione completata senza errori.', false);
          }
        }
      }).catch(function (error) {
        running = false;
        showMessage(error.message, true);
        document.getElementById('abw_resume_batch').style.display = 'inline-block';
      });
    }

    var questions = {
      apply: 'Rigenerare e filigranare le immagini del catalogo esistente?',
      missing: 'Applicare la filigrana solo alle immagini che ne risultano prive? Le immagini già filigranate non vengono toccate.',
      clean: 'Rigenerare tutti i formati immagine del catalogo e rimuovere completamente le filigrane?'
    };

    function start(modeValue) {
      var question = questions[modeValue];
      if (!window.confirm(question)) {
        return;
      }
      request('start', {mode: modeValue}).then(function (payload) {
        if (!payload.success) {
          throw new Error(payload.message || 'Avvio non riuscito.');
        }
        renderStatus(payload.status);
        running = true;
        showMessage('Elaborazione in corso. Non chiudere questa pagina.', false);
        runNextStep();
      }).catch(function (error) {
        showMessage(error.message, true);
      });
    }

    var applyMissing = document.getElementById('abw_apply_missing');
    if (applyMissing) {
      applyMissing.addEventListener('click', function () { start('missing'); });
    }
    document.getElementById('abw_apply_existing').addEventListener('click', function () { start('apply'); });
    document.getElementById('abw_clean_existing').addEventListener('click', function () { start('clean'); });
    document.getElementById('abw_resume_batch').addEventListener('click', function () {
      running = true;
      showMessage('Operazione ripresa.', false);
      runNextStep();
    });
  });
}());
