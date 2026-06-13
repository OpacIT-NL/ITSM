</div>
<?php if ( function_exists( 'itsm_render_local_datetime_script' ) ) { itsm_render_local_datetime_script(); } ?>
<script>
(function () {
  const i18n = {
    dashboard: <?= json_encode(t('Dashboard')) ?>,
    openTabs: <?= json_encode(t('Open tabbladen')) ?>,
    closeTab: <?= json_encode(t('Tab sluiten')) ?>,
    refresh: <?= json_encode(t('Verversen')) ?>,
    someoneElseSaved: <?= json_encode(t('Iemand anders heeft deze kaart opgeslagen. Ververs voordat je opslaat; je concept blijft lokaal bewaard.')) ?>,
    saveBlocked: <?= json_encode(t('Opslaan is geblokkeerd omdat iemand anders deze kaart heeft opgeslagen. Je concept blijft bewaard; klik op Verversen.')) ?>,
    someoneElse: <?= json_encode(t('Iemand anders')) ?>,
    operatorSingular: <?= json_encode(t('behandelaar heeft')) ?>,
    operatorPlural: <?= json_encode(t('behandelaren hebben')) ?>,
    alsoOpen: <?= json_encode(t('deze kaart ook open:')) ?>
  };
  const maxTabs = 15;
  const storageKey = 'itsm_secure_tabs_v1';
  const activeTabKeyStorage = 'itsm_secure_active_tab_key_v1';
  const draftPrefix = 'itsm_secure_tab_draft_v1:';
  const scrollPrefix = 'itsm_secure_tab_scroll_v1:';
  const viewPrefix = 'itsm_secure_tab_view_v1:';
  const presenceTokenPrefix = 'itsm_secure_presence_token_v1:';
  const pendingPresenceSubmitPrefix = 'itsm_secure_pending_presence_submit_v1:';
  const closedDraftPrefix = 'itsm_secure_closed_draft_v1:';
  const tabbar = document.getElementById('secure_tabbar');
  const initialActiveTabKey = sessionStorage.getItem(activeTabKeyStorage);
  let existingTabIdentifiersBeforeBoot = new Set();

  if (!tabbar) {
    return;
  }

  function readTabs() {
    try {
      const parsed = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function writeTabs(tabs) {
    sessionStorage.setItem(storageKey, JSON.stringify(tabs));
  }

  function countClosableTabs(tabs) {
    return tabs.filter((tab) => !tab.pinned).length;
  }

  function dashboardTab() {
    return {
      key: '/secure/index.php',
      url: '/secure/index.php',
      title: i18n.dashboard,
      icon: 'home',
      pinned: true,
      lastActive: 0,
      createdAt: 0
    };
  }

  function ensureDashboardTab(tabs) {
    const dashboardKey = '/secure/index.php';
    const withoutDuplicates = tabs.filter((tab) => (tab.key || tab.url) !== dashboardKey && tab.url !== dashboardKey);
    return [dashboardTab()].concat(withoutDuplicates);
  }

  function normalizeUrl() {
    const params = new URLSearchParams(window.location.search);
    params.delete('presence_blocked');
    const query = params.toString();
    return window.location.pathname + (query ? '?' + query : '');
  }

  function currentPathName() {
    return window.location.pathname.split('/').pop() || 'index.php';
  }

  function pageKind(page) {
    if (['index.php', 'modules.php', 'settings.php', 'search.php', 'profile.php'].includes(page)) {
      return 'root';
    }
    if (page === 'callercard.php') {
      return 'callercard';
    }
    if (/^[a-z0-9_-]+-menu\.php$/i.test(page) || page === 'im_menu.php') {
      return 'module-menu';
    }
    if ([
      'incidents.php', 'changes.php', 'change_activities.php', 'problems.php', 'events.php', 'ubm_items.php', 'ubm_tree.php', 'assets.php', 'kb_items.php', 'news.php',
      'persons.php', 'persongroups.php', 'operators.php', 'operatorgroups.php', 'suppliers.php', 'buildings.php', 'customers.php',
      'set-am-types.php', 'set-am-connections.php', 'set-am-config-templates.php', 'set-ls-cat.php', 'set-ls-status.php', 'set-templates.php', 'set-mailrules.php', 'set-imaprules.php', 'set-priority.php'
    ].includes(page)) {
      return 'result';
    }
    if ([
      'new_incident.php', 'new_change.php', 'new_problem.php', 'new_event.php', 'new_ubm_item.php',
      'new_person.php', 'new_persongroup.php', 'new_operator.php', 'new_operatorgroup.php', 'new_supplier.php', 'new_building.php', 'new_customer.php',
      'new_opgrouplink.php', 'new_persongrouplink.php',
      'new_status.php', 'edit_status.php', 'new_cat.php', 'edit_cat.php', 'new_subcat.php', 'edit_subcat.php',
      'new_template.php', 'edit_template.php', 'edit_template_activity.php', 'edit_change_activity.php', 'new_assettype.php', 'edit_assettype.php', 'edit_amfield.php',
      'new_news.php', 'edit_news.php'
    ].includes(page)) {
      return 'new-task';
    }
    return 'content';
  }

  function pageShouldHaveTab(page) {
    return pageKind(page) !== 'content';
  }

  function currentTabKey() {
    const page = currentPathName();
    const kind = pageKind(page);
    if (kind === 'callercard') {
      return '/secure/callercard.php';
    }
    if (page === 'index.php') {
      return '/secure/index.php';
    }
    if (kind === 'module-menu') {
      return sessionStorage.getItem(activeTabKeyStorage) || normalizeUrl();
    }
    return normalizeUrl();
  }

  function shouldCreateTabForCurrentPage() {
    return pageShouldHaveTab(currentPathName());
  }

  function shouldKeepStoredTab(tab) {
    try {
      const url = new URL(tab.url, window.location.origin);
      const keyUrl = new URL(tab.key || tab.url, window.location.origin);
      const page = url.pathname.split('/').pop() || 'index.php';
      const keyPage = keyUrl.pathname.split('/').pop() || 'index.php';
      return (url.pathname.includes('/secure/') || url.pathname === '/secure/index.php') && (pageShouldHaveTab(page) || pageShouldHaveTab(keyPage));
    } catch (error) {
      return false;
    }
  }

  function cleanTitle(value) {
    return String(value || '')
      .replace(/\s+/g, ' ')
      .replace(/^ITSM\s*[-|]\s*/i, '')
      .trim();
  }

  function currentTitle() {
    const h1 = document.querySelector('.content h1, h1');
    const title = cleanTitle(h1 ? h1.textContent : document.title);
    return title || 'ITSM';
  }

  function currentTabLabel() {
    const meta = document.querySelector('[data-tab-title]');
    const title = meta && meta.dataset.tabTitle ? cleanTitle(meta.dataset.tabTitle) : currentTitle();
    const subtitle = meta && meta.dataset.tabSubtitle ? cleanTitle(meta.dataset.tabSubtitle) : '';
    return {
      title: title || 'ITSM',
      subtitle: subtitle
    };
  }

  function addOrTouchCurrentTab(tabs) {
    const now = Date.now();
    const url = normalizeUrl();
    const key = currentTabKey();
    const label = currentTabLabel();
    tabs.forEach((tab, index) => {
      if (!tab.createdAt) {
        tab.createdAt = tab.lastActive || now + index;
      }
      if (!tab.key) {
        tab.key = tab.url;
      }
    });
    const existing = tabs.find((tab) => tab.key === key || tab.url === url);

    if (existing) {
      existing.key = key;
      existing.url = url;
      existing.title = label.title;
      existing.subtitle = label.subtitle;
      existing.lastActive = now;
      return tabs;
    }

    tabs.push({
      key: key,
      url: url,
      title: label.title,
      subtitle: label.subtitle,
      lastActive: now,
      createdAt: now
    });

    while (countClosableTabs(tabs) > maxTabs) {
      const currentUrl = normalizeUrl();
      let oldestIndex = -1;
      let oldestValue = Infinity;

      tabs.forEach((tab, index) => {
        if (tab.url === currentUrl) {
          return;
        }
      if (tab.pinned) {
        return;
      }
      const lastActive = Number(tab.lastActive || 0);
        if (lastActive < oldestValue) {
          oldestValue = lastActive;
          oldestIndex = index;
        }
      });

    if (oldestIndex === -1) {
        oldestIndex = tabs.findIndex((tab) => !tab.pinned);
      }
      if (oldestIndex === -1) {
        return;
      }
      clearTabState(tabs[oldestIndex]);
      tabs.splice(oldestIndex, 1);
    }

    return tabs;
  }

  function identifierVariants(identifier) {
    if (!identifier) {
      return [];
    }
    const variants = [identifier];
    try {
      const url = new URL(identifier, window.location.origin);
      variants.push(url.pathname + url.search);
      variants.push(url.pathname);
    } catch (error) {}
    return [...new Set(variants.filter(Boolean))];
  }

  function removeStateForIdentifier(identifier) {
    identifierVariants(identifier).forEach((variant) => {
      sessionStorage.removeItem(draftPrefix + variant);
      sessionStorage.removeItem(scrollPrefix + variant);
      sessionStorage.removeItem(viewPrefix + variant);
      sessionStorage.removeItem(presenceTokenPrefix + variant);
      sessionStorage.removeItem(pendingPresenceSubmitPrefix + variant);
      sessionStorage.setItem(closedDraftPrefix + variant, String(Date.now()));
    });
  }

  function clearTabState(tab) {
    if (!tab) {
      return;
    }
    const identifiers = [...new Set(
      (typeof tab === 'string' ? [tab] : [tab.url, tab.key]).filter(Boolean)
    )];
    identifiers.forEach(removeStateForIdentifier);
  }

  function closeTab(identifier) {
    const currentUrl = normalizeUrl();
    const activeKey = sessionStorage.getItem(activeTabKeyStorage);
    let tabs = readTabs();
    clearTabState(identifier);
    tabs.filter((tab) => !tab.pinned && (tab.url === identifier || tab.key === identifier)).forEach(clearTabState);
    let nextTabs = tabs.filter((tab) => tab.pinned || (tab.url !== identifier && tab.key !== identifier));
    nextTabs = ensureDashboardTab(nextTabs);
    writeTabs(nextTabs);

    if (identifier === currentUrl || identifier === activeKey) {
      nextTabs = nextTabs.sort((a, b) => Number(b.lastActive || 0) - Number(a.lastActive || 0));
      if (nextTabs[0]) {
        sessionStorage.setItem(activeTabKeyStorage, nextTabs[0].key || nextTabs[0].url);
      }
      window.location.href = nextTabs[0] ? nextTabs[0].url : '/secure/index.php';
      return;
    }

    renderTabs();
  }

  function renderTabs() {
    const currentUrl = normalizeUrl();
    const activeKey = currentTabKey();
    const tabs = ensureDashboardTab(readTabs().filter(shouldKeepStoredTab)).sort((a, b) => {
      if (a.pinned && !b.pinned) { return -1; }
      if (!a.pinned && b.pinned) { return 1; }
      return Number(a.createdAt || a.lastActive || 0) - Number(b.createdAt || b.lastActive || 0);
    });
    writeTabs(tabs);
    tabbar.innerHTML = '';

    tabs.forEach((tab) => {
      const item = document.createElement('div');
      item.className = 'secure-tab' + (tab.url === currentUrl || tab.key === activeKey ? ' is-active' : '');
      if (tab.pinned) {
        item.className += ' is-pinned';
      }

      const link = document.createElement('a');
      link.href = tab.url;
      if (tab.icon === 'home') {
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-home';
        link.appendChild(icon);
        link.setAttribute('aria-label', tab.title || i18n.dashboard);
        link.setAttribute('title', tab.title || i18n.dashboard);
      } else {
        if (tab.subtitle) {
          const primary = document.createElement('span');
          primary.className = 'secure-tab-primary';
          primary.textContent = tab.title || tab.url;
          const secondary = document.createElement('span');
          secondary.className = 'secure-tab-secondary';
          secondary.textContent = tab.subtitle;
          link.appendChild(primary);
          link.appendChild(secondary);
          link.setAttribute('title', (tab.title || tab.url) + ' - ' + tab.subtitle);
        } else {
          link.textContent = tab.title || tab.url;
        }
      }
      link.addEventListener('click', () => {
        sessionStorage.setItem(activeTabKeyStorage, tab.key || tab.url);
      });

      item.appendChild(link);
      if (!tab.pinned) {
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'secure-tab-close';
        close.setAttribute('aria-label', i18n.closeTab);
        close.textContent = '×';
        close.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          closeTab(tab.key || tab.url);
        });
        item.appendChild(close);
      }
      tabbar.appendChild(item);
    });

    const activeTab = tabbar.querySelector('.secure-tab.is-active');
    if (activeTab && typeof activeTab.scrollIntoView === 'function') {
      activeTab.scrollIntoView({ inline: 'nearest', block: 'nearest' });
    }
  }

  function formFieldKey(field, fallbackIndex) {
    if (field.name) {
      return field.name;
    }
    if (field.id) {
      return '#' + field.id;
    }
    return '__field_' + fallbackIndex;
  }

  function shouldSaveField(field) {
    if (!field || field.disabled) {
      return false;
    }
    const type = String(field.type || '').toLowerCase();
    if (['button', 'submit', 'reset', 'file', 'password'].includes(type)) {
      return false;
    }
    return Boolean(field.name || field.id);
  }

  function collectDraft(form) {
    const data = {};
    Array.from(form.elements).forEach((field, index) => {
      if (!shouldSaveField(field)) {
        return;
      }
      const key = formFieldKey(field, index);
      const type = String(field.type || '').toLowerCase();
      if (type === 'checkbox' || type === 'radio') {
        data[key] = field.checked;
        return;
      }
      data[key] = field.value;
    });
    return data;
  }

  function restoreDraft(form, data) {
    Array.from(form.elements).forEach((field, index) => {
      if (!shouldSaveField(field)) {
        return;
      }
      const key = formFieldKey(field, index);
      if (!Object.prototype.hasOwnProperty.call(data, key)) {
        return;
      }
      const type = String(field.type || '').toLowerCase();
      if (type === 'checkbox' || type === 'radio') {
        field.checked = Boolean(data[key]);
      } else {
        field.value = data[key];
      }
      field.dispatchEvent(new Event('change', { bubbles: true }));
      field.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  function setupDraftSaving() {
    if (!shouldCreateTabForCurrentPage() && !currentPresenceTarget()) {
      return;
    }
    const forms = Array.from(document.querySelectorAll('.content form'));
    if (forms.length === 0) {
      return;
    }

    const draftKey = draftPrefix + normalizeUrl();
    const pendingSubmitKey = pendingPresenceSubmitPrefix + normalizeUrl();
    const presenceBlocked = new URLSearchParams(window.location.search).get('presence_blocked') === '1';
    const currentKey = currentTabKey();
    const draftIdentifiers = [...new Set(identifierVariants(normalizeUrl()).concat(identifierVariants(currentKey)))];
    const wasClosed = draftIdentifiers.some((identifier) => sessionStorage.getItem(closedDraftPrefix + identifier));
    const shouldRestoreDraft = presenceBlocked
      || (!wasClosed && (
        initialActiveTabKey === currentKey
        || initialActiveTabKey === normalizeUrl()
        || existingTabIdentifiersBeforeBoot.has(currentKey)
        || existingTabIdentifiersBeforeBoot.has(normalizeUrl())
      ));

    if (sessionStorage.getItem(pendingSubmitKey) === '1') {
      if (!presenceBlocked) {
        sessionStorage.removeItem(draftKey);
      }
      sessionStorage.removeItem(pendingSubmitKey);
    }

    if (!shouldRestoreDraft) {
      draftIdentifiers.forEach((identifier) => {
        sessionStorage.removeItem(draftPrefix + identifier);
      });
    } else {
      try {
        const draft = JSON.parse(sessionStorage.getItem(draftKey) || '{}');
        forms.forEach((form, formIndex) => {
          if (draft[formIndex]) {
            restoreDraft(form, draft[formIndex]);
          }
        });
      } catch (error) {
        sessionStorage.removeItem(draftKey);
      }
    }

    const saveDraft = () => {
      const draft = {};
      forms.forEach((form, formIndex) => {
        draft[formIndex] = collectDraft(form);
      });
      sessionStorage.setItem(draftKey, JSON.stringify(draft));
      draftIdentifiers.forEach((identifier) => {
        sessionStorage.removeItem(closedDraftPrefix + identifier);
      });
    };

    forms.forEach((form) => {
      form.addEventListener('input', saveDraft);
      form.addEventListener('change', saveDraft);
      form.addEventListener('submit', () => {
        if (currentPresenceTarget()) {
          sessionStorage.setItem(pendingSubmitKey, '1');
          saveDraft();
          return;
        }
        window.setTimeout(() => {
          if (form.dataset.keepDraftOnSubmit === '1') {
            form.dataset.keepDraftOnSubmit = '';
            return;
          }
          sessionStorage.removeItem(draftKey);
        }, 0);
      });
    });
  }

  function setupScrollMemory() {
    if (!shouldCreateTabForCurrentPage()) {
      return;
    }
    const scrollKey = scrollPrefix + normalizeUrl();
    const storedScroll = Number(sessionStorage.getItem(scrollKey) || 0);
    if (storedScroll > 0) {
      window.setTimeout(() => window.scrollTo(0, storedScroll), 0);
    }

    let scrollTimer = null;
    const saveScroll = () => {
      if (scrollTimer) {
        window.clearTimeout(scrollTimer);
      }
      scrollTimer = window.setTimeout(() => {
        sessionStorage.setItem(scrollKey, String(window.scrollY || 0));
      }, 100);
    };

    window.addEventListener('scroll', saveScroll, { passive: true });
    window.addEventListener('beforeunload', () => {
      sessionStorage.setItem(scrollKey, String(window.scrollY || 0));
    });
  }

  function activateTicketView(viewName) {
    if (!viewName) {
      return false;
    }
    const targetTab = Array.from(document.querySelectorAll('[data-ticket-view-tab]')).find((tab) => tab.dataset.ticketViewTab === viewName);
    const targetPanel = Array.from(document.querySelectorAll('[data-ticket-view-panel]')).find((panel) => panel.dataset.ticketViewPanel === viewName);
    if (!targetTab || !targetPanel) {
      return false;
    }

    document.querySelectorAll('[data-ticket-view-tab]').forEach((button) => {
      const active = button.dataset.ticketViewTab === viewName;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-ticket-view-panel]').forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.ticketViewPanel === viewName);
    });
    return true;
  }

  function setupTicketViewMemory() {
    const tabs = document.querySelectorAll('[data-ticket-view-tab]');
    if (!tabs.length || !shouldCreateTabForCurrentPage()) {
      return;
    }

    const viewKey = viewPrefix + currentTabKey();
    const storedView = sessionStorage.getItem(viewKey);
    if (storedView) {
      window.setTimeout(() => activateTicketView(storedView), 0);
    }

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        if (tab.dataset.ticketViewTab) {
          sessionStorage.setItem(viewKey, tab.dataset.ticketViewTab);
        }
      });
    });
  }

  function currentPresenceTarget() {
    const page = currentPathName();
    if (page === 'callercard.php') {
      return null;
    }
    const params = new URLSearchParams(window.location.search);
    const id = Number(params.get('id') || 0);
    const map = {
      'edit_incident.php': 'incident',
      'edit_change.php': 'change',
      'edit_problem.php': 'problem',
      'edit_event.php': 'event',
      'edit_change_activity.php': 'changeactivity',
      'edit_ubm_item.php': 'ubm'
    };
    if (!map[page] || !id) {
      return null;
    }
    return {
      tasktype: map[page],
      taskid: id
    };
  }

  function randomToken() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return String(Date.now()) + '-' + Math.random().toString(16).slice(2);
  }

  function ensurePresenceBanner() {
    let banner = document.getElementById('form_presence_warning');
    if (banner) {
      return banner;
    }
    const content = document.querySelector('.content');
    if (!content) {
      return null;
    }
    banner = document.createElement('div');
    banner.id = 'form_presence_warning';
    banner.className = 'form-presence-warning';
    banner.style.display = 'none';
    banner.innerHTML = '<span></span><button type="button" class="btn-primary form-presence-refresh">' + i18n.refresh + '</button>';
    const firstChild = content.firstElementChild;
    if (firstChild) {
      content.insertBefore(banner, firstChild);
    } else {
      content.appendChild(banner);
    }
    banner.querySelector('button').addEventListener('click', () => {
      sessionStorage.removeItem(presenceTokenPrefix + normalizeUrl());
      window.location.reload();
    });
    return banner;
  }

  function showPresenceWarning(message, stale) {
    const banner = ensurePresenceBanner();
    if (!banner) {
      return;
    }
    banner.querySelector('span').textContent = message;
    banner.classList.toggle('is-stale', Boolean(stale));
    banner.style.display = message ? 'flex' : 'none';
  }

  function setupFormPresence() {
    const target = currentPresenceTarget();
    if (!target) {
      return;
    }
    const tokenKey = presenceTokenPrefix + normalizeUrl();
    const token = randomToken();
    sessionStorage.setItem(tokenKey, token);
    let presenceClosed = false;
    let submittingPresenceForm = false;

    document.querySelectorAll('.content form').forEach((form) => {
      if (String(form.method || '').toLowerCase() !== 'post') {
        return;
      }
      if (!form.querySelector('input[name="presence_token"]')) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'presence_token';
        input.value = token;
        form.appendChild(input);
      }
      form.addEventListener('submit', (event) => {
        if (form.dataset.presenceStale === '1') {
          form.dataset.keepDraftOnSubmit = '1';
          event.preventDefault();
          showPresenceWarning(i18n.saveBlocked, true);
          return;
        }
        submittingPresenceForm = true;
      });
    });

    let stale = false;
    const heartbeat = () => {
      const body = new URLSearchParams();
      body.set('tasktype', target.tasktype);
      body.set('taskid', String(target.taskid));
      body.set('token', token);
      body.set('action', 'heartbeat');
      fetch('/secure/form_presence.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        credentials: 'same-origin'
      })
        .then((response) => response.ok ? response.json() : null)
        .then((data) => {
          if (!data || !data.ok) {
            return;
          }
          stale = Boolean(data.stale);
          document.querySelectorAll('.content form').forEach((form) => {
            form.dataset.presenceStale = stale ? '1' : '';
          });
          if (stale) {
            showPresenceWarning((data.saved_by || i18n.someoneElse) + ' ' + i18n.someoneElseSaved, true);
            return;
          }
          const names = (data.others || []).map((row) => row.operatorname).filter(Boolean);
          if (names.length > 0) {
            showPresenceWarning(names.length + ' ' + (names.length === 1 ? i18n.operatorSingular : i18n.operatorPlural) + ' ' + i18n.alsoOpen + ' ' + names.join(', ') + '.', false);
          } else {
            showPresenceWarning('', false);
          }
        })
        .catch(() => {});
    };

    heartbeat();
    window.setInterval(heartbeat, 7000);

    const closePresence = () => {
      if (presenceClosed) {
        return;
      }
      if (submittingPresenceForm) {
        return;
      }
      presenceClosed = true;
      const body = new URLSearchParams();
      body.set('tasktype', target.tasktype);
      body.set('taskid', String(target.taskid));
      body.set('token', token);
      body.set('action', 'close');
      if (navigator.sendBeacon) {
        navigator.sendBeacon('/secure/form_presence.php', new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' }));
        return;
      }
      fetch('/secure/form_presence.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        credentials: 'same-origin',
        keepalive: true
      }).catch(() => {});
    };

    window.addEventListener('pagehide', closePresence);
  }

  let tabs = ensureDashboardTab(readTabs().filter(shouldKeepStoredTab));
  existingTabIdentifiersBeforeBoot = new Set();
  tabs.forEach((tab) => {
    if (tab.url) {
      existingTabIdentifiersBeforeBoot.add(tab.url);
    }
    if (tab.key) {
      existingTabIdentifiersBeforeBoot.add(tab.key);
    }
  });
  if (shouldCreateTabForCurrentPage()) {
    const kind = pageKind(currentPathName());
    const activeKey = sessionStorage.getItem(activeTabKeyStorage);
    if (kind === 'module-menu' && activeKey) {
      let replacedTab = false;
      tabs.forEach((tab) => {
        if (tab.key === activeKey) {
          replacedTab = true;
          tab.url = normalizeUrl();
          const label = currentTabLabel();
          tab.title = label.title;
          tab.subtitle = label.subtitle;
          tab.lastActive = Date.now();
        }
      });
      if (!replacedTab) {
        tabs = addOrTouchCurrentTab(tabs);
      }
    } else {
      tabs = addOrTouchCurrentTab(tabs);
    }
    sessionStorage.setItem(activeTabKeyStorage, currentTabKey());
  } else {
    const activeKey = sessionStorage.getItem(activeTabKeyStorage);
    if (activeKey) {
      tabs.forEach((tab) => {
        if (tab.key === activeKey) {
          tab.url = normalizeUrl();
          const label = currentTabLabel();
          tab.title = label.title;
          tab.subtitle = label.subtitle;
          tab.lastActive = Date.now();
        }
      });
    }
  }
  writeTabs(tabs);
  renderTabs();
  setupDraftSaving();
  setupScrollMemory();
  setupTicketViewMemory();
  setupFormPresence();

  const logoutLink = document.querySelector('.topbar a[href*="logout.php"]');
  if (logoutLink) {
    logoutLink.addEventListener('click', () => {
      sessionStorage.removeItem(storageKey);
      sessionStorage.removeItem(activeTabKeyStorage);
      Object.keys(sessionStorage).forEach((key) => {
        if (key.startsWith(draftPrefix) || key.startsWith(scrollPrefix) || key.startsWith(viewPrefix) || key.startsWith(presenceTokenPrefix) || key.startsWith(closedDraftPrefix)) {
          sessionStorage.removeItem(key);
        }
      });
    });
  }
})();
</script>
</body>
</html>
