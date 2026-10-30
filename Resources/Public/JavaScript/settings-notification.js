import Notification from '@typo3/backend/notification.js';

function lang(key, fallback) {
  return window.TYPO3?.lang?.[key] || fallback;
}

function showSettingsNotifications() {
  const notifications = [
    {
      holder: document.querySelector('[data-role="settings-saved-notification"]'),
      title: lang('settings.saved', 'Settings saved.'),
      message: lang('settings.saved.description', 'Your AutoAlt.ai settings have been saved successfully.'),
    },
    {
      holder: document.querySelector('[data-role="error-logs-cleared-notification"]'),
      title: lang('settings.errorLogs.cleared', 'Error logs cleared.'),
      message: lang('settings.errorLogs.clearedDescription', 'All stored AutoAlt.ai error log entries have been removed.'),
    },
  ];

  notifications.forEach(({ holder, title, message }) => {
    if (holder) {
      Notification.success(holder.dataset.title || title, holder.dataset.message || message);
    }
  });

  const url = new URL(window.location.href);
  url.searchParams.delete('saved');
  url.searchParams.delete('logsCleared');
  window.history.replaceState(window.history.state, '', url.toString());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', showSettingsNotifications, { once: true });
} else {
  showSettingsNotifications();
}
