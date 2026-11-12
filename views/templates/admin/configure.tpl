<div class="panel abw-panel" id="abw-config"
     data-watermark-url="{$abw_watermark_url|escape:'htmlall':'UTF-8'}"
     data-watermark-color-url="{$abw_watermark_color_url|escape:'htmlall':'UTF-8'}"
     data-watermark-white-url="{$abw_watermark_white_url|escape:'htmlall':'UTF-8'}"
     data-watermark-custom-url="{$abw_watermark_custom_url|escape:'htmlall':'UTF-8'}"
     data-batch-endpoint="{$abw_batch_endpoint|escape:'htmlall':'UTF-8'}">
  <div class="panel-heading">
    <i class="icon-picture"></i> A.B. Filigrana prodotti
  </div>

  <div class="alert alert-info">
    Le immagini originali rimangono sempre prive di filigrana. Il modulo modifica soltanto le versioni pubbliche selezionate.
    Tutti i formati prodotto possono essere protetti. Fino a 320px il pattern usa una sola filigrana centrale inclinata e più leggibile; nei formati grandi usa tre filigrane realmente ruotate lungo la diagonale.
  </div>

  <form method="post" action="{$abw_form_action}" enctype="multipart/form-data" class="form-horizontal">
    <div class="row">
      <div class="col-lg-7">
        <div class="form-group">
          <label class="control-label col-lg-4">Attiva filigrana automatica</label>
          <div class="col-lg-8">
            <span class="switch prestashop-switch fixed-width-lg">
              <input type="radio" name="ABW_ACTIVE" id="abw_active_on" value="1"{if $abw_active} checked{/if}>
              <label for="abw_active_on">Sì</label>
              <input type="radio" name="ABW_ACTIVE" id="abw_active_off" value="0"{if !$abw_active} checked{/if}>
              <label for="abw_active_off">No</label>
              <a class="slide-button btn"></a>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_mode">Distribuzione</label>
          <div class="col-lg-8">
            <select name="ABW_MODE" id="abw_mode" class="form-control abw-live-control">
              <option value="corner"{if $abw_mode == 'corner'} selected{/if}>Angolo inferiore destro</option>
              <option value="center"{if $abw_mode == 'center'} selected{/if}>Centrale</option>
              <option value="diagonal"{if $abw_mode == 'diagonal'} selected{/if}>Pattern diagonale ruotato (consigliato)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_variant">Variante grafica</label>
          <div class="col-lg-8">
            <select name="ABW_VARIANT" id="abw_variant" class="form-control">
              <option value="color"{if $abw_variant == 'color'} selected{/if}>Logo originale a colori</option>
              <option value="white"{if $abw_variant == 'white'} selected{/if}>Logo bianco per sfondi scuri</option>
              {if $abw_has_custom_watermark}<option value="custom"{if $abw_variant == 'custom'} selected{/if}>Filigrana personalizzata</option>{/if}
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_opacity">Opacità</label>
          <div class="col-lg-6">
            <input type="range" min="5" max="80" name="ABW_OPACITY" id="abw_opacity" value="{$abw_opacity}" class="abw-live-control">
          </div>
          <div class="col-lg-2"><span class="badge" id="abw_opacity_value">{$abw_opacity}%</span></div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_scale">Larghezza relativa</label>
          <div class="col-lg-6">
            <input type="range" min="10" max="60" name="ABW_SCALE" id="abw_scale" value="{$abw_scale}" class="abw-live-control">
          </div>
          <div class="col-lg-2"><span class="badge" id="abw_scale_value">{$abw_scale}%</span></div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_margin">Margine</label>
          <div class="col-lg-6">
            <input type="range" min="0" max="15" name="ABW_MARGIN" id="abw_margin" value="{$abw_margin}" class="abw-live-control">
          </div>
          <div class="col-lg-2"><span class="badge" id="abw_margin_value">{$abw_margin}%</span></div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_min_size">Dimensione minima</label>
          <div class="col-lg-4">
            <div class="input-group">
              <input type="number" min="64" max="4000" name="ABW_MIN_SIZE" id="abw_min_size" value="{$abw_min_size}" class="form-control">
              <span class="input-group-addon">px</span>
            </div>
          </div>
          <div class="col-lg-4"><p class="help-block">Usa 64px per includere anche carrello, ordini e miniature responsive.</p></div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_file">Nuova filigrana</label>
          <div class="col-lg-8">
            <input type="file" name="ABW_WATERMARK_FILE" id="abw_file" accept="image/png,image/jpeg">
            <p class="help-block">PNG trasparente consigliato, massimo 5 MB. Il caricamento seleziona automaticamente la variante personalizzata.</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_excluded_products">Prodotti esclusi</label>
          <div class="col-lg-8">
            <input type="text" name="ABW_EXCLUDED_PRODUCTS" id="abw_excluded_products" value="{$abw_excluded_products|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="Esempio: 12, 48, 233">
            <p class="help-block">ID prodotto separati da virgola.</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="abw_excluded_categories">Categorie escluse</label>
          <div class="col-lg-8">
            <input type="text" name="ABW_EXCLUDED_CATEGORIES" id="abw_excluded_categories" value="{$abw_excluded_categories|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="Esempio: 4, 18">
            <p class="help-block">Sono escluse le immagini dei prodotti appartenenti alle categorie indicate.</p>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="abw-preview-card">
          <h4>Anteprima configurazione</h4>
          <div class="abw-preview-stage" id="abw_preview_stage">
            <img src="{$abw_preview_url|escape:'htmlall':'UTF-8'}" class="abw-preview-base" alt="Prodotto di esempio">
            <div id="abw_preview_overlays"></div>
          </div>
          <p class="help-block">L’anteprima è indicativa; l’applicazione reale mantiene la risoluzione del formato PrestaShop.</p>
        </div>
      </div>
    </div>

    <hr>
    <h4>Formati da proteggere</h4>
    <div class="row abw-types">
      {foreach from=$abw_image_types item=image_type}
        <div class="col-sm-6 col-md-4 col-lg-3">
          <label class="abw-type-option">
            <input type="checkbox" name="ABW_IMAGE_TYPES[]" value="{$image_type.name|escape:'htmlall':'UTF-8'}"{if $image_type.selected} checked{/if}>
            <strong>{$image_type.name|escape:'htmlall':'UTF-8'}</strong>
            <small>{$image_type.width}×{$image_type.height}px</small>
          </label>
        </div>
      {/foreach}
    </div>

    <div class="panel-footer">
      <button type="submit" name="submitAbwSettings" class="btn btn-default pull-right">
        <i class="process-icon-save"></i> Salva configurazione
      </button>
    </div>
  </form>
</div>

<div class="panel abw-panel">
  <div class="panel-heading"><i class="icon-list"></i> Ultime elaborazioni</div>
  {if $abw_logs}
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr><th>Data</th><th>Immagine</th><th>Operazione</th><th>Stato</th><th>Dettaglio</th></tr>
        </thead>
        <tbody>
          {foreach from=$abw_logs item=log}
            <tr>
              <td>{$log.date_add|escape:'htmlall':'UTF-8'}</td>
              <td>#{$log.id_image}</td>
              <td>{$log.action|escape:'htmlall':'UTF-8'}</td>
              <td><span class="label {if $log.status == 'ok'}label-success{elseif $log.status == 'partial'}label-warning{else}label-danger{/if}">{$log.status|escape:'htmlall':'UTF-8'}</span></td>
              <td>{$log.message|escape:'htmlall':'UTF-8'}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {else}
    <p class="text-muted">Nessuna elaborazione registrata.</p>
  {/if}
</div>

<div class="panel abw-panel" id="abw-batch-panel">
  <div class="panel-heading"><i class="icon-refresh"></i> Catalogo esistente</div>
  <p>Le operazioni vengono eseguite in blocchi prudenti da 3 immagini e possono essere interrotte e riprese.</p>

  {if $abw_catalog_dirty}
    <div class="alert alert-warning">
      Il catalogo contiene o potrebbe contenere immagini filigranate. La disinstallazione resta bloccata finché la pulizia completa non termina senza errori.
    </div>
  {else}
    <div class="alert alert-success">
      Catalogo in stato pulito: non risultano filigrane da rimuovere prima della disinstallazione.
    </div>
  {/if}

  <div class="progress abw-progress">
    <div class="progress-bar" id="abw_progress_bar" role="progressbar" style="width: {$abw_batch_status.percent}%">
      <span id="abw_progress_text">{$abw_batch_status.processed}/{$abw_batch_status.total} ({$abw_batch_status.percent}%; errori: {$abw_batch_status.errors})</span>
    </div>
  </div>

  {if $abw_missing_count > 0}
    <div class="alert alert-warning">
      Immagini senza filigrana rilevate: <strong>{$abw_missing_count}</strong>. Sono immagini caricate o modificate mentre il modulo non era raggiungibile. Usa “Applica solo alle immagini mancanti”: le altre non vengono toccate.
    </div>
  {else}
    <div class="alert alert-success">
      Nessuna immagine risulta priva di filigrana.
    </div>
  {/if}

  <div id="abw_batch_message" class="alert alert-info" style="display:none"></div>
  <button type="button" class="btn btn-primary" id="abw_apply_missing"{if $abw_missing_count <= 0} disabled="disabled"{/if}>
    <i class="icon-magic"></i> Applica solo alle immagini mancanti{if $abw_missing_count > 0} ({$abw_missing_count}){/if}
  </button>
  <button type="button" class="btn btn-primary" id="abw_apply_existing">
    <i class="icon-shield"></i> Rigenera e applica al catalogo
  </button>
  <button type="button" class="btn btn-warning" id="abw_clean_existing">
    <i class="icon-undo"></i> Rigenera il catalogo senza filigrana
  </button>
  <button type="button" class="btn btn-default" id="abw_resume_batch"{if !$abw_batch_status.running} style="display:none"{/if}>
    <i class="icon-play"></i> Riprendi operazione
  </button>

  <div class="alert alert-warning abw-uninstall-warning">
    La pulizia rigenera tutti i formati prodotto, anche quelli non selezionati. Finché non termina senza errori, il modulo impedisce la disinstallazione per evitare filigrane residue.
  </div>
</div>
