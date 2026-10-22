<?php

declare(strict_types=1);

namespace Next\Support;

/**
 * Official Swup.js Page Transition Engine for NEXT.php.
 *
 * Provides official Swup v4 with full Fade-In & Fade-Out animations,
 * multi-container swapping (#swup, #nav), and active navigation updates.
 */
class ClientScript
{
    /**
     * @param array{
     *   containers?: list<string>,
     *   animation?: string,
     *   cache?: bool,
     *   preload?: bool
     * } $options
     */
    public static function render(array $options = []): string
    {
        $containers = $options['containers'] ?? ['#swup', '#nav', 'main'];
        $containersJson = json_encode($containers);
        $cacheEnabled = json_encode($options['cache'] ?? true);

        $swupLibFile = __DIR__ . '/swup.umd.js';
        $swupLib = file_exists($swupLibFile) ? file_get_contents($swupLibFile) : '';

        return <<<HTML
<style id="swup-transitions">
/* ===================================================
 * Swup.js Official Fade-In & Fade-Out Transitions
 * =================================================== */
.transition-fade,
#swup {
  opacity: 1;
  transform: translateY(0);
  transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  will-change: opacity, transform;
}

/* FADE OUT STATE: Applied during navigation out */
html.is-animating .transition-fade,
html.is-animating #swup {
  opacity: 0;
  transform: translateY(8px);
}

html.is-leaving .transition-fade,
html.is-leaving #swup {
  opacity: 0;
  transform: translateY(-8px);
}

/* Swup Top Progress Indicator */
.next-loading-bar {
  position: fixed;
  top: 0;
  left: 0;
  height: 3px;
  background: linear-gradient(90deg, #0070f3, #7928ca, #ff0080);
  width: 0%;
  transition: width 0.25s ease, opacity 0.2s ease;
  z-index: 999999;
  pointer-events: none;
}

html.is-animating .next-loading-bar {
  width: 75%;
  opacity: 1;
}

/* Active Link Styles */
nav a.is-active,
nav a[aria-current="page"],
.nav-link.is-active,
.nav-link[aria-current="page"] {
  color: #0070f3 !important;
  font-weight: 600 !important;
  background: #e6f0ff !important;
}
</style>
<div class="next-loading-bar" id="swup-progress"></div>
<script>
{$swupLib}
</script>
<script>
(function() {
  function updateActiveNavLinks(targetUrl) {
    const currentPath = (targetUrl ? new URL(targetUrl, window.location.origin).pathname : window.location.pathname).replace(/\/$/, '') || '/';
    document.querySelectorAll('nav a, a[data-next-link], .nav-link').forEach(link => {
      const href = (link.getAttribute('href') || '').replace(/\/$/, '') || '/';
      const isMatch = (href === '/' && currentPath === '/') || (href !== '/' && (currentPath === href || currentPath.startsWith(href + '/')));
      if (isMatch) {
        link.classList.add('is-active');
        link.setAttribute('aria-current', 'page');
      } else {
        link.classList.remove('is-active');
        link.removeAttribute('aria-current');
      }
    });
  }

  // Initialize official Swup.js engine
  if (typeof Swup !== 'undefined' && !window.__next_swup_initialized) {
    window.__next_swup_initialized = true;

    const candidateContainers = {$containersJson};
    let targetContainers = ['#swup'];
    if (!document.querySelector('#swup')) {
      const found = candidateContainers.find(sel => document.querySelector(sel) !== null);
      if (found) {
        targetContainers = [found];
      }
    }

    const swup = new Swup({
      containers: targetContainers,
      animationSelector: '[class*="transition-"], #swup, main',
      animateHistoryBrowsing: true,
      cache: {$cacheEnabled}
    });

    window.swup = swup;
    window.__next_swup = swup;

    // Swup Hooks: Fade Out, Progress Bar & Active Link Updates
    swup.hooks.on('visit:start', (visit) => {
      const targetUrl = visit.to.url;
      updateActiveNavLinks(targetUrl);
      const bar = document.getElementById('swup-progress');
      if (bar) {
        bar.style.opacity = '1';
        bar.style.width = '70%';
      }
    });

    swup.hooks.on('content:replace', (visit) => {
      updateActiveNavLinks(visit.to.url);
      window.scrollTo({ top: 0, behavior: 'instant' });
      const bar = document.getElementById('swup-progress');
      if (bar) bar.style.width = '100%';
    });

    swup.hooks.on('visit:end', (visit) => {
      const bar = document.getElementById('swup-progress');
      if (bar) {
        bar.style.opacity = '0';
        setTimeout(() => { bar.style.width = '0%'; }, 200);
      }
      updateActiveNavLinks(visit.to.url);
      window.dispatchEvent(new CustomEvent('next:route-change', { detail: { url: visit.to.url } }));
    });

    // Next.js router compatibility alias
    window.next = {
      push: (url) => swup.navigate(url),
      refresh: () => swup.navigate(window.location.pathname + window.location.search)
    };
  }

  // Run on initial page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => updateActiveNavLinks());
  } else {
    updateActiveNavLinks();
  }
})();
</script>
HTML;
    }
}
