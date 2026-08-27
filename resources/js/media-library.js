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
  var patchedBrowser = false;
  var openFilterView = null;
  var filterDocBound = false;

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

  function ensureBulkPanel() {
    if ($bulkPanel) {
      return $bulkPanel;
    }

    var termOptions = (settings.terms || [])
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

    $bulkPanel = $(
      '<div class="media-categories-bulk-panel hidden" role="dialog" aria-label="' +
        (labels.bulkEdit || 'Edit categories') +
        '">' +
        '<div class="media-categories-bulk-panel__inner">' +
        '<p class="media-categories-bulk-panel__title"></p>' +
        '<div class="media-categories-bulk-terms">' +
        termOptions +
        '</div>' +
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

    $bulkPanel.on('click', '.media-categories-bulk-cancel', closeBulkPanel);
    $bulkPanel.on('click', '.media-categories-bulk-add', function () {
      submitBulk(true);
    });
    $bulkPanel.on('click', '.media-categories-bulk-remove', function () {
      submitBulk(false);
    });

    return $bulkPanel;
  }

  function openBulkPanel(ids, $anchor) {
    var $panel = ensureBulkPanel();
    currentBulkIds = ids || [];
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
    if ($bulkPanel) {
      $bulkPanel.addClass('hidden');
    }
    currentBulkIds = [];
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
        $status.text(
          (labels.bulkSuccess || 'Categories updated.') + ' (' + count + ')'
        );

        // Refresh media grid if present.
        if (window.wp && wp.media && wp.media.frame) {
          var library = wp.media.frame.state().get('library');
          if (library && library.props) {
            library.props.set({ ignore: +new Date() });
          }
        }

        setTimeout(function () {
          closeBulkPanel();
          if (
            window.location.search.indexOf('media_categories_bulk') !== -1 ||
            window.mediaCategoriesBulkIds
          ) {
            var url = new URL(window.location.href);
            url.searchParams.delete('media_categories_bulk');
            url.searchParams.delete('media_ids');
            url.searchParams.set('media_categories_updated', String(count));
            window.location.href = url.toString();
          }
        }, 600);
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
  var boundAcfPopup = false;

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
        if (!this.live || !this.model || typeof this.model.set !== 'function') {
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
        openBulkPanel(ids, this.$el);
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

  function bindMediaFrame(frame) {
    if (!frame || frame._mediaCategoriesBound || typeof frame.on !== 'function') {
      return;
    }
    frame._mediaCategoriesBound = true;
    frame.on('content:activate:browse', function () {
      var browser = null;
      try {
        browser = frame.content.get();
      } catch (e) {
        return;
      }
      injectCategoryFilter(browser);
    });
  }

  function bindAcfMediaPopups() {
    if (boundAcfPopup) {
      return true;
    }
    if (!window.acf || typeof acf.addAction !== 'function') {
      return false;
    }
    acf.addAction('new_media_popup', function (popup) {
      patchMediaBrowser();
      if (popup && popup.frame) {
        bindMediaFrame(popup.frame);
      }
    });
    boundAcfPopup = true;
    return true;
  }

  function patchMediaBrowser() {
    if (!ensureMediaViews()) {
      return false;
    }
    if (!wp.media.view.AttachmentsBrowser) {
      return false;
    }

    bindAcfMediaPopups();

    if (patchedBrowser) {
      return true;
    }

    // Mutate the prototype in place so any cached constructor reference still picks this up.
    var proto = wp.media.view.AttachmentsBrowser.prototype;
    var originalCreateToolbar = proto.createToolbar;
    proto.createToolbar = function () {
      originalCreateToolbar.apply(this, arguments);
      injectCategoryFilter(this);
    };

    patchedBrowser = true;
    return true;
  }

  /* ------------------------------------------------------------------ */
  /* Attachment sidebar: REST replace + Add New category                */
  /* ------------------------------------------------------------------ */

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

  function patchAttachmentCompatSave() {
    if (!window.wp || !wp.media || !wp.media.view || !wp.media.view.AttachmentCompat) {
      return false;
    }
    if (wp.media.view.AttachmentCompat.prototype._mediaCategoriesPatched) {
      return true;
    }

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
    wp.media.view.AttachmentCompat.prototype._mediaCategoriesPatched = true;
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
          var checklist = $field.find('.media-categories-checklist');
          var inputName = 'tax_input[' + taxonomy + '][]';
          var $li = $(
            '<li id="' +
              taxonomy +
              '-' +
              term.id +
              '">' +
              '<label class="selectit">' +
              '<input value="' +
              term.id +
              '" type="checkbox" name="' +
              inputName +
              '" data-slug="' +
              $('<div>').text(term.slug || '').html() +
              '" checked="checked" /> ' +
              $('<div>').text(term.name).html() +
              '</label></li>'
          );
          var parentId = parseInt(term.parent, 10) || parent || 0;
          var $parentLi =
            parentId > 0
              ? checklist
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
            checklist.append($li);
          }

          settings.terms = settings.terms || [];
          settings.terms.push({
            id: term.id,
            name: term.name,
            slug: term.slug,
            parent: term.parent || 0,
            count: 0,
          });
          if ($bulkPanel) {
            $bulkPanel
              .find('.media-categories-bulk-terms')
              .append(
                '<label class="media-categories-bulk-term">' +
                  '<input type="checkbox" value="' +
                  term.id +
                  '" /> ' +
                  $('<div>').text(term.name).html() +
                  '</label>'
              );
          }

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
    $('.media-categories-filter-not-wrap').hide();
    $('label[for="media-categories-filter"]').attr('for', 'media-categories-filter-toggle');

    $select.closest('form').on('submit.mediaCategoriesFilter', function () {
      $hidden.val(encodeFilterValue(view.filterMode, view.selected));
      $('#media-categories-filter-not').prop('checked', false);
    });
  }

  // Patch as soon as media-views is available; retry on ready if needed.
  if (!patchMediaBrowser()) {
    $(function () {
      patchMediaBrowser();
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
    patchMediaBrowser();
    bindAcfMediaPopups();
    enhanceListFilter();
    patchAttachmentCompatSave();
  });
})(window, jQuery);
