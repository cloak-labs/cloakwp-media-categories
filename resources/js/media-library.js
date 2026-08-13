/**
 * Media Categories — admin media library enhancements.
 *
 * - Grid / modal taxonomy filter
 * - Bulk-select "Edit categories" button + panel
 * - Attachment sidebar "Add New" term
 * - List-view bulk assign panel (via query args)
 */
(function (window, $) {
  'use strict';

  var settings = window.mediaCategoriesAdmin || null;
  if (!settings || !window.wp || !wp.media) {
    return;
  }

  var taxonomy = settings.taxonomy;
  var labels = settings.labels || {};

  /* ------------------------------------------------------------------ */
  /* Grid / modal filter                                                */
  /* ------------------------------------------------------------------ */

  var MediaCategoryFilter = wp.media.view.AttachmentFilters.extend({
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
  });

  var AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
  wp.media.view.AttachmentsBrowser = AttachmentsBrowser.extend({
    createToolbar: function () {
      AttachmentsBrowser.prototype.createToolbar.call(this);

      this.toolbar.set(
        'MediaCategoryFilter',
        new MediaCategoryFilter({
          controller: this.controller,
          model: this.collection.props,
          priority: -75,
        }).render()
      );

      // Bulk-select assign (hidden until select:activate — only exists on the grid library).
      if (settings.assignCap) {
        this.toolbar.set(
          'AssignCategoriesButton',
          new AssignCategoriesButton({
            controller: this.controller,
            priority: -60,
          }).render()
        );
      }
    },
  });

  /* ------------------------------------------------------------------ */
  /* Grid bulk-select assign button                                     */
  /* ------------------------------------------------------------------ */

  var Button = wp.media.view.Button;

  var AssignCategoriesButton = Button.extend({
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
      if (this.controller.isModeActive('select')) {
        this.$el.removeClass('hidden');
      } else {
        this.$el.addClass('hidden');
      }
      this.toggleDisabled();
      return this;
    },
  });

  // Keep button visible when SelectModeToggle hides non-.media-button children.
  // Core already looks for .delete-selected-button; we use .media-button which stays.

  /* ------------------------------------------------------------------ */
  /* Shared bulk panel                                                  */
  /* ------------------------------------------------------------------ */

  var $bulkPanel = null;

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

  var currentBulkIds = [];

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

    if ($anchor && $anchor.length) {
      var offset = $anchor.offset();
      $panel.css({
        top: offset.top + $anchor.outerHeight() + 8,
        left: Math.max(16, offset.left - 40),
      });
    }
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

    var headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
    };

    fetch(settings.restUrl, {
      method: 'POST',
      headers: headers,
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
          (labels.bulkSuccess || 'Categories updated.') +
            ' (' +
            count +
            ')'
        );

        // Refresh media grid if present.
        if (wp.media.frame && wp.media.frame.content && wp.media.frame.content.get()) {
          var library = wp.media.frame.state().get('library');
          if (library && library.props) {
            library.props.set({ ignore: +new Date() });
          }
        }

        setTimeout(closeBulkPanel, 800);

        // List-view: clean URL + optional notice.
        if (window.location.search.indexOf('media_categories_bulk') !== -1) {
          var url = new URL(window.location.href);
          url.searchParams.delete('media_categories_bulk');
          url.searchParams.delete('media_ids');
          url.searchParams.set('media_categories_updated', String(count));
          window.location.href = url.toString();
        }
      })
      .catch(function () {
        $status.text(labels.bulkError || 'Could not update categories.');
      });
  }

  /* ------------------------------------------------------------------ */
  /* List-view bulk (query-arg driven)                                  */
  /* ------------------------------------------------------------------ */

  function initListBulkFromQuery() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('media_categories_bulk') !== '1') {
      return;
    }
    var ids = (params.get('media_ids') || '')
      .split(',')
      .map(function (id) {
        return parseInt(id, 10);
      })
      .filter(Boolean);
    if (!ids.length) {
      return;
    }
    openBulkPanel(ids, $('#posts-filter .bulkactions').first());
  }

  /* ------------------------------------------------------------------ */
  /* Attachment sidebar: Add New category                               */
  /* ------------------------------------------------------------------ */

  function initAddNewHandlers() {
    $(document).on('click', '.media-categories-add-toggle', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $form = $btn.siblings('.media-categories-add-form');
      var expanded = $btn.attr('aria-expanded') === 'true';
      $btn.attr('aria-expanded', expanded ? 'false' : 'true');
      $form.toggleClass('hidden', expanded);
    });

    $(document).on('click', '.media-categories-add-submit', function (e) {
      e.preventDefault();
      if (!settings.manageCap || !wp.apiFetch) {
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
              '" checked="checked" /> ' +
              $('<div>').text(term.name).html() +
              '</label></li>'
          );
          checklist.append($li);

          // Keep bulk panel term list in sync.
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
          $wrap.find('.media-categories-add-form').addClass('hidden');
          $wrap.find('.media-categories-add-toggle').attr('aria-expanded', 'false');
        })
        .catch(function () {
          window.alert(labels.bulkError || 'Could not create category.');
        })
        .finally(function () {
          $button.prop('disabled', false);
        });
    });
  }

  $(function () {
    initAddNewHandlers();
    initListBulkFromQuery();
  });
})(window, jQuery);
