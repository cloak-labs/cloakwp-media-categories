/**
 * Media Categories — admin media library enhancements.
 *
 * - Grid / modal taxonomy filter
 * - Bulk-select "Edit categories" button + panel
 * - Attachment sidebar "Add New" term
 * - List-view bulk assign panel
 */
(function (window, $) {
  'use strict';

  var settings = window.mediaCategoriesAdmin || null;
  if (!settings) {
    return;
  }

  var taxonomy = settings.taxonomy;
  var labels = settings.labels || {};
  var openFilterView = null;
  var filterDocBound = false;
  var categoryFilterViews = [];

  // WP persists term names via _wp_specialchars (`&` → `&amp;`). Decode once so
  // later .text()/escapeHtml() encode for HTML instead of showing a literal entity.
  function decodeHtmlEntities(text) {
    if (text == null || text === '') {
      return '';
    }
    return $('<textarea></textarea>').html(String(text)).val();
  }

  (settings.terms || []).forEach(function (term) {
    if (term) {
      term.name = decodeHtmlEntities(term.name);
    }
  });

  function termPrefix(depth) {
    var prefix = '';
    var d = parseInt(depth, 10) || 0;
    while (d-- > 0) {
      prefix += '- ';
    }
    return prefix;
  }

  function encodeFilterValue(mode, selected) {
    selected = selected || [];
    if (!selected.length) {
      return '';
    }
    if (selected.indexOf(settings.uncategorized) !== -1) {
      return mode === 'not' ? 'not:' + settings.uncategorized : settings.uncategorized;
    }
    var ids = selected
      .map(function (id) {
        return parseInt(id, 10);
      })
      .filter(function (id) {
        return id > 0;
      });
    if (!ids.length) {
      return '';
    }
    var joined = ids.join(',');
    return mode === 'not' ? 'not:' + joined : joined;
  }

  function parseFilterValue(value) {
    var raw = $.trim(String(value == null ? '' : value));
    var mode = 'in';
    if (raw.indexOf('not:') === 0) {
      mode = 'not';
      raw = $.trim(raw.slice(4));
    }
    if (!raw || raw === '0') {
      return { mode: mode, selected: [] };
    }
    if (raw === settings.uncategorized) {
      return { mode: mode, selected: [settings.uncategorized] };
    }
    var selected = raw
      .split(',')
      .map(function (part) {
        return parseInt($.trim(part), 10);
      })
      .filter(function (id) {
        return id > 0;
      });
    return { mode: mode, selected: selected };
  }

  function bindFilterDocumentEvents() {
    if (filterDocBound) {
      return;
    }
    filterDocBound = true;
    $(document).on('click.mediaCategoriesFilter', function (e) {
      if (!openFilterView) {
        return;
      }
      if ($(e.target).closest('.media-categories-filter-wrap').length) {
        return;
      }
      openFilterView.closePanel();
    });
    $(document).on('keydown.mediaCategoriesFilter', function (e) {
      if ((e.key === 'Escape' || e.keyCode === 27) && openFilterView) {
        openFilterView.closePanel();
      }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Shared bulk panel                                                  */
  /* ------------------------------------------------------------------ */

  var $bulkPanel = null;
  var currentBulkIds = [];
  var bulkSelectController = null;
  var bulkTermsAbort = null;
  var bulkTermsRequestId = 0;

  function bulkTermOptionsHtml() {
    return (settings.terms || [])
      .map(function (term) {
        return (
          '<label class="media-categories-bulk-term">' +
          '<input type="checkbox" value="' +
          term.id +
          '" /> ' +
          $('<div>').text(termPrefix(term.depth) + term.name).html() +
          '</label>'
        );
      })
      .join('');
  }

  function renderBulkTerms(checkedIds) {
    if (!$bulkPanel) {
      return;
    }
    var checked = (checkedIds || []).map(String);
    $bulkPanel.find('.media-categories-bulk-terms').html(bulkTermOptionsHtml());
    if (checked.length) {
      $bulkPanel.find('.media-categories-bulk-terms input[type="checkbox"]').each(function () {
        this.checked = checked.indexOf(String(this.value)) !== -1;
      });
    }
  }

  function refreshFilterTermLists() {
    categoryFilterViews.forEach(function (view) {
      if (!view || !view.$el || !view.$el.length) {
        return;
      }
      var $box = view.$('.media-categories-filter-terms');
      if (!$box.length) {
        return;
      }
      $box.html(view.termRowsHtml());
      if (typeof view.syncPanelState === 'function') {
        view.syncPanelState();
      }
      if (typeof view.updateToggleLabel === 'function') {
        view.updateToggleLabel();
      }
    });
  }

  function applyFetchedTerms(terms) {
    settings.terms = (terms || []).map(function (term) {
      return {
        id: parseInt(term.id, 10),
        name: decodeHtmlEntities(term.name),
        slug: term.slug,
        parent: parseInt(term.parent, 10) || 0,
        count: parseInt(term.count, 10) || 0,
        depth: parseInt(term.depth, 10) || 0,
      };
    });
    renderBulkTerms(selectedTermIds());
    refreshFilterTermLists();
    $('.media-categories-attachment-field').each(function () {
      syncSidebarField($(this));
    });
  }

  function setBulkRefreshBusy(busy) {
    if (!$bulkPanel) {
      return;
    }
    var $btn = $bulkPanel.find('.media-categories-bulk-refresh');
    $btn.prop('disabled', !!busy).toggleClass('is-busy', !!busy);
    $btn.attr('aria-busy', busy ? 'true' : 'false');
  }

  function refreshBulkTerms() {
    if (!window.wp || !wp.apiFetch) {
      if ($bulkPanel) {
        $bulkPanel
          .find('.media-categories-bulk-status')
          .text(labels.refreshError || 'Could not refresh categories.');
      }
      return;
    }

    if (bulkTermsAbort && typeof bulkTermsAbort.abort === 'function') {
      bulkTermsAbort.abort();
    }
    bulkTermsAbort =
      typeof AbortController !== 'undefined' ? new AbortController() : null;
    var requestId = ++bulkTermsRequestId;

    var $status = $bulkPanel ? $bulkPanel.find('.media-categories-bulk-status') : $();
    setBulkRefreshBusy(true);
    if ($status.length) {
      $status.text('');
    }

    wp.apiFetch({
      path: '/media-categories/v1/terms',
      signal: bulkTermsAbort ? bulkTermsAbort.signal : undefined,
    })
      .then(function (data) {
        if (requestId !== bulkTermsRequestId) {
          return;
        }
        applyFetchedTerms(Array.isArray(data) ? data : []);
      })
      .catch(function (error) {
        if (requestId !== bulkTermsRequestId) {
          return;
        }
        if (error && error.name === 'AbortError') {
          return;
        }
        if ($status.length) {
          $status.text(labels.refreshError || 'Could not refresh categories.');
        }
      })
      .then(function () {
        if (requestId !== bulkTermsRequestId) {
          return;
        }
        bulkTermsAbort = null;
        setBulkRefreshBusy(false);
      });
  }

  function ensureBulkPanel() {
    if ($bulkPanel) {
      return $bulkPanel;
    }

    var refreshLabel = labels.refresh || 'Refresh categories';

    $bulkPanel = $(
      '<div class="media-categories-bulk-panel hidden" role="dialog" aria-label="' +
        (labels.bulkEdit || 'Edit categories') +
        '">' +
        '<div class="media-categories-bulk-panel__inner">' +
        '<div class="media-categories-bulk-panel__header">' +
        '<p class="media-categories-bulk-panel__title"></p>' +
        '<button type="button" class="media-categories-bulk-refresh" aria-label="' +
        $('<div>').text(refreshLabel).html() +
        '">' +
        '<span class="dashicons dashicons-update" aria-hidden="true"></span>' +
        '</button>' +
        '</div>' +
        '<div class="media-categories-bulk-terms"></div>' +
        '<div class="media-categories-bulk-actions">' +
        '<button type="button" class="button button-primary media-categories-bulk-add">' +
        (labels.addToSelected || 'Add to selected') +
        '</button> ' +
        '<button type="button" class="button media-categories-bulk-remove">' +
        (labels.removeFromSelected || 'Remove from selected') +
        '</button> ' +
        '<button type="button" class="button-link media-categories-bulk-cancel">' +
        (labels.cancel || 'Cancel') +
        '</button>' +
        '</div>' +
        '<p class="media-categories-bulk-status" aria-live="polite"></p>' +
        '</div>' +
        '</div>'
    );

    $('body').append($bulkPanel);
    renderBulkTerms();

    $bulkPanel.on('click', '.media-categories-bulk-cancel', closeBulkPanel);
    $bulkPanel.on('click', '.media-categories-bulk-refresh', function (e) {
      e.preventDefault();
      refreshBulkTerms();
    });
    $bulkPanel.on('click', '.media-categories-bulk-add', function () {
      submitBulk(true);
    });
    $bulkPanel.on('click', '.media-categories-bulk-remove', function () {
      submitBulk(false);
    });

    return $bulkPanel;
  }

  function openBulkPanel(ids, $anchor, controller) {
    var $panel = ensureBulkPanel();
    currentBulkIds = ids || [];
    bulkSelectController = controller || null;
    $panel
      .find('.media-categories-bulk-panel__title')
      .text(
        (labels.bulkEdit || 'Edit categories') +
          ' (' +
          currentBulkIds.length +
          ')'
      );
    $panel.find('input[type="checkbox"]').prop('checked', false);
    $panel.find('.media-categories-bulk-status').text('');
    $panel.removeClass('hidden');
    positionBulkPanel($anchor);
  }

  /**
   * Panel is position:fixed (viewport). jQuery offset() is document-relative,
   * so after scrolling the library it would place the panel thousands of
   * pixels below the screen. Use the anchor's visible box instead.
   */
  function positionBulkPanel($anchor) {
    if (!$bulkPanel) {
      return;
    }

    var gutter = 16;
    var margin = 8;
    var panelWidth = $bulkPanel.outerWidth() || 280;
    var panelHeight = $bulkPanel.outerHeight() || 320;
    var viewportW = window.innerWidth;
    var viewportH = window.innerHeight;
    var top = Math.max(gutter, 80);
    var left = Math.max(gutter, (viewportW - panelWidth) / 2);

    if ($anchor && $anchor.length) {
      var rect = $anchor[0].getBoundingClientRect();
      top = rect.bottom + margin;
      left = rect.left;

      if (left + panelWidth > viewportW - gutter) {
        left = viewportW - panelWidth - gutter;
      }
      if (left < gutter) {
        left = gutter;
      }

      if (top + panelHeight > viewportH - gutter) {
        var above = rect.top - panelHeight - margin;
        top = above >= gutter ? above : Math.max(gutter, viewportH - panelHeight - gutter);
      }
    }

    $bulkPanel.css({ top: Math.round(top), left: Math.round(left) });
  }

  function closeBulkPanel() {
    if (bulkTermsAbort && typeof bulkTermsAbort.abort === 'function') {
      bulkTermsAbort.abort();
      bulkTermsAbort = null;
    }
    bulkTermsRequestId += 1;
    if ($bulkPanel) {
      $bulkPanel.addClass('hidden');
      setBulkRefreshBusy(false);
      $bulkPanel.find('.media-categories-bulk-status').text('');
    }
    currentBulkIds = [];
    bulkSelectController = null;
  }

  function exitBulkSelectMode() {
    var controller = bulkSelectController;
    if (controller && typeof controller.trigger === 'function') {
      controller.trigger('selection:action:done');
    }
    if (controller && typeof controller.isModeActive === 'function' && !controller.isModeActive('select')) {
      return;
    }
    var $toggle = $('.media-toolbar-mode-select .select-mode-toggle-button');
    if ($toggle.length) {
      $toggle.trigger('click');
    }
  }

  function selectedTermIds() {
    if (!$bulkPanel) {
      return [];
    }
    return $bulkPanel
      .find('.media-categories-bulk-terms input:checked')
      .map(function () {
        return parseInt(this.value, 10);
      })
      .get();
  }

  function submitBulk(append) {
    var termIds = selectedTermIds();
    if (append && !termIds.length) {
      $bulkPanel
        .find('.media-categories-bulk-status')
        .text('Select at least one category.');
      return;
    }

    var $status = $bulkPanel.find('.media-categories-bulk-status');
    $status.text('Updating…');

    fetch(settings.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': settings.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        attachment_ids: currentBulkIds,
        term_ids: termIds,
        append: append,
      }),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          $status.text(
            (result.data && result.data.message) ||
              labels.bulkError ||
              'Could not update categories.'
          );
          return;
        }

        var count = (result.data.updated || []).length;
        var isListBulk =
          window.location.search.indexOf('media_categories_bulk') !== -1 ||
          window.mediaCategoriesBulkIds;

        if (window.wp && wp.media && wp.media.frame) {
          var library = wp.media.frame.state().get('library');
          if (library && library.props) {
            library.props.set({ ignore: +new Date() });
          }
        }

        if (isListBulk) {
          $status.text(
            (labels.bulkSuccess || 'Categories updated.') + ' (' + count + ')'
          );
          setTimeout(function () {
            closeBulkPanel();
            var url = new URL(window.location.href);
            url.searchParams.delete('media_categories_bulk');
            url.searchParams.delete('media_ids');
            url.searchParams.set('media_categories_updated', String(count));
            window.location.href = url.toString();
          }, 600);
          return;
        }

        exitBulkSelectMode();
        closeBulkPanel();
      })
      .catch(function () {
        $status.text(labels.bulkError || 'Could not update categories.');
      });
  }

  /* ------------------------------------------------------------------ */
  /* Grid / modal: patch AttachmentsBrowser                             */
  /* ------------------------------------------------------------------ */

  var MediaCategoryFilter = null;
  var AssignCategoriesButton = null;
  var boundClearFilters = false;

  function resetCategoryFilterView(view) {
    if (!view) {
      return;
    }
    view.filterMode = 'in';
    view.selected = [];
    if (view.options && view.options.$input && view.options.$input.length) {
      view.options.$input.val('');
    }
    if (view.$el && view.$el.length && view.$('.media-categories-filter-toggle').length) {
      view.syncPanelState();
      view.updateToggleLabel();
      if (typeof view.closePanel === 'function') {
        view.closePanel();
      }
    }
    if (view.live && typeof view.applyFilter === 'function') {
      view.applyFilter();
    }
  }

  function resetAllCategoryFilterViews() {
    categoryFilterViews.forEach(resetCategoryFilterView);
    $('#media-categories-filter-value').val('');
    $('#media-categories-filter').val('').attr('data-encoded', '');
  }

  function bindClearFilters() {
    if (boundClearFilters) {
      return;
    }
    if (window.cloakwpMediaLibrary && typeof cloakwpMediaLibrary.onClear === 'function') {
      cloakwpMediaLibrary.onClear(function () {
        resetAllCategoryFilterViews();
      });
      boundClearFilters = true;
      return;
    }
    $(document).on('cloakwp.mediaLibrary.clearFilters', function () {
      resetAllCategoryFilterViews();
    });
    boundClearFilters = true;
  }

  function ensureMediaViews() {
    if (MediaCategoryFilter) {
      return true;
    }
    if (!window.wp || !wp.media || !wp.media.view || !wp.media.View) {
      return false;
    }

    MediaCategoryFilter = wp.media.View.extend({
      tagName: 'div',
      className: 'media-categories-filter-wrap',

      events: {
        'click .media-categories-filter-toggle': 'onToggle',
        'click .media-categories-filter-mode-btn': 'onMode',
        'click .media-categories-filter-clear': 'onClear',
        'change .media-categories-filter-term': 'onTermChange',
      },

      initialize: function () {
        this.live = this.options.live !== false;
        this.panelOpen = false;
        this.filterMode = 'in';
        this.selected = [];
        this.toggleId =
          this.options.toggleId || 'media-categories-attachment-filter-' + this.cid;
        this.syncFromModel();
        if (this.model && typeof this.model.on === 'function') {
          this.model.on('change:' + taxonomy, this.syncFromModel, this);
        }
      },

      syncFromModel: function () {
        var encoded = '';
        if (this.options.encoded != null && this.options.encoded !== '') {
          encoded = this.options.encoded;
          this.options.encoded = null;
        } else if (this.model && typeof this.model.get === 'function') {
          encoded = this.model.get(taxonomy) || '';
        }
        var parsed = parseFilterValue(encoded);
        this.filterMode = parsed.mode;
        this.selected = parsed.selected;
        if (this.$el && this.$el.length && this.$('.media-categories-filter-toggle').length) {
          this.syncPanelState();
          this.updateToggleLabel();
        }
      },

      termRowsHtml: function () {
        return (settings.terms || [])
          .map(function (term) {
            var text = termPrefix(term.depth) + term.name + ' (' + term.count + ')';
            return (
              '<label class="media-categories-filter-row">' +
              '<input type="checkbox" class="media-categories-filter-term" value="' +
              term.id +
              '" /> ' +
              '<span>' +
              $('<div>').text(text).html() +
              '</span></label>'
            );
          })
          .join('');
      },

      render: function () {
        var panelId = 'media-categories-filter-panel-' + this.cid;
        var toggleId = this.toggleId;

        this.$el.html(
          '<button type="button" class="media-categories-filter-toggle" id="' +
            toggleId +
            '" aria-expanded="false" aria-controls="' +
            panelId +
            '" aria-haspopup="true">' +
            '</button>' +
            '<div class="media-categories-filter-panel hidden" id="' +
            panelId +
            '" role="dialog" aria-label="' +
            $('<div>').text(labels.filterBy || 'Filter by Media Category').html() +
            '">' +
            '<div class="media-categories-filter-mode" role="group" aria-label="' +
            $('<div>').text(labels.filterBy || 'Filter by Media Category').html() +
            '">' +
            '<button type="button" class="button media-categories-filter-mode-btn" data-mode="in">' +
            $('<div>').text(labels.include || 'In').html() +
            '</button>' +
            '<button type="button" class="button media-categories-filter-mode-btn" data-mode="not">' +
            $('<div>').text(labels.exclude || 'Not in').html() +
            '</button>' +
            '</div>' +
            '<button type="button" class="button-link media-categories-filter-clear">' +
            $('<div>').text(labels.all || 'All').html() +
            '</button>' +
            '<label class="media-categories-filter-row">' +
            '<input type="checkbox" class="media-categories-filter-term" value="' +
            settings.uncategorized +
            '" /> ' +
            '<span>' +
            $('<div>').text(labels.uncategorized || 'Uncategorized').html() +
            '</span></label>' +
            '<div class="media-categories-filter-terms">' +
            this.termRowsHtml() +
            '</div></div>'
        );

        this.syncPanelState();
        this.updateToggleLabel();
        return this;
      },

      syncPanelState: function () {
        var self = this;
        var selected = this.selected.map(String);
        this.$('.media-categories-filter-term').each(function () {
          this.checked = selected.indexOf(String(this.value)) !== -1;
        });
        this.$('.media-categories-filter-mode-btn').each(function () {
          var on = this.getAttribute('data-mode') === self.filterMode;
          this.setAttribute('aria-pressed', on ? 'true' : 'false');
          $(this).toggleClass('button-primary', on);
        });
      },

      updateToggleLabel: function () {
        var text = labels.all || 'All';
        var selected = this.selected;
        if (selected.length === 1 && selected[0] === settings.uncategorized) {
          text =
            this.filterMode === 'not'
              ? labels.categorized || 'Categorized'
              : labels.uncategorized || 'Uncategorized';
        } else if (selected.length === 1) {
          var term = (settings.terms || []).filter(function (item) {
            return item.id === selected[0];
          })[0];
          text = term ? term.name : String(selected[0]);
          if (this.filterMode === 'not') {
            text = (labels.exclude || 'Not in') + ' ' + text;
          }
        } else if (selected.length > 1) {
          var countLabel = (labels.selectedCount || '%d selected').replace(
            '%d',
            String(selected.length)
          );
          text =
            this.filterMode === 'not'
              ? (labels.exclude || 'Not in') + ' · ' + countLabel
              : countLabel;
        }
        this.$('.media-categories-filter-toggle').text(text);
      },

      collectSelected: function () {
        return this.$('.media-categories-filter-term:checked')
          .map(function () {
            return this.value === settings.uncategorized
              ? settings.uncategorized
              : parseInt(this.value, 10);
          })
          .get();
      },

      applyFilter: function () {
        var encoded = encodeFilterValue(this.filterMode, this.selected);
        this.updateToggleLabel();
        if (this.options.$input && this.options.$input.length) {
          this.options.$input.val(encoded);
        }
        if (!this.live || !this.model) {
          return;
        }
        var listArg = settings.filterArg;
        if (!encoded) {
          if (typeof this.model.unset === 'function') {
            this.model.unset(taxonomy);
            if (listArg && listArg !== taxonomy) {
              this.model.unset(listArg, { silent: true });
            }
          }
          return;
        }
        if (typeof this.model.set !== 'function') {
          return;
        }
        var props = {};
        props[taxonomy] = encoded;
        var currentType = this.model.get('type');
        if (currentType) {
          props.type = currentType;
        }
        this.model.set(props);
      },

      onToggle: function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (this.panelOpen) {
          this.closePanel();
        } else {
          this.openPanel();
        }
      },

      onMode: function (e) {
        e.preventDefault();
        this.filterMode = $(e.currentTarget).attr('data-mode') === 'not' ? 'not' : 'in';
        this.syncPanelState();
        if (this.selected.length) {
          this.applyFilter();
        } else {
          this.updateToggleLabel();
        }
      },

      onClear: function (e) {
        e.preventDefault();
        this.selected = [];
        this.syncPanelState();
        this.applyFilter();
      },

      onTermChange: function (e) {
        var $input = $(e.currentTarget);
        var value = $input.val();
        if ($input.prop('checked') && value === settings.uncategorized) {
          this.$('.media-categories-filter-term')
            .not($input)
            .prop('checked', false);
        } else if ($input.prop('checked')) {
          this.$('.media-categories-filter-term[value="' + settings.uncategorized + '"]').prop(
            'checked',
            false
          );
        }
        this.selected = this.collectSelected();
        this.applyFilter();
      },

      openPanel: function () {
        if (openFilterView && openFilterView !== this) {
          openFilterView.closePanel();
        }
        bindFilterDocumentEvents();
        this.panelOpen = true;
        openFilterView = this;
        this.$('.media-categories-filter-panel').removeClass('hidden');
        this.$('.media-categories-filter-toggle').attr('aria-expanded', 'true');
        this.positionPanel();
        $(window).on(
          'resize.mediaCategoriesFilter-' + this.cid,
          $.proxy(this.positionPanel, this)
        );
      },

      closePanel: function () {
        this.panelOpen = false;
        if (openFilterView === this) {
          openFilterView = null;
        }
        this.$('.media-categories-filter-panel').addClass('hidden');
        this.$('.media-categories-filter-toggle').attr('aria-expanded', 'false');
        $(window).off('resize.mediaCategoriesFilter-' + this.cid);
      },

      positionPanel: function () {
        var $panel = this.$('.media-categories-filter-panel');
        var toggle = this.$('.media-categories-filter-toggle').get(0);
        if (!$panel.length || !toggle) {
          return;
        }
        var rect = toggle.getBoundingClientRect();
        var gutter = 8;
        var width = $panel.outerWidth() || 240;
        var left = rect.left;
        if (left + width > window.innerWidth - gutter) {
          left = Math.max(gutter, window.innerWidth - width - gutter);
        }
        $panel.css({
          top: Math.round(rect.bottom + 4),
          left: Math.round(left),
        });
      },
    });

    var Button = wp.media.view.Button;

    AssignCategoriesButton = Button.extend({
      className: 'button media-button assign-categories-button hidden',
      defaults: {
        text: labels.bulkEdit || 'Edit categories',
        style: 'secondary',
        size: 'large',
        disabled: true,
      },

      initialize: function () {
        Button.prototype.initialize.apply(this, arguments);
        this.controller.on('selection:toggle', this.toggleDisabled, this);
        this.controller.on('select:activate', this.show, this);
        this.controller.on('select:deactivate', this.hide, this);
      },

      toggleDisabled: function () {
        var selection = this.controller.state().get('selection');
        this.model.set('disabled', !selection || !selection.length);
      },

      show: function () {
        this.$el.removeClass('hidden');
        this.toggleDisabled();
      },

      hide: function () {
        this.$el.addClass('hidden');
        closeBulkPanel();
      },

      click: function () {
        if (this.model.get('disabled')) {
          return;
        }
        var selection = this.controller.state().get('selection');
        var ids = selection.map(function (model) {
          return model.id;
        });
        openBulkPanel(ids, this.$el, this.controller);
      },

      render: function () {
        Button.prototype.render.apply(this, arguments);
        // Grid bulk-select only — picker modals (ACF Image, Add Media) are
        // already "select" frames and use the attachment sidebar instead.
        if (
          this.controller.isModeActive('grid') &&
          this.controller.isModeActive('select')
        ) {
          this.$el.removeClass('hidden');
        } else {
          this.$el.addClass('hidden');
        }
        this.toggleDisabled();
        return this;
      },
    });

    return true;
  }

  /**
   * Add the category dropdown to an AttachmentsBrowser toolbar.
   * Used from createToolbar and again when ACF activates its browse state
   * (ACF builds a fresh Select frame per Image/Gallery/File click).
   */
  function injectCategoryFilter(browser) {
    if (!ensureMediaViews() || !browser || !browser.toolbar || !browser.collection) {
      return;
    }
    if (browser.toolbar.get('MediaCategoryFilter')) {
      return;
    }

    var filterView = new MediaCategoryFilter({
      controller: browser.controller,
      model: browser.collection.props,
      live: true,
      priority: -74,
    }).render();

    if (wp.media.view.Label) {
      browser.toolbar.set(
        'MediaCategoryFilterLabel',
        new wp.media.view.Label({
          value: labels.filterBy || 'Filter by Media Category',
          attributes: {
            'for': filterView.toggleId,
          },
          priority: -74,
        }).render()
      );
    }

    browser.toolbar.set('MediaCategoryFilter', filterView);
    categoryFilterViews.push(filterView);

    if (
      settings.assignCap &&
      AssignCategoriesButton &&
      !browser.toolbar.get('AssignCategoriesButton') &&
      browser.controller &&
      typeof browser.controller.isModeActive === 'function' &&
      browser.controller.isModeActive('grid')
    ) {
      browser.toolbar.set(
        'AssignCategoriesButton',
        new AssignCategoriesButton({
          controller: browser.controller,
          priority: -60,
        }).render()
      );
    }
  }

  var boundLibraryToolbar = false;

  function bindLibraryToolbar() {
    if (boundLibraryToolbar) {
      if (window.cloakwpMediaLibrary && typeof cloakwpMediaLibrary.patch === 'function') {
        cloakwpMediaLibrary.patch();
      }
      return true;
    }
    if (window.cloakwpMediaLibrary && typeof cloakwpMediaLibrary.onToolbar === 'function') {
      cloakwpMediaLibrary.onToolbar(injectCategoryFilter);
      boundLibraryToolbar = true;
      bindClearFilters();
      if (typeof cloakwpMediaLibrary.patch === 'function') {
        cloakwpMediaLibrary.patch();
      }
      return true;
    }
    return false;
  }

  /* ------------------------------------------------------------------ */
  /* Attachment sidebar: REST replace + Add New category                */
  /* ------------------------------------------------------------------ */

  function escapeHtml(text) {
    return $('<div>').text(text == null ? '' : String(text)).html();
  }

  function termDepthFromSettings(term) {
    var depth = 0;
    var parent = parseInt(term.parent, 10) || 0;
    var byId = {};
    (settings.terms || []).forEach(function (item) {
      byId[parseInt(item.id, 10)] = item;
    });
    var guard = 0;
    while (parent > 0 && byId[parent] && guard++ < 50) {
      depth += 1;
      parent = parseInt(byId[parent].parent, 10) || 0;
    }
    return depth;
  }

  function registerCreatedTerm(term) {
    settings.terms = settings.terms || [];
    var id = parseInt(term.id, 10);
    var existing = settings.terms.filter(function (item) {
      return parseInt(item.id, 10) === id;
    })[0];
    if (existing) {
      return existing;
    }
    var row = {
      id: id,
      name: decodeHtmlEntities(term.name),
      slug: term.slug,
      parent: parseInt(term.parent, 10) || 0,
      count: parseInt(term.count, 10) || 0,
    };
    row.depth = termDepthFromSettings(row);
    settings.terms.push(row);
    return row;
  }

  function appendTermToChecklist($checklist, term, checked) {
    if (!$checklist || !$checklist.length) {
      return;
    }
    if ($checklist.find('input[type="checkbox"][value="' + term.id + '"]').length) {
      return;
    }
    var $li = $(
      '<li id="' +
        taxonomy +
        '-' +
        term.id +
        '">' +
        '<label class="selectit">' +
        '<input value="' +
        term.id +
        '" type="checkbox" name="tax_input[' +
        taxonomy +
        '][]" data-slug="' +
        escapeHtml(term.slug || '') +
        '"' +
        (checked ? ' checked="checked"' : '') +
        ' /> ' +
        escapeHtml(term.name) +
        '</label></li>'
    );
    var parentId = parseInt(term.parent, 10) || 0;
    var $parentLi =
      parentId > 0
        ? $checklist
            .find('input[type="checkbox"][value="' + parentId + '"]')
            .closest('li')
            .first()
        : $();
    if ($parentLi.length) {
      var $kids = $parentLi.children('ul.children');
      if (!$kids.length) {
        $kids = $('<ul class="children"></ul>');
        $parentLi.append($kids);
      }
      $kids.append($li);
    } else {
      $checklist.append($li);
    }
  }

  function appendTermToParentSelect($select, term) {
    if (!$select || !$select.length) {
      return;
    }
    if ($select.find('option[value="' + term.id + '"]').length) {
      return;
    }
    var depth = term.depth != null ? term.depth : termDepthFromSettings(term);
    var pad = new Array(depth * 3 + 1).join('\u00a0');
    var $option = $('<option></option>').val(String(term.id)).text(pad + term.name);
    var parentId = parseInt(term.parent, 10) || 0;
    if (parentId > 0) {
      var $parentOpt = $select.find('option[value="' + parentId + '"]');
      if ($parentOpt.length) {
        $parentOpt.after($option);
        return;
      }
    }
    $select.append($option);
  }

  function syncSidebarField($field) {
    if (!$field || !$field.length) {
      return;
    }
    var $checklist = $field.find('.media-categories-checklist');
    var $parent = $field.find('.media-categories-new-parent');
    (settings.terms || []).forEach(function (term) {
      appendTermToChecklist($checklist, term, false);
      appendTermToParentSelect($parent, term);
    });
  }

  function addTermToFilterPanels(term) {
    $('.media-categories-filter-terms').each(function () {
      var $box = $(this);
      if ($box.find('.media-categories-filter-term[value="' + term.id + '"]').length) {
        return;
      }
      var text = termPrefix(term.depth) + term.name + ' (' + (term.count || 0) + ')';
      $box.append(
        '<label class="media-categories-filter-row">' +
          '<input type="checkbox" class="media-categories-filter-term" value="' +
          term.id +
          '" /> <span>' +
          escapeHtml(text) +
          '</span></label>'
      );
    });
  }

  function addTermToBulkPanel(term) {
    if (!$bulkPanel) {
      return;
    }
    var $box = $bulkPanel.find('.media-categories-bulk-terms');
    if ($box.find('input[value="' + term.id + '"]').length) {
      return;
    }
    $box.append(
      '<label class="media-categories-bulk-term">' +
        '<input type="checkbox" value="' +
        term.id +
        '" /> ' +
        escapeHtml(termPrefix(term.depth) + term.name) +
        '</label>'
    );
  }

  function rememberCreatedTerm(term, $currentField) {
    var row = registerCreatedTerm(term);
    if ($currentField && $currentField.length) {
      appendTermToChecklist($currentField.find('.media-categories-checklist'), row, true);
      appendTermToParentSelect($currentField.find('.media-categories-new-parent'), row);
    }
    $('.media-categories-attachment-field').each(function () {
      if ($currentField && this === $currentField.get(0)) {
        return;
      }
      syncSidebarField($(this));
    });
    addTermToFilterPanels(row);
    addTermToBulkPanel(row);
    return row;
  }

  function collectCheckedTermIds($field) {
    return $field
      .find('.media-categories-checklist input[type="checkbox"]:checked')
      .map(function () {
        return parseInt(this.value, 10);
      })
      .get()
      .filter(function (id) {
        return id > 0;
      });
  }

  function refreshAttachmentModel(attachmentId) {
    if (!window.wp || !wp.media || !wp.media.model || !wp.media.model.Attachment) {
      return;
    }
    var model = wp.media.model.Attachment.get(attachmentId);
    if (model && typeof model.fetch === 'function') {
      model.fetch();
    }
  }

  function saveAttachmentCategories(attachmentId, $field, onDone) {
    if (!attachmentId || !settings.restUrl) {
      if (onDone) {
        onDone();
      }
      return;
    }

    fetch(settings.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': settings.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        attachment_ids: [attachmentId],
        term_ids: collectCheckedTermIds($field),
        append: false,
        replace: true,
      }),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          window.alert(
            (result.data && result.data.message) ||
              labels.bulkError ||
              'Could not update categories.'
          );
          return;
        }
        refreshAttachmentModel(attachmentId);
      })
      .catch(function () {
        window.alert(labels.bulkError || 'Could not update categories.');
      })
      .finally(function () {
        if (onDone) {
          onDone();
        }
      });
  }

  function patchTwoColumnSidebarSync() {
    var Details =
      window.wp && wp.media && wp.media.view && wp.media.view.Attachment
        ? wp.media.view.Attachment.Details
        : null;
    var TwoColumn = Details && Details.TwoColumn ? Details.TwoColumn.prototype : null;
    if (!TwoColumn || TwoColumn._mediaCategoriesPatched) {
      return;
    }
    var originalTwoColumnRender = TwoColumn.render;
    TwoColumn.render = function () {
      var result = originalTwoColumnRender.apply(this, arguments);
      syncSidebarField(this.$('.media-categories-attachment-field'));
      return result;
    };
    TwoColumn._mediaCategoriesPatched = true;
  }

  function patchAttachmentCompatSave() {
    if (!window.wp || !wp.media || !wp.media.view || !wp.media.view.AttachmentCompat) {
      return false;
    }
    if (!wp.media.view.AttachmentCompat.prototype._mediaCategoriesPatched) {
      var originalSave = wp.media.view.AttachmentCompat.prototype.save;
      wp.media.view.AttachmentCompat.prototype.save = function (event) {
        if (
          event &&
          event.target &&
          event.target.closest &&
          event.target.closest('.media-categories-attachment-field')
        ) {
          // Let the document REST handler persist IDs. Do not POST to
          // save-attachment-compat (core would treat taxonomy values as slugs).
          event.preventDefault();
          return;
        }
        return originalSave.apply(this, arguments);
      };

      // query-attachments caches compat.item per model. Next/prev re-renders
      // that snapshot, which would omit terms created in this session.
      var originalRender = wp.media.view.AttachmentCompat.prototype.render;
      wp.media.view.AttachmentCompat.prototype.render = function () {
        var result = originalRender.apply(this, arguments);
        syncSidebarField(this.$('.media-categories-attachment-field'));
        return result;
      };

      wp.media.view.AttachmentCompat.prototype._mediaCategoriesPatched = true;
    }

    patchTwoColumnSidebarSync();
    return true;
  }

  function setAddFormVisible($wrap, visible) {
    var $btn = $wrap.find('.media-categories-add-toggle');
    var $form = $wrap.find('.media-categories-add-form');
    $btn.attr('aria-expanded', visible ? 'true' : 'false');
    $form.toggleClass('hidden', !visible);
    $btn.text(
      visible
        ? $btn.attr('data-label-cancel') || labels.cancel || 'Cancel'
        : $btn.attr('data-label-add') || labels.addNew || 'Add New Media Category'
    );
  }

  function initAddNewHandlers() {
    $(document).on(
      'change',
      '.media-categories-checklist input[type="checkbox"]',
      function () {
        var $field = $(this).closest('.media-categories-attachment-field');
        var attachmentId = parseInt($field.attr('data-attachment-id'), 10);
        var frame =
          (window.wp && wp.media && (wp.media.frame || (wp.media.frames && wp.media.frames.edit))) ||
          null;
        if (frame && typeof frame.trigger === 'function') {
          frame.trigger('attachment:compat:waiting', ['waiting']);
        }
        saveAttachmentCategories(attachmentId, $field, function () {
          if (frame && typeof frame.trigger === 'function') {
            frame.trigger('attachment:compat:ready', ['ready']);
          }
        });
      }
    );

    $(document).on('click', '.media-categories-add-toggle', function (e) {
      e.preventDefault();
      var $wrap = $(this).closest('.media-categories-add-new');
      setAddFormVisible($wrap, $(this).attr('aria-expanded') !== 'true');
    });

    $(document).on('click', '.edit-media-header .left, .edit-media-header .right', function () {
      window.setTimeout(function () {
        $('.media-categories-attachment-field').each(function () {
          syncSidebarField($(this));
        });
      }, 0);
    });

    $(document).on('click', '.media-categories-add-submit', function (e) {
      e.preventDefault();
      if (!settings.manageCap || !window.wp || !wp.apiFetch) {
        return;
      }

      var $wrap = $(this).closest('.media-categories-add-new');
      var $field = $wrap.closest('.media-categories-attachment-field');
      var $name = $wrap.find('.media-categories-new-name');
      var $parent = $wrap.find('.media-categories-new-parent');
      var name = $.trim($name.val() || '');
      if (!name) {
        $name.trigger('focus');
        return;
      }

      var data = { name: name };
      var parent = parseInt($parent.val(), 10);
      if (parent > 0) {
        data.parent = parent;
      }

      var $button = $(this);
      $button.prop('disabled', true);

      wp
        .apiFetch({
          path: '/wp/v2/' + settings.restBase,
          method: 'POST',
          data: data,
        })
        .then(function (term) {
          rememberCreatedTerm(term, $field);
          $name.val('');
          setAddFormVisible($wrap, false);

          var attachmentId = parseInt($field.attr('data-attachment-id'), 10);
          saveAttachmentCategories(attachmentId, $field);
        })
        .catch(function () {
          window.alert(labels.bulkError || 'Could not create category.');
        })
        .finally(function () {
          $button.prop('disabled', false);
        });
    });
  }

  function initListBulkFromQuery() {
    var ids = window.mediaCategoriesBulkIds || null;
    if (!ids || !ids.length) {
      var params = new URLSearchParams(window.location.search);
      if (params.get('media_categories_bulk') === '1') {
        ids = (params.get('media_ids') || '')
          .split(',')
          .map(function (id) {
            return parseInt(id, 10);
          })
          .filter(Boolean);
      }
    }
    if (!ids || !ids.length) {
      return;
    }
    openBulkPanel(ids, $('#posts-filter .bulkactions').first());
  }

  function enhanceListFilter() {
    var $select = $('#media-categories-filter');
    if (!$select.length || $select.data('mediaCategoriesEnhanced')) {
      return;
    }
    if (!window.Backbone || !Backbone.Model || !ensureMediaViews() || !MediaCategoryFilter) {
      return;
    }
    $select.data('mediaCategoriesEnhanced', true);

    var encoded = $select.attr('data-encoded') || $select.val() || '';
    var parsed = parseFilterValue(encoded);
    if (
      $('#media-categories-filter-not').is(':checked') &&
      parsed.mode === 'in' &&
      parsed.selected.length
    ) {
      parsed.mode = 'not';
    }
    encoded = encodeFilterValue(parsed.mode, parsed.selected);

    var $hidden = $('<input type="hidden" />')
      .attr({
        name: $select.attr('name'),
        id: 'media-categories-filter-value',
      })
      .val(encoded);
    $select.removeAttr('name');

    var model = new Backbone.Model();
    model.set(taxonomy, encoded);

    var view = new MediaCategoryFilter({
      model: model,
      live: false,
      $input: $hidden,
      encoded: encoded,
      toggleId: 'media-categories-filter-toggle',
    });
    view.render();

    $select.hide().after($hidden).after(view.$el);
    categoryFilterViews.push(view);
    bindClearFilters();
    $('.media-categories-filter-not-wrap').hide();
    $('label[for="media-categories-filter"]').attr('for', 'media-categories-filter-toggle');

    $select.closest('form').on('submit.mediaCategoriesFilter', function () {
      $hidden.val(encodeFilterValue(view.filterMode, view.selected));
      $('#media-categories-filter-not').prop('checked', false);
    });
  }

  // Register with the shared LibraryFilter toolbar as soon as it exists.
  if (!bindLibraryToolbar()) {
    $(function () {
      bindLibraryToolbar();
    });
  }
  if (!patchAttachmentCompatSave()) {
    $(function () {
      patchAttachmentCompatSave();
    });
  }

  $(function () {
    initAddNewHandlers();
    initListBulkFromQuery();
    // One more attempt after other footer scripts (media-grid, acf-input) have run.
    bindLibraryToolbar();
    bindClearFilters();
    enhanceListFilter();
    patchAttachmentCompatSave();
  });
})(window, jQuery);
