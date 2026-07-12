(function(window, document, $) {
  'use strict';

  var STORAGE_KEY = 'adminlte-theme-mode';
  var DARK_MODE = 'dark';
  var LIGHT_MODE = 'light';
  var currentTheme = null;
  var mediaQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function getStoredTheme() {
    try {
      return window.localStorage.getItem(STORAGE_KEY);
    } catch (error) {
      return null;
    }
  }

  function setStoredTheme(mode) {
    try {
      window.localStorage.setItem(STORAGE_KEY, mode);
    } catch (error) {
      return null;
    }

    return mode;
  }

  function getSystemTheme() {
    return mediaQuery && mediaQuery.matches ? DARK_MODE : LIGHT_MODE;
  }

  function getPreferredTheme() {
    var storedTheme = getStoredTheme();
    return storedTheme === DARK_MODE || storedTheme === LIGHT_MODE ? storedTheme : getSystemTheme();
  }

  function setToggleState(mode) {
    var isDark = mode === DARK_MODE;

    document.querySelectorAll('[data-theme-toggle]').forEach(function(toggleButton) {
      toggleButton.setAttribute('data-theme-mode', mode);
      toggleButton.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      toggleButton.setAttribute('aria-label', isDark ? 'Aktifkan light mode' : 'Aktifkan dark mode');
      toggleButton.setAttribute('title', isDark ? 'Pindah ke light mode' : 'Pindah ke dark mode');
    });
  }

  function setNavbarTheme(isDark) {
    document.querySelectorAll('.main-header').forEach(function(navbar) {
      navbar.classList.toggle('navbar-dark', isDark);
      navbar.classList.toggle('navbar-light', !isDark);
      navbar.classList.toggle('navbar-white', !isDark);
      navbar.classList.toggle('navbar-gray-dark', isDark);
    });
  }

  function setSidebarTheme(isDark) {
    document.querySelectorAll('.main-sidebar').forEach(function(sidebar) {
      sidebar.classList.toggle('sidebar-dark-primary', isDark);
      sidebar.classList.toggle('sidebar-light-primary', !isDark);
    });
  }

  function refreshPluginSurfaces() {
    if (!$) {
      return;
    }

    var mode = currentTheme || getPreferredTheme();

    $('.select2-container, .select2-dropdown').attr('data-theme-mode', mode);
    $('.dataTables_wrapper').attr('data-theme-mode', mode);
    $('.modal').attr('data-theme-mode', mode);
  }

  function applyTheme(mode, options) {
    var settings = options || {};
    var finalMode = mode === DARK_MODE ? DARK_MODE : LIGHT_MODE;
    var isDark = finalMode === DARK_MODE;

    currentTheme = finalMode;

    document.documentElement.setAttribute('data-theme-mode', finalMode);
    document.documentElement.classList.add('theme-preload');
    document.documentElement.classList.toggle('theme-preload-dark', isDark);
    document.documentElement.classList.toggle('theme-preload-light', !isDark);
    document.documentElement.classList.toggle('dark-mode', isDark);

    if (document.body) {
      document.body.classList.toggle('dark-mode', isDark);
    }

    setNavbarTheme(isDark);
    setSidebarTheme(isDark);
    setToggleState(finalMode);
    refreshPluginSurfaces();

    if (!settings.skipPersist) {
      setStoredTheme(finalMode);
    }

    document.dispatchEvent(new CustomEvent('theme:change', {
      detail: {
        mode: finalMode
      }
    }));
  }

  function toggleTheme() {
    applyTheme(currentTheme === DARK_MODE ? LIGHT_MODE : DARK_MODE);
  }

  function handleToggleClick(event) {
    var toggleButton = event.target.closest('[data-theme-toggle]');

    if (!toggleButton) {
      return;
    }

    event.preventDefault();
    toggleTheme();
  }

  function bindEvents() {
    document.addEventListener('click', handleToggleClick);

    if (mediaQuery) {
      var handleSchemeChange = function() {
        var storedTheme = getStoredTheme();

        if (storedTheme !== DARK_MODE && storedTheme !== LIGHT_MODE) {
          applyTheme(getSystemTheme(), {
            skipPersist: true
          });
        }
      };

      if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', handleSchemeChange);
      } else if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(handleSchemeChange);
      }
    }

    if ($) {
      $(document).on('shown.bs.modal ajaxComplete draw.dt init.dt select2:open', function() {
        refreshPluginSurfaces();
      });
    }
  }

  function init() {
    applyTheme(getPreferredTheme(), {
      skipPersist: true
    });
    window.requestAnimationFrame(function() {
      window.requestAnimationFrame(function() {
        document.documentElement.classList.remove('theme-preload');
      });
    });
    bindEvents();
  }

  window.AdminLTETheme = {
    apply: applyTheme,
    toggle: toggleTheme,
    refresh: refreshPluginSurfaces,
    getStoredTheme: getStoredTheme,
    getSystemTheme: getSystemTheme,
    getCurrentTheme: function() {
      return currentTheme || getPreferredTheme();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document, window.jQuery);
