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
          $('<div>').text(term.name).html() +
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
    if (!window.wp || !wp.media || !wp.media.view || !wp.media.view.AttachmentFilters) {
      return false;
    }

    MediaCategoryFilter = wp.media.view.AttachmentFilters.extend({
      id: 'media-categories-attachment-filter',
      className: 'attachment-filters media-categories-filter',

      createFilters: function () {
        var filters = {};
        var terms = settings.terms || [];

        filters.all = {
          text: labels.all || 'All',
          priority: 10,
          props: {},
        };
        filters.all.props[taxonomy] = '';

        filters.uncategorized = {
          text: labels.uncategorized || 'Uncategorized',
          priority: 15,
          props: {},
        };
        filters.uncategorized.props[taxonomy] = settings.uncategorized;

        terms.forEach(function (term) {
          var key = 'term_' + term.id;
          filters[key] = {
            text: term.name + ' (' + term.count + ')',
            priority: 20 + term.id,
            props: {},
          };
          filters[key].props[taxonomy] = term.id;
        });

        this.filters = filters;
      },

      /**
       * Keep mime-type restrictions from the current query (ACF Image/File
       * fields set library.type = image). Core set() only applies our props.
       */
      change: function () {
        var filter = this.filters[this.el.value];
        if (!filter) {
          return;
        }
        var props = $.extend({}, filter.props);
        var currentType = this.model && this.model.get ? this.model.get('type') : null;
        if (currentType && props.type === undefined) {
          props.type = currentType;
        }
        this.model.set(props);
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

    if (wp.media.view.Label) {
      browser.toolbar.set(
        'MediaCategoryFilterLabel',
        new wp.media.view.Label({
          value: labels.filterBy || 'Filter by Media Category',
          attributes: {
            'for': 'media-categories-attachment-filter',
          },
          priority: -74,
        }).render()
      );
    }

    browser.toolbar.set(
      'MediaCategoryFilter',
      new MediaCategoryFilter({
        controller: browser.controller,
        model: browser.collection.props,
        priority: -74,
      }).render()
    );

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
    patchAttachmentCompatSave();
  });
})(window, jQuery);
