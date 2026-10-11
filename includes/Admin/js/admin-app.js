/* ENZO Admin React SPA — v1.1.0 (full UI)
 * Vanilla wp.element + wp-components (no build step).
 * All REST calls are cookie-authenticated with the wp_rest nonce. */
(function (wp) {
  var e = wp.element;
  var c = wp.components;
  var useState = e.useState;
  var useEffect = e.useEffect;
  var useRef = e.useRef;
  var Fragment = e.Fragment;

  /** rest_url() yields "/wp-json/…" on pretty permalinks but "/?rest_route=…"
   *  on plain ones — merge path + first query param into the right separator. */
  function apiUrl(path) {
    var base = ENZO_REST_URL.url;
    path = String(path).replace(/^\//, '');
    var q = path.indexOf('?');
    if (base.indexOf('?') !== -1 && q !== -1) {
      path = path.slice(0, q) + '&' + path.slice(q + 1);
    }
    return base + path;
  }

  function api(path, opts) {
    opts = opts || {};
    // Cookie-authenticated REST calls require the wp_rest nonce, or WP
    // answers 403 rest_forbidden for permission_callback-gated routes.
    return fetch(apiUrl(path), Object.assign({
      headers: Object.assign(
        { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        ENZO_REST_URL.nonce ? { 'X-WP-Nonce': ENZO_REST_URL.nonce } : {}
      ),
      credentials: 'same-origin',
    }, opts)).then(function (r) {
      return r.json().catch(function () { return {}; });
    });
  }

  // ------------------------------------------------------------------ helpers

  function fmtDateTime(utc) {
    if (!utc) return '—';
    var d = new Date(String(utc).replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) return utc;
    try {
      return d.toLocaleString('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch (err) { return utc; }
  }

  /** UTC "Y-m-d H:i:s" → local "Y-m-dTH:mm" for datetime-local inputs. */
  function utcToLocalInput(utc) {
    if (!utc) return '';
    var d = new Date(String(utc).replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) return String(utc).replace(' ', 'T');
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  var POST_STATUS = {
    draft: ['پیش‌نویس', 'badge-gray'],
    approved: ['تأییدشده', 'badge-blue'],
    scheduled: ['زمان‌بندی‌شده', 'badge-purple'],
    published: ['منتشرشده', 'badge-green'],
    cancelled: ['لغوشده', 'badge-red'],
  };
  var QUEUE_STATUS = {
    pending: ['در انتظار', 'badge-gray'],
    running: ['در حال اجرا', 'badge-blue'],
    done: ['انجام‌شده', 'badge-green'],
    failed: ['ناموفق', 'badge-red'],
    cancelled: ['لغوشده', 'badge-yellow'],
  };
  var LOG_LEVELS = {
    info: ['اطلاعات', 'badge-blue'],
    warning: ['هشدار', 'badge-yellow'],
    error: ['خطا', 'badge-red'],
    debug: ['دیباگ', 'badge-gray'],
  };

  function Badge(props) {
    return e.createElement('span', { className: 'enzo-badge ' + (props.cls || 'badge-gray') }, props.label);
  }
  function postBadge(s) { var v = POST_STATUS[s] || [s, 'badge-gray']; return e.createElement(Badge, { label: v[0], cls: v[1] }); }
  function queueBadge(s) { var v = QUEUE_STATUS[s] || [s, 'badge-gray']; return e.createElement(Badge, { label: v[0], cls: v[1] }); }
  function levelBadge(s) { var v = LOG_LEVELS[s] || [s, 'badge-gray']; return e.createElement(Badge, { label: v[0], cls: v[1] }); }

  function Loading() { return e.createElement('div', { className: 'enzo-loading' }, e.createElement(c.Spinner)); }
  function ErrorBox(props) {
    return e.createElement(c.Notice, { status: 'error', onRemove: props.onRemove }, props.children);
  }
  function SuccessBox(props) {
    return e.createElement(c.Notice, { status: 'success', onRemove: props.onRemove }, props.children);
  }
  function Msg(props) {
    if (!props.msg) return null;
    return props.msg.type === 'success'
      ? e.createElement(SuccessBox, { onRemove: props.clear }, props.msg.text)
      : e.createElement(ErrorBox, { onRemove: props.clear }, props.msg.text);
  }
  function Empty(props) {
    return e.createElement('div', { className: 'enzo-empty' },
      e.createElement('span', { className: 'dashicons dashicons-' + (props.icon || 'awards') }),
      e.createElement('p', null, props.text || 'موردی یافت نشد.'));
  }

  function PageHeader(props) {
    return e.createElement('div', { className: 'enzo-page-header' },
      e.createElement('div', null,
        e.createElement('h2', null, props.title),
        props.desc && e.createElement('p', { className: 'enzo-page-desc' }, props.desc)),
      props.actions && e.createElement('div', { className: 'enzo-page-actions' }, props.actions));
  }

  /** Standard response unwrap: throws Error(message) on success=false. */
  function unwrap(promise) {
    return promise.then(function (d) {
      if (d && d.success === false) {
        var err = new Error(d.message || 'درخواست ناموفق بود.');
        err.code = d.code;
        throw err;
      }
      return d;
    });
  }

  /** Simple async loader hook: {data, loading, error, reload} */
  function useAsync(fn, deps) {
    var state = useState({ data: null, loading: true, error: null });
    var setData = state[1];
    var tick = useState(0);
    useEffect(function () {
      var alive = true;
      setData(function (s) { return Object.assign({}, s, { loading: true, error: null }); });
      fn().then(function (data) {
        if (alive) setData({ data: data, loading: false, error: null });
      }).catch(function (err) {
        if (alive) setData({ data: null, loading: false, error: (err && err.message) || 'خطا' });
      });
      return function () { alive = false; };
    }, (deps || []).concat([tick[0]]));
    function reload() { tick[1](function (t) { return t + 1; }); }
    return { data: state[0].data, loading: state[0].loading, error: state[0].error, reload: reload };
  }

  function Table(props) {
    var cols = props.columns;
    var rows = props.rows;
    return e.createElement('table', { className: 'enzo-table' },
      e.createElement('thead', null, e.createElement('tr', null, cols.map(function (col, i) {
        return e.createElement('th', { key: i }, col.label);
      }))),
      e.createElement('tbody', null, rows.map(function (row, ri) {
        return e.createElement('tr', { key: row.id || ri }, cols.map(function (col, ci) {
          return e.createElement('td', { key: ci }, col.render ? col.render(row) : (row[col.key] || '—'));
        }));
      })));
  }

  function ConfirmModal(props) {
    return e.createElement(c.Modal, {
      title: props.title, onRequestClose: props.onCancel, className: 'enzo-modal',
    },
      e.createElement('p', null, props.message),
      e.createElement('div', { className: 'enzo-modal-actions' },
        e.createElement(c.Button, { isDestructive: true, variant: 'primary', onClick: props.onConfirm, isBusy: props.busy }, 'بله، انجام بده'),
        e.createElement(c.Button, { variant: 'tertiary', onClick: props.onCancel }, 'انصراف')));
  }

  function channelOptions(list) {
    var opts = [{ label: '— کانال انتخاب نشده —', value: 0 }];
    (list || []).forEach(function (ch) {
      opts.push({ label: ch.title + (ch.username ? ' (' + ch.username + ')' : ''), value: ch.id });
    });
    return opts;
  }

  // ------------------------------------------------------------------ Dashboard

  function Dashboard() {
    var q = useAsync(function () { return unwrap(api('/dashboard')); }, []);
    if (q.loading) return e.createElement(Loading);
    if (q.error || !q.data || !q.data.counts) return e.createElement(ErrorBox, null, 'خطا در دریافت داشبورد');

    var counts = q.data.counts;
    var posts = counts.posts || {};
    var queue = counts.queue || {};
    var postTotal = Object.keys(posts).reduce(function (s, k) { return s + posts[k]; }, 0);
    var queueTotal = Object.keys(queue).reduce(function (s, k) { return s + queue[k]; }, 0);
    var recent = q.data.recent_logs || [];

    var cards = [
      { icon: 'groups', label: 'کانال‌های متصل', value: counts.channels || 0, sub: null, cls: 'stat-teal' },
      { icon: 'media-document', label: 'پست‌ها', value: postTotal, sub: 'پیش‌نویس: ' + (posts.draft || 0) + ' · منتشرشده: ' + (posts.published || 0), cls: 'stat-blue' },
      { icon: 'sort', label: 'کارهای صف', value: queueTotal, sub: 'در انتظار: ' + (queue.pending || 0) + ' · ناموفق: ' + (queue.failed || 0), cls: 'stat-purple' },
      { icon: 'marker', label: 'لاگ‌ها', value: counts.logs || 0, sub: null, cls: 'stat-gray' },
    ];
    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'داشبورد',
        desc: 'نمای کلی وضعیت انزو — کانال‌ها، محتوا، صف انتشار و لاگ‌ها',
      }),
      e.createElement('div', { className: 'enzo-stat-grid' }, cards.map(function (card) {
        return e.createElement('div', { key: card.label, className: 'enzo-stat ' + card.cls },
          e.createElement('span', { className: 'dashicons dashicons-' + card.icon }),
          e.createElement('div', { className: 'enzo-stat-value' }, Number(card.value).toLocaleString('fa-IR')),
          e.createElement('div', { className: 'enzo-stat-label' }, card.label),
          card.sub && e.createElement('div', { className: 'enzo-stat-sub' }, card.sub));
      })),
      e.createElement('h3', { className: 'enzo-h3' }, 'لاگ‌های اخیر'),
      recent.length === 0
        ? e.createElement(Empty, { icon: 'marker', text: 'لاگی ثبت نشده است.' })
        : e.createElement(Table, {
            columns: [
              { label: 'سطح', render: function (r) { return levelBadge(r.level); } },
              { label: 'بخش', render: function (r) { return e.createElement('code', null, r.scope); } },
              { label: 'پیام', render: function (r) { return r.message; } },
              { label: 'زمان', render: function (r) { return fmtDateTime(r.created_at); } },
            ],
            rows: recent,
          }));
  }

  // ------------------------------------------------------------------ Telegram

  function Telegram() {
    var status = useAsync(function () { return unwrap(api('/telegram/status')); }, []);
    var token = useState('');
    var busy = useState(false);
    var msg = useState(null);
    var confirmDisconnect = useState(false);

    function save() {
      msg[1](null);
      busy[1](true);
      unwrap(api('/telegram/connect', { method: 'POST', body: JSON.stringify({ token: token[0] }) }))
        .then(function (d) {
          msg[1]({ type: 'success', text: 'اتصال موفق — ربات @' + (d.bot_username || '؟') + ' متصل شد.' });
          token[1]('');
          status.reload();
          busy[1](false);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busy[1](false); });
    }
    function test() {
      msg[1](null);
      busy[1](true);
      unwrap(api('/telegram/test', { method: 'POST', body: JSON.stringify({}) }))
        .then(function (d) {
          var ok = d.result && d.result.success;
          msg[1]({ type: ok ? 'success' : 'error', text: ok ? 'اتصال سالم است (getMe موفق).' : ((d.result && d.result.message) || 'اتصال ناموفق.') });
          busy[1](false);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busy[1](false); });
    }
    function disconnect() {
      busy[1](true);
      unwrap(api('/telegram/disconnect', { method: 'POST', body: JSON.stringify({}) }))
        .then(function () {
          msg[1]({ type: 'success', text: 'اتصال قطع شد.' });
          confirmDisconnect[1](false);
          status.reload();
          busy[1](false);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busy[1](false); });
    }

    var connected = status.data && status.data.connected;
    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'تلگرام',
        desc: 'اتصال ربات تلگرام — توکن با رمزنگاری XChaCha20 ذخیره می‌شود و هرگز نمایش داده نمی‌شود',
      }),
      connected && e.createElement(c.Notice, { status: 'success', isDismissible: false },
        'ربات متصل است: @' + ((status.data.bot_username) || '؟') + ' (شناسه: ' + (status.data.bot_id || '—') + ')'),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      e.createElement(c.Card, null,
        e.createElement(c.CardBody, null,
          e.createElement(c.TextControl, {
            label: 'Bot Token (از @BotFather)',
            type: 'password',
            value: token[0],
            placeholder: '123456789:AAH…',
            onChange: token[1],
            help: 'قالب معتبر: ۱۳ رقم، دونقطه، حداقل ۳۰ کاراکتر. توکن فقط ذخیره می‌شود و هرگز برنمی‌گردد.',
          }),
          e.createElement('div', { className: 'enzo-btn-row' },
            e.createElement(c.Button, { variant: 'primary', onClick: save, isBusy: busy[0], disabled: !token[0] }, 'ذخیره و اتصال'),
            e.createElement(c.Button, { variant: 'secondary', onClick: test, isBusy: busy[0], disabled: !connected }, 'تست اتصال'),
            connected && e.createElement(c.Button, { isDestructive: true, variant: 'secondary', onClick: function () { confirmDisconnect[1](true); } }, 'قطع اتصال')))),
      confirmDisconnect[0] && e.createElement(ConfirmModal, {
        title: 'قطع اتصال ربات',
        message: 'توکن ربات حذف می‌شود. زمان‌بندی‌های در حال اجرا تا اتصال دوباره کار نمی‌کنند. مطمئنید؟',
        busy: busy[0],
        onConfirm: disconnect,
        onCancel: function () { confirmDisconnect[1](false); },
      }),
      e.createElement(c.Card, { style: { marginTop: '16px' } },
        e.createElement(c.CardHeader, null, e.createElement('b', null, 'راهنمای سریع')),
        e.createElement(c.CardBody, null,
          e.createElement('ol', { className: 'enzo-ol' },
            e.createElement('li', null, 'در تلگرام به ', e.createElement('code', null, '@BotFather'), ' پیام دهید و با ', e.createElement('code', null, '/newbot'), ' یک ربات بسازید.'),
            e.createElement('li', null, 'توکن دریافتی را در فیلد بالا وارد و ذخیره کنید.'),
            e.createElement('li', null, 'ربات را ', e.createElement('b', null, 'ادمین کانال'), ' هدف کنید (دسترسی ارسال پیام داشته باشد).'),
            e.createElement('li', null, 'شناسه عددی کانال (مثل ', e.createElement('code', null, '-100…'), ') را در بخش «کانال‌ها» ثبت کنید.')))));
  }

  // ------------------------------------------------------------------ Channels

  function Channels() {
    var list = useAsync(function () { return unwrap(api('/channels')); }, []);
    var open = useState(false);
    var busy = useState(false);
    var msg = useState(null);
    var form = useState({ chat_id: '', title: '', username: '' });

    function save() {
      msg[1](null);
      busy[1](true);
      unwrap(api('/channels', { method: 'POST', body: JSON.stringify({
        chat_id: form[0].chat_id, title: form[0].title, username: form[0].username, chat_type: 'channel',
      }) }))
        .then(function () {
          open[1](false);
          form[1]({ chat_id: '', title: '', username: '' });
          msg[1]({ type: 'success', text: 'کانال ثبت شد.' });
          list.reload();
          busy[1](false);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busy[1](false); });
    }

    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'کانال‌ها',
        desc: 'کانال‌های مقصد انتشار — شناسه عددی کانال را از @userinfobot یا از شناسه یک پیام بگیرید',
        actions: e.createElement(c.Button, { variant: 'primary', onClick: function () { open[1](true); } }, 'افزودن کانال'),
      }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      list.loading ? e.createElement(Loading)
        : !list.data || !list.data.channels || list.data.channels.length === 0
          ? e.createElement(Empty, { icon: 'groups', text: 'هنوز کانالی ثبت نشده است.' })
          : e.createElement(Table, {
              columns: [
                { label: 'عنوان', render: function (r) { return e.createElement('b', null, r.title); } },
                { label: 'آیدی کانال', render: function (r) { return r.username || '—'; } },
                { label: 'شناسه عددی', render: function (r) { return e.createElement('code', null, String(r.chat_id)); } },
                { label: 'وضعیت', render: function () { return e.createElement(Badge, { label: 'متصل', cls: 'badge-green' }); } },
                { label: 'تاریخ ثبت', render: function (r) { return fmtDateTime(r.created_at); } },
              ],
              rows: list.data.channels,
            }),
      open[0] && e.createElement(c.Modal, { title: 'افزودن کانال', onRequestClose: function () { open[1](false); } },
        e.createElement(c.TextControl, { label: 'عنوان کانال', value: form[0].title, onChange: function (v) { form[1](Object.assign({}, form[0], { title: v })); } }),
        e.createElement(c.TextControl, { label: 'شناسه کاربر (اختیاری)', placeholder: '@mychannel', value: form[0].username, onChange: function (v) { form[1](Object.assign({}, form[0], { username: v })); } }),
        e.createElement(c.TextControl, { label: 'شناسه عددی کانال (chat_id)', placeholder: '-1001234567890', value: form[0].chat_id, onChange: function (v) { form[1](Object.assign({}, form[0], { chat_id: v })); }, help: 'برای کانال‌ها عدد منفی است.' }),
        e.createElement('div', { className: 'enzo-modal-actions' },
          e.createElement(c.Button, { variant: 'primary', onClick: save, isBusy: busy[0], disabled: !form[0].chat_id || !form[0].title }, 'ثبت کانال'),
          e.createElement(c.Button, { variant: 'tertiary', onClick: function () { open[1](false); } }, 'انصراف'))));
  }

  // ------------------------------------------------------------------ AI Providers

  function Providers() {
    var list = useAsync(function () { return unwrap(api('/ai/providers')); }, []);
    var busyId = useState(0);
    var msg = useState(null);
    var edit = useState(null);
    var testing = useState(0);

    function openNew() { edit[1]({ id: 0, name: '', baseUrl: 'https://api.openai.com/v1/chat/completions', apiKey: '', model: 'gpt-4o-mini', is_default: false }); }
    function openEdit(row) {
      edit[1]({
        id: row.id, name: row.name, baseUrl: (row.config && row.config.baseUrl) || '',
        apiKey: '', model: (row.config && row.config.model) || '', is_default: !!row.is_default,
      });
    }
    function save() {
      var f = edit[0];
      var body = { name: f.name, config: { baseUrl: f.baseUrl, model: f.model }, is_default: f.is_default };
      if (f.apiKey) body.secret = f.apiKey;
      msg[1](null);
      busyId[1](f.id || -1);
      unwrap(api(f.id ? '/ai/providers/' + f.id : '/ai/providers', {
        method: 'POST', body: JSON.stringify(body),
      }))
        .then(function () {
          edit[1](null);
          msg[1]({ type: 'success', text: f.id ? 'سرویس به‌روزرسانی شد.' : 'سرویس افزوده شد.' });
          list.reload();
          busyId[1](0);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busyId[1](0); });
    }
    function test(row) {
      msg[1](null);
      testing[1](row.id);
      unwrap(api('/ai/providers/' + row.id + '/test', { method: 'POST', body: JSON.stringify({}) }))
        .then(function (d) {
          var ok = d.result && d.result.success;
          msg[1]({ type: ok ? 'success' : 'error', text: ok ? 'اتصال Provider سالم است.' : ((d.result && d.result.message) || 'اتصال ناموفق.') });
          testing[1](0);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); testing[1](0); });
    }

    var rows = (list.data && list.data.providers) || [];
    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'سرویس‌های AI',
        desc: 'هر سرویس سازگار با OpenAI را وصل کنید — کلید API رمزنگاری‌شده ذخیره می‌شود و هرگز نمایش داده نمی‌شود',
        actions: e.createElement(c.Button, { variant: 'primary', onClick: openNew }, 'افزودن سرویس'),
      }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      list.loading ? e.createElement(Loading)
        : rows.length === 0
          ? e.createElement(Empty, { icon: 'admin-network', text: 'هنوز سرویسی تنظیم نشده است. برای تولید محتوا یکی اضافه کنید.' })
          : e.createElement(Table, {
              columns: [
                { label: 'نام', render: function (r) { return e.createElement('b', null, r.name); } },
                { label: 'Base URL', render: function (r) { return e.createElement('code', { className: 'enzo-code-sm' }, (r.config && r.config.baseUrl) || '—'); } },
                { label: 'مدل', render: function (r) { return (r.config && r.config.model) || 'پیش‌فرض'; } },
                { label: 'کلید API', render: function () { return e.createElement(Badge, { label: 'رمزنگاری‌شده', cls: 'badge-green' }); } },
                { label: 'پیش‌فرض', render: function (r) { return r.is_default ? e.createElement(Badge, { label: 'پیش‌فرض', cls: 'badge-blue' }) : '—'; } },
                { label: 'عملیات', render: function (r) {
                    return e.createElement('div', { className: 'enzo-btn-row' },
                      e.createElement(c.Button, { variant: 'secondary', isSmall: true, isBusy: testing[0] === r.id, onClick: function () { test(r); } }, 'تست'),
                      e.createElement(c.Button, { variant: 'tertiary', isSmall: true, onClick: function () { openEdit(r); } }, 'ویرایش'),
                      !r.is_default && e.createElement(c.Button, {
                        variant: 'tertiary', isSmall: true, isBusy: busyId[0] === r.id,
                        onClick: function () {
                          busyId[1](r.id);
                          unwrap(api('/ai/providers/' + r.id, { method: 'POST', body: JSON.stringify({ is_default: true }) }))
                            .then(function () { list.reload(); busyId[1](0); })
                            .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busyId[1](0); });
                        },
                      }, 'پیش‌فرض کن'));
                  } },
              ],
              rows: rows,
            }),
      e.createElement('p', { className: 'enzo-hint' },
        'توصیه برای هاست‌های ایران: OpenRouter یا Groq — با Base URL خودشان، دسترسی پایدارتری دارند. نمونه: ',
        e.createElement('code', null, 'https://openrouter.ai/api/v1/chat/completions')),
      edit[0] && e.createElement(c.Modal, {
        title: edit[0].id ? 'ویرایش سرویس' : 'افزودن سرویس',
        onRequestClose: function () { edit[1](null); },
      },
        e.createElement(c.TextControl, { label: 'نام سرویس', value: edit[0].name, onChange: function (v) { edit[1](Object.assign({}, edit[0], { name: v })); } }),
        e.createElement(c.TextControl, {
          label: 'Base URL',
          help: 'نشانی کامل سرویس چت، مثل https://api.openai.com/v1/chat/completions',
          placeholder: 'https://api.openai.com/v1/chat/completions',
          value: edit[0].baseUrl,
          onChange: function (v) { edit[1](Object.assign({}, edit[0], { baseUrl: v })); },
        }),
        e.createElement(c.TextControl, {
          label: 'مدل', placeholder: 'gpt-4o-mini / claude-3.5-sonnet / llama-3.1-70b …',
          value: edit[0].model,
          onChange: function (v) { edit[1](Object.assign({}, edit[0], { model: v })); },
        }),
        e.createElement(c.TextControl, {
          label: 'کلید API ' + (edit[0].id ? '(برای تغییر، مقدار جدید را وارد کنید)' : ''),
          type: 'password',
          placeholder: edit[0].id ? '••••' : 'sk-…',
          value: edit[0].apiKey,
          onChange: function (v) { edit[1](Object.assign({}, edit[0], { apiKey: v })); },
        }),
        e.createElement(c.CheckboxControl, {
          label: 'به‌عنوان سرویس پیش‌فرض استفاده شود',
          checked: edit[0].is_default,
          onChange: function (v) { edit[1](Object.assign({}, edit[0], { is_default: v })); },
        }),
        e.createElement('div', { className: 'enzo-modal-actions' },
          e.createElement(c.Button, { variant: 'primary', onClick: save, isBusy: busyId[0] !== 0, disabled: !edit[0].name || !edit[0].baseUrl }, 'ذخیره'),
          e.createElement(c.Button, { variant: 'tertiary', onClick: function () { edit[1](null); } }, 'انصراف'))));
  }

  // ------------------------------------------------------------------ AI Generate

  function AiGenerate(props) {
    var channels = useAsync(function () { return unwrap(api('/channels')); }, []);
    var busy = useState(false);
    var msg = useState(null);
    var form = useState({ operation: 'generate', input: '', title: '', channel_id: 0 });
    var result = useState(null);

    function run() {
      msg[1](null);
      result[1](null);
      busy[1](true);
      unwrap(api('/ai/generate', { method: 'POST', body: JSON.stringify({
        operation: form[0].operation,
        input: form[0].input,
        title: form[0].title,
        channel_id: form[0].channel_id || 0,
      }) }))
        .then(function (d) { result[1](d); busy[1](false); })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busy[1](false); });
    }

    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'AI تولید',
        desc: 'تولید، بازنویسی، خلاصه و ترجمه محتوا — خروجی به‌صورت خودکار پیش‌نویس ذخیره می‌شود',
      }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      e.createElement('div', { className: 'enzo-two-col' },
        e.createElement(c.Card, null,
          e.createElement(c.CardBody, null,
            e.createElement(c.SelectControl, {
              label: 'عملیات',
              options: [
                { label: 'تولید محتوا', value: 'generate' },
                { label: 'بازنویسی', value: 'rewrite' },
                { label: 'خلاصه‌سازی', value: 'summarize' },
                { label: 'ترجمه', value: 'translate' },
                { label: 'عنوان‌سازی', value: 'title' },
                { label: 'کپشن', value: 'caption' },
              ],
              value: form[0].operation,
              onChange: function (v) { form[1](Object.assign({}, form[0], { operation: v })); },
            }),
            e.createElement(c.TextareaControl, {
              label: form[0].operation === 'translate' ? 'متن برای ترجمه' : 'موضوع یا ورودی',
              placeholder: 'مثال: معرفی تخفیف ۲۰٪ محصولات پاییزی برای کانال فروشگاه',
              rows: 6,
              value: form[0].input,
              onChange: function (v) { form[1](Object.assign({}, form[0], { input: v })); },
            }),
            e.createElement(c.TextControl, {
              label: 'عنوان (اختیاری)',
              value: form[0].title,
              onChange: function (v) { form[1](Object.assign({}, form[0], { title: v })); },
            }),
            e.createElement(c.SelectControl, {
              label: 'کانال مقصد (اختیاری)',
              options: channelOptions(channels.data && channels.data.channels),
              value: form[0].channel_id,
              onChange: function (v) { form[1](Object.assign({}, form[0], { channel_id: parseInt(v, 10) || 0 })); },
            }),
            e.createElement(c.Button, { variant: 'primary', isBusy: busy[0], disabled: !form[0].input, onClick: run }, 'اجرا با AI'))),
        e.createElement(c.Card, null,
          e.createElement(c.CardHeader, null, e.createElement('b', null, 'خروجی')),
          e.createElement(c.CardBody, null,
            !result[0] && !busy[0] && e.createElement(Empty, { icon: 'admin-post', text: 'نتیجه پس از اجرا اینجا نمایش داده می‌شود.' }),
            busy[0] && e.createElement(Loading),
            result[0] && e.createElement(Fragment, null,
              e.createElement('div', { className: 'enzo-btn-row', style: { marginBottom: '12px' } },
                e.createElement(Badge, { label: 'مدل: ' + (result[0].model || '—'), cls: 'badge-blue' }),
                result[0].tokens_used > 0 && e.createElement(Badge, { label: 'توکن: ' + result[0].tokens_used, cls: 'badge-gray' })),
              e.createElement(c.TextareaControl, {
                rows: 12, value: result[0].content,
                onChange: function (v) { result[1](Object.assign({}, result[0], { content: v })); },
              }),
              e.createElement('div', { className: 'enzo-btn-row', style: { marginTop: '12px' } },
                e.createElement(c.Button, {
                  variant: 'primary',
                  onClick: function () {
                    unwrap(api('/posts', { method: 'POST', body: JSON.stringify({
                      title: form[0].title, body: result[0].content, channel_id: form[0].channel_id || 0,
                    }) }))
                      .then(function (d) { msg[1]({ type: 'success', text: 'به‌عنوان پیش‌نویس ذخیره شد (پست #' + d.id + ').' }); })
                      .catch(function (err) { msg[1]({ type: 'error', text: err.message }); });
                  },
                }, 'ذخیره به‌عنوان پیش‌نویس'),
                e.createElement(c.Button, { variant: 'secondary', onClick: function () { props.go('posts'); } }, 'رفتن به پست‌ها')),
              result[0].stored_post_id > 0 && e.createElement('p', { className: 'enzo-hint' },
                'نسخه اصلی به‌صورت خودکار به‌عنوان پیش‌نویس #' + result[0].stored_post_id + ' ذخیره شده است.'))))));
  }

  // ------------------------------------------------------------------ Preview modal

  function PreviewModal(props) {
    var q = useAsync(function () { return unwrap(api('/posts/' + props.id + '/preview', { method: 'POST', body: JSON.stringify({}) })); }, [props.id]);
    var p = q.data && q.data.preview;
    return e.createElement(c.Modal, { title: 'پیش‌نمایش پیام تلگرام', onRequestClose: props.onClose, className: 'enzo-modal enzo-modal-preview' },
      q.loading && e.createElement(Loading),
      q.error && e.createElement(ErrorBox, null, q.error),
      p && e.createElement(Fragment, null,
        e.createElement('div', { className: 'enzo-tg-preview' },
          e.createElement('div', { className: 'enzo-tg-header' },
            e.createElement('div', { className: 'enzo-tg-avatar' }, (p.channel_title || 'ک').slice(0, 1)),
            e.createElement('div', null,
              e.createElement('div', { className: 'enzo-tg-title' }, p.channel_title || 'کانال'),
              p.channel_username && e.createElement('div', { className: 'enzo-tg-user' }, p.channel_username))),
          p.title && e.createElement('div', { className: 'enzo-tg-title-text' }, p.title),
          e.createElement('div', { className: 'enzo-tg-body' }, p.text),
          e.createElement('div', { className: 'enzo-tg-footer' },
            e.createElement('span', null, p.char_count.toLocaleString('fa-IR') + ' کاراکتر'),
            e.createElement('span', { className: 'enzo-tg-clock' }, '⟵ زمان ارسال، سر وقت خودش'))),
        e.createElement('p', { className: 'enzo-hint' },
          'حداکثر طول پیام متنی تلگرام ۴۰۹۶ کاراکتر است.',
          p.char_count > 4096 ? e.createElement('b', { className: 'enzo-error-text' }, ' متن شما بلندتر از حد مجاز است!') : null)),
      e.createElement('div', { className: 'enzo-modal-actions' },
        e.createElement(c.Button, { variant: 'secondary', onClick: props.onClose }, 'بستن')));
  }

  // ------------------------------------------------------------------ Edit / Schedule modals

  function EditPostModal(props) {
    var busy = useState(false);
    var v = useState({ id: props.value.id, title: props.value.title, body: props.value.body, channel_id: props.value.channel_id });

    function save() {
      busy[1](true);
      unwrap(api('/posts/' + v[0].id, { method: 'POST', body: JSON.stringify({
        title: v[0].title, body: v[0].body, channel_id: v[0].channel_id,
      }) }))
        .then(function () { busy[1](false); props.onSaved(); })
        .catch(function (err) { busy[1](false); props.onError(err.message); });
    }
    return e.createElement(c.Modal, { title: 'ویرایش پست #' + v[0].id, onRequestClose: props.onClose, className: 'enzo-modal' },
      e.createElement(c.TextControl, { label: 'عنوان', value: v[0].title, onChange: function (x) { v[1](Object.assign({}, v[0], { title: x })); } }),
      e.createElement(c.TextareaControl, { label: 'متن', rows: 10, value: v[0].body, onChange: function (x) { v[1](Object.assign({}, v[0], { body: x })); } }),
      e.createElement(c.SelectControl, {
        label: 'کانال', options: channelOptions(props.channels), value: v[0].channel_id,
        onChange: function (x) { v[1](Object.assign({}, v[0], { channel_id: parseInt(x, 10) || 0 })); },
      }),
      e.createElement('div', { className: 'enzo-modal-actions' },
        e.createElement(c.Button, { variant: 'primary', isBusy: busy[0], onClick: save }, 'ذخیره'),
        e.createElement(c.Button, { variant: 'tertiary', onClick: props.onClose }, 'انصراف')));
  }

  function ScheduleModal(props) {
    function defaultWhen() {
      if (props.initial) return utcToLocalInput(props.initial);
      var d = new Date(Date.now() + 60 * 60 * 1000);
      d.setSeconds(0, 0);
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    var when = useState(defaultWhen());
    var busy = useState(false);

    function save() {
      busy[1](true);
      unwrap(api('/posts/' + props.postId + '/schedule', { method: 'POST', body: JSON.stringify({ scheduled_at: when[0].replace('T', ' ') }) }))
        .then(function () { busy[1](false); props.onSaved(); })
        .catch(function (err) { busy[1](false); props.onError(err.message); });
    }
    return e.createElement(c.Modal, { title: 'زمان‌بندی انتشار پست #' + props.postId, onRequestClose: props.onClose },
      e.createElement('p', { className: 'enzo-hint' }, 'زمان به ساعت محلی سایت شماست؛ در صف به UTC تبدیل می‌شود و یک کار انتشار به صف اضافه می‌شود.'),
      e.createElement('label', { className: 'components-base-control__label' }, 'زمان انتشار'),
      e.createElement('input', {
        type: 'datetime-local', className: 'enzo-datetime',
        value: when[0],
        onChange: function (ev) { when[1](ev.target.value); },
      }),
      e.createElement('div', { className: 'enzo-modal-actions' },
        e.createElement(c.Button, { variant: 'primary', isBusy: busy[0], onClick: save }, 'ثبت زمان‌بندی'),
        e.createElement(c.Button, { variant: 'tertiary', onClick: props.onClose }, 'انصراف')));
  }

  // ------------------------------------------------------------------ Posts

  function Posts(props) {
    var status = useState('all');
    var list = useAsync(function () {
      return unwrap(api('/posts?status=' + status[0]));
    }, [status[0]]);
    var channels = useAsync(function () { return unwrap(api('/channels')); }, []);
    var msg = useState(null);
    var busyId = useState(0);
    var preview = useState(null);
    var editing = useState(null);
    var scheduling = useState(null);
    var confirmCancel = useState(0);

    var channelMap = {};
    ((channels.data && channels.data.channels) || []).forEach(function (ch) { channelMap[ch.id] = ch; });

    function action(id, path, okText) {
      msg[1](null);
      busyId[1](id);
      unwrap(api('/posts/' + id + '/' + path, { method: 'POST', body: JSON.stringify({}) }))
        .then(function () {
          if (okText) msg[1]({ type: 'success', text: okText });
          list.reload();
          busyId[1](0);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); list.reload(); busyId[1](0); });
    }

    var rows = (list.data && list.data.posts) || [];
    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'پست‌ها',
        desc: 'گردش کار: پیش‌نویس ← تأیید شما ← زمان‌بندی یا انتشار فوری — هیچ‌چیز بدون تأیید شما منتشر نمی‌شود',
        actions: e.createElement(c.Button, { variant: 'secondary', onClick: function () { props.go('content'); } }, 'ایجاد پست جدید'),
      }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      e.createElement('div', { className: 'enzo-filter-row' },
        e.createElement(c.SelectControl, {
          label: 'وضعیت',
          options: [
            { label: 'همه', value: 'all' },
            { label: 'پیش‌نویس', value: 'draft' },
            { label: 'تأییدشده', value: 'approved' },
            { label: 'زمان‌بندی‌شده', value: 'scheduled' },
            { label: 'منتشرشده', value: 'published' },
            { label: 'لغوشده', value: 'cancelled' },
          ],
          value: status[0], onChange: status[1],
        })),
      list.loading ? e.createElement(Loading)
        : rows.length === 0
          ? e.createElement(Empty, { icon: 'media-document', text: 'پستی در این وضعیت نیست.' })
          : e.createElement(Table, {
              columns: [
                { label: '#', render: function (r) { return e.createElement('code', null, String(r.id)); } },
                { label: 'عنوان / متن', render: function (r) {
                    return e.createElement(Fragment, null,
                      r.title ? e.createElement('b', null, r.title) : null,
                      e.createElement('div', { className: 'enzo-cell-sub' }, (r.body || '').slice(0, 70) + ((r.body || '').length > 70 ? '…' : '')));
                  } },
                { label: 'کانال', render: function (r) { return (channelMap[r.channel_id] && channelMap[r.channel_id].title) || '—'; } },
                { label: 'وضعیت', render: function (r) { return postBadge(r.status); } },
                { label: 'زمان‌بندی', render: function (r) { return r.scheduled_at ? fmtDateTime(r.scheduled_at) : '—'; } },
                { label: 'انتشار', render: function (r) { return r.published_at ? fmtDateTime(r.published_at) : '—'; } },
                { label: 'عملیات', render: function (r) {
                    var b = [];
                    b.push(e.createElement(c.Button, { key: 'p', variant: 'tertiary', isSmall: true, onClick: function () { preview[1](r.id); } }, 'پیش‌نمایش'));
                    if (r.status === 'draft' || r.status === 'approved') {
                      b.push(e.createElement(c.Button, { key: 'e', variant: 'tertiary', isSmall: true, onClick: function () {
                        editing[1]({ id: r.id, title: r.title || '', body: r.body || '', channel_id: r.channel_id || 0 });
                      } }, 'ویرایش'));
                    }
                    if (r.status === 'draft') {
                      b.push(e.createElement(c.Button, { key: 'a', variant: 'secondary', isSmall: true, isBusy: busyId[0] === r.id, onClick: function () { action(r.id, 'approve', 'پست تأیید شد.'); } }, 'تأیید'));
                    }
                    if (r.status === 'approved' || r.status === 'draft') {
                      b.push(e.createElement(c.Button, { key: 's', variant: 'secondary', isSmall: true, onClick: function () { scheduling[1]({ id: r.id, initial: r.scheduled_at || '' }); } }, 'زمان‌بندی'));
                      b.push(e.createElement(c.Button, { key: 'n', variant: 'primary', isSmall: true, isBusy: busyId[0] === r.id, onClick: function () { action(r.id, 'publish', 'پست منتشر شد.'); } }, 'انتشار فوری'));
                    }
                    if (r.status === 'scheduled' || r.status === 'approved' || r.status === 'draft') {
                      b.push(e.createElement(c.Button, { key: 'c', isDestructive: true, variant: 'tertiary', isSmall: true, onClick: function () { confirmCancel[1](r.id); } }, 'لغو'));
                    }
                    return e.createElement('div', { className: 'enzo-btn-row' }, b);
                  } },
              ],
              rows: rows,
            }),
      preview[0] && e.createElement(PreviewModal, { id: preview[0], onClose: function () { preview[1](null); } }),
      editing[0] && e.createElement(EditPostModal, {
        value: editing[0], channels: (channels.data && channels.data.channels) || [],
        onClose: function () { editing[1](null); },
        onSaved: function () { editing[1](null); msg[1]({ type: 'success', text: 'پست ذخیره شد.' }); list.reload(); },
        onError: function (t) { msg[1]({ type: 'error', text: t }); },
      }),
      scheduling[0] && e.createElement(ScheduleModal, {
        postId: scheduling[0].id, initial: scheduling[0].initial,
        onClose: function () { scheduling[1](null); },
        onSaved: function () { scheduling[1](null); msg[1]({ type: 'success', text: 'زمان‌بندی ثبت شد و کار آن به صف اضافه شد.' }); list.reload(); },
        onError: function (t) { msg[1]({ type: 'error', text: t }); },
      }),
      confirmCancel[0] > 0 && e.createElement(ConfirmModal, {
        title: 'لغو پست',
        message: 'پست لغو می‌شود و کار صف مرتبط آن هم کنسل خواهد شد. مطمئنید؟',
        busy: busyId[0] === confirmCancel[0],
        onConfirm: function () { var id = confirmCancel[0]; confirmCancel[1](0); action(id, 'cancel', 'پست لغو شد.'); },
        onCancel: function () { confirmCancel[1](0); },
      }));
  }

  // ------------------------------------------------------------------ Queue

  function Queue() {
    var status = useState('all');
    var list = useAsync(function () { return unwrap(api('/queue?status=' + status[0])); }, [status[0]]);
    var msg = useState(null);
    var busyId = useState(0);
    var auto = useState(true);
    var timer = useRef(null);

    // Auto-refresh every 15s so running/completed jobs stay fresh.
    useEffect(function () {
      if (!auto[0]) return undefined;
      timer.current = setInterval(function () { list.reload(); }, 15000);
      return function () { clearInterval(timer.current); };
    }, [auto[0], status[0]]);

    function jobAction(id, verb, okText) {
      msg[1](null);
      busyId[1](id);
      unwrap(api('/queue/' + id + '/' + verb, { method: 'POST', body: JSON.stringify({}) }))
        .then(function () { if (okText) msg[1]({ type: 'success', text: okText }); list.reload(); busyId[1](0); })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); list.reload(); busyId[1](0); });
    }

    var rows = (list.data && list.data.jobs) || [];
    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'صف انتشار',
        desc: 'صف اتمیک با قفل و تلاش مجدد نمایی — هر کار فقط یک‌بار اجرا می‌شود',
        actions: e.createElement(c.CheckboxControl, { label: 'به‌روزرسانی خودکار (۱۵ ثانیه)', checked: auto[0], onChange: auto[1] }),
      }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      e.createElement('div', { className: 'enzo-filter-row' },
        e.createElement(c.SelectControl, {
          label: 'وضعیت',
          options: [
            { label: 'همه', value: 'all' },
            { label: 'در انتظار', value: 'pending' },
            { label: 'در حال اجرا', value: 'running' },
            { label: 'انجام‌شده', value: 'done' },
            { label: 'ناموفق', value: 'failed' },
            { label: 'لغوشده', value: 'cancelled' },
          ],
          value: status[0], onChange: status[1],
        })),
      list.loading ? e.createElement(Loading)
        : rows.length === 0
          ? e.createElement(Empty, { icon: 'sort', text: 'کاری در صف نیست.' })
          : e.createElement(Table, {
              columns: [
                { label: '#', render: function (r) { return e.createElement('code', null, String(r.id)); } },
                { label: 'نوع کار', render: function (r) { return e.createElement('code', null, r.job_type); } },
                { label: 'پست', render: function (r) { return r.payload && r.payload.post_id ? '#' + r.payload.post_id : '—'; } },
                { label: 'وضعیت', render: function (r) { return queueBadge(r.status); } },
                { label: 'تلاش', render: function (r) { return r.attempts + ' از ' + r.max_attempts; } },
                { label: 'اجرا بعدی', render: function (r) { return fmtDateTime(r.next_run_at); } },
                { label: 'خطا', render: function (r) { return r.last_error ? e.createElement('span', { className: 'enzo-error-text' }, r.last_error) : '—'; } },
                { label: 'عملیات', render: function (r) {
                    var b = [];
                    if (r.status === 'pending' || r.status === 'failed') {
                      b.push(e.createElement(c.Button, { key: 'run', variant: 'secondary', isSmall: true, isBusy: busyId[0] === r.id, onClick: function () { jobAction(r.id, 'run', 'کار اجرا شد.'); } }, 'اجرای الان'));
                    }
                    if (r.status === 'failed' || r.status === 'cancelled') {
                      b.push(e.createElement(c.Button, { key: 'retry', variant: 'secondary', isSmall: true, isBusy: busyId[0] === r.id, onClick: function () { jobAction(r.id, 'retry', 'کار به صف برگشت.'); } }, 'تلاش مجدد'));
                    }
                    if (r.status === 'pending' || r.status === 'failed') {
                      b.push(e.createElement(c.Button, { key: 'cancel', isDestructive: true, variant: 'tertiary', isSmall: true, isBusy: busyId[0] === r.id, onClick: function () { jobAction(r.id, 'cancel', 'کار لغو شد.'); } }, 'لغو'));
                    }
                    return b.length ? e.createElement('div', { className: 'enzo-btn-row' }, b) : '—';
                  } },
              ],
              rows: rows,
            }));
  }

  // ------------------------------------------------------------------ Scheduler

  function Scheduler(props) {
    var list = useAsync(function () { return unwrap(api('/posts?status=scheduled')); }, []);
    var channels = useAsync(function () { return unwrap(api('/channels')); }, []);
    var channelMap = {};
    ((channels.data && channels.data.channels) || []).forEach(function (ch) { channelMap[ch.id] = ch; });
    var rows = (list.data && list.data.posts) || [];
    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'زمان‌بندی',
        desc: 'پست‌های زمان‌بندی‌شده — اجرا با WP-Cron دقیقه‌ای انجام می‌شود',
        actions: e.createElement(c.Button, { variant: 'secondary', onClick: function () { props.go('queue'); } }, 'مشاهده صف انتشار'),
      }),
      list.loading ? e.createElement(Loading)
        : rows.length === 0
          ? e.createElement(Empty, { icon: 'calendar', text: 'زمان‌بندی فعالی ندارید. از بخش «پست‌ها» یک پست را زمان‌بندی کنید.' })
          : e.createElement(Table, {
              columns: [
                { label: '#', render: function (r) { return e.createElement('code', null, String(r.id)); } },
                { label: 'عنوان', render: function (r) { return e.createElement('b', null, r.title || (r.body || '').slice(0, 50)); } },
                { label: 'کانال', render: function (r) { return (channelMap[r.channel_id] && channelMap[r.channel_id].title) || '—'; } },
                { label: 'زمان انتشار', render: function (r) { return e.createElement('b', null, fmtDateTime(r.scheduled_at)); } },
              ],
              rows: rows,
            }),
      e.createElement(c.Card, { style: { marginTop: '16px' } },
        e.createElement(c.CardBody, null,
          e.createElement('b', null, 'نکته WP-Cron:'),
          e.createElement('p', null, 'در سایت‌های کم‌بازدید، WP-Cron فقط با ورود بازدیدکننده اجرا می‌شود. برای انتشار دقیق، cron واقعی سرور را تنظیم کنید:'),
          e.createElement('code', { className: 'enzo-code-block' }, '*/2 * * * * curl -s https://YOUR-SITE.com/wp-cron.php?doing_wp_cron'))));
  }

  // ------------------------------------------------------------------ Logs

  function Logs() {
    var scope = useState('');
    var level = useState('');
    var page = useState(1);
    var perPage = 20;
    var list = useAsync(function () {
      var qs = '?page=' + page[0] + '&per_page=' + perPage;
      if (scope[0]) qs += '&scope=' + scope[0];
      if (level[0]) qs += '&level=' + level[0];
      return unwrap(api('/logs' + qs));
    }, [scope[0], level[0], page[0]]);
    var confirmClear = useState(false);
    var msg = useState(null);
    var openCtx = useState(0);

    var data = list.data;
    var rows = (data && data.logs) || [];
    var total = (data && data.total) || 0;
    var pages = Math.max(1, Math.ceil(total / perPage));
    var ctxRow = null;
    rows.forEach(function (r) { if (r.id === openCtx[0]) ctxRow = r; });

    function clearAll() {
      unwrap(api('/logs', { method: 'DELETE', body: JSON.stringify({}) }))
        .then(function () { confirmClear[1](false); msg[1]({ type: 'success', text: 'لاگ‌ها پاک شدند.' }); list.reload(); })
        .catch(function (err) { confirmClear[1](false); msg[1]({ type: 'error', text: err.message }); });
    }

    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'لاگ‌ها',
        desc: 'لاگ ساختاریافته — مقادیر حساس (توکن/کلید) به‌صورت خودکار حذف می‌شوند',
        actions: e.createElement(c.Button, { isDestructive: true, variant: 'secondary', onClick: function () { confirmClear[1](true); } }, 'پاک کردن همه'),
      }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      e.createElement('div', { className: 'enzo-filter-row' },
        e.createElement(c.SelectControl, {
          label: 'بخش',
          options: [
            { label: 'همه بخش‌ها', value: '' },
            { label: 'سیستم', value: 'system' },
            { label: 'تلگرام', value: 'telegram' },
            { label: 'AI', value: 'ai' },
            { label: 'انتشار', value: 'publish' },
            { label: 'محتوا', value: 'content' },
            { label: 'لایسنس', value: 'license' },
          ],
          value: scope[0], onChange: function (v) { scope[1](v); page[1](1); },
        }),
        e.createElement(c.SelectControl, {
          label: 'سطح',
          options: [
            { label: 'همه سطوح', value: '' },
            { label: 'اطلاعات', value: 'info' },
            { label: 'هشدار', value: 'warning' },
            { label: 'خطا', value: 'error' },
          ],
          value: level[0], onChange: function (v) { level[1](v); page[1](1); },
        })),
      list.loading ? e.createElement(Loading)
        : rows.length === 0
          ? e.createElement(Empty, { icon: 'marker', text: 'لاگی با این فیلترها نیست.' })
          : e.createElement(Table, {
              columns: [
                { label: 'سطح', render: function (r) { return levelBadge(r.level); } },
                { label: 'بخش', render: function (r) { return e.createElement('code', null, r.scope); } },
                { label: 'پیام', render: function (r) { return r.message; } },
                { label: 'جزئیات', render: function (r) {
                    return r.context && Object.keys(r.context).length
                      ? e.createElement(c.Button, { variant: 'tertiary', isSmall: true, onClick: function () { openCtx[1](openCtx[0] === r.id ? 0 : r.id); } }, openCtx[0] === r.id ? 'بستن' : 'نمایش JSON')
                      : '—';
                  } },
                { label: 'زمان', render: function (r) { return fmtDateTime(r.created_at); } },
              ],
              rows: rows,
            }),
      ctxRow && e.createElement('pre', { className: 'enzo-json' }, JSON.stringify(ctxRow.context, null, 2)),
      pages > 1 && e.createElement('div', { className: 'enzo-pagination' },
        e.createElement(c.Button, { variant: 'secondary', isSmall: true, disabled: page[0] <= 1, onClick: function () { page[1](page[0] - 1); } }, 'قبلی'),
        e.createElement('span', null, 'صفحه ' + page[0] + ' از ' + pages + ' — مجموع: ' + total),
        e.createElement(c.Button, { variant: 'secondary', isSmall: true, disabled: page[0] >= pages, onClick: function () { page[1](page[0] + 1); } }, 'بعدی')),
      confirmClear[0] && e.createElement(ConfirmModal, {
        title: 'پاک کردن لاگ‌ها',
        message: 'همه لاگ‌ها برای همیشه حذف می‌شوند. مطمئنید؟',
        onConfirm: clearAll,
        onCancel: function () { confirmClear[1](false); },
      }));
  }

  // ------------------------------------------------------------------ Content create

  function ContentCreate(props) {
    var channels = useAsync(function () { return unwrap(api('/channels')); }, []);
    var busy = useState(false);
    var msg = useState(null);
    var form = useState({ title: '', body: '', channel_id: 0 });

    function save() {
      msg[1](null);
      busy[1](true);
      unwrap(api('/posts', { method: 'POST', body: JSON.stringify({
        title: form[0].title, body: form[0].body, channel_id: form[0].channel_id,
      }) }))
        .then(function (d) {
          msg[1]({ type: 'success', text: 'پست #' + d.id + ' به‌عنوان پیش‌نویس ذخیره شد.' });
          form[1]({ title: '', body: '', channel_id: 0 });
          busy[1](false);
        })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busy[1](false); });
    }

    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'ایجاد محتوا',
        desc: 'نوشتن دستی پست — یا از «AI تولید» برای تولید خودکار استفاده کنید',
        actions: e.createElement(c.Button, { variant: 'secondary', onClick: function () { props.go('ai-generate'); } }, 'تولید با AI'),
      }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      e.createElement(c.Card, null,
        e.createElement(c.CardBody, null,
          e.createElement(c.TextControl, { label: 'عنوان (اختیاری)', value: form[0].title, onChange: function (v) { form[1](Object.assign({}, form[0], { title: v })); } }),
          e.createElement(c.TextareaControl, { label: 'متن پست', rows: 10, value: form[0].body, onChange: function (v) { form[1](Object.assign({}, form[0], { body: v })); } }),
          e.createElement(c.SelectControl, { label: 'کانال', options: channelOptions(channels.data && channels.data.channels), value: form[0].channel_id, onChange: function (v) { form[1](Object.assign({}, form[0], { channel_id: parseInt(v, 10) || 0 })); } }),
          e.createElement('p', { className: 'enzo-hint' }, 'پست ابتدا پیش‌نویس ذخیره می‌شود؛ انتشار فقط پس از تأیید یا زمان‌بندی شما انجام می‌شود.'),
          e.createElement(c.Button, { variant: 'primary', isBusy: busy[0], disabled: !form[0].body, onClick: save }, 'ذخیره پیش‌نویس'))));
  }

  // ------------------------------------------------------------------ Generated

  function Generated(props) {
    var list = useAsync(function () { return unwrap(api('/posts?status=draft')); }, []);
    var channels = useAsync(function () { return unwrap(api('/channels')); }, []);
    var channelMap = {};
    ((channels.data && channels.data.channels) || []).forEach(function (ch) { channelMap[ch.id] = ch; });
    var preview = useState(null);
    var rows = (list.data && list.data.posts) || [];
    return e.createElement('div', null,
      e.createElement(PageHeader, {
        title: 'متن‌های تولید شده',
        desc: 'پیش‌نویس‌های خروجی AI — بازبینی، ویرایش و تأیید قبل از انتشار',
        actions: e.createElement(c.Button, { variant: 'secondary', onClick: function () { props.go('ai-generate'); } }, 'تولید جدید'),
      }),
      list.loading ? e.createElement(Loading)
        : rows.length === 0
          ? e.createElement(Empty, { icon: 'media-document', text: 'هنوز متنی تولید نشده است.' })
          : e.createElement(Table, {
              columns: [
                { label: '#', render: function (r) { return e.createElement('code', null, String(r.id)); } },
                { label: 'عنوان / متن', render: function (r) {
                    return e.createElement(Fragment, null,
                      r.title ? e.createElement('b', null, r.title) : null,
                      e.createElement('div', { className: 'enzo-cell-sub' }, (r.body || '').slice(0, 80) + ((r.body || '').length > 80 ? '…' : '')));
                  } },
                { label: 'کانال', render: function (r) { return (channelMap[r.channel_id] && channelMap[r.channel_id].title) || '—'; } },
                { label: 'تاریخ', render: function (r) { return fmtDateTime(r.created_at); } },
                { label: 'عملیات', render: function (r) {
                    return e.createElement('div', { className: 'enzo-btn-row' },
                      e.createElement(c.Button, { variant: 'tertiary', isSmall: true, onClick: function () { preview[1](r.id); } }, 'پیش‌نمایش'),
                      e.createElement(c.Button, { variant: 'secondary', isSmall: true, onClick: function () { props.go('posts'); } }, 'مدیریت و تأیید'));
                  } },
              ],
              rows: rows,
            }),
      preview[0] && e.createElement(PreviewModal, { id: preview[0], onClose: function () { preview[1](null); } }));
  }

  // ------------------------------------------------------------------ Settings

  function Settings() {
    var q = useAsync(function () { return unwrap(api('/settings')); }, []);
    var busy = useState(false);
    var msg = useState(null);
    var form = useState(null);

    useEffect(function () {
      if (q.data && q.data.values) {
        var v = q.data.values;
        form[1]({
          enzo_default_tone: v.enzo_default_tone || 'friendly',
          enzo_default_language: v.enzo_default_language || 'fa',
          enzo_post_template: v.enzo_post_template || '',
          enzo_auto_preview: String(v.enzo_auto_preview) === '1' || v.enzo_auto_preview === true,
          enzo_log_retention_days: v.enzo_log_retention_days || 30,
        });
      }
    }, [q.data]);

    function save() {
      msg[1](null);
      busy[1](true);
      var f = form[0];
      unwrap(api('/settings', { method: 'POST', body: JSON.stringify({
        enzo_default_tone: f.enzo_default_tone,
        enzo_default_language: f.enzo_default_language,
        enzo_post_template: f.enzo_post_template,
        enzo_auto_preview: f.enzo_auto_preview ? '1' : '0',
        enzo_log_retention_days: parseInt(f.enzo_log_retention_days, 10) || 30,
      }) }))
        .then(function (d) { msg[1]({ type: 'success', text: 'تنظیمات ذخیره شد (' + ((d.saved || []).length) + ' مورد).' }); busy[1](false); })
        .catch(function (err) { msg[1]({ type: 'error', text: err.message }); busy[1](false); });
    }

    var f = form[0];
    return e.createElement('div', null,
      e.createElement(PageHeader, { title: 'تنظیمات', desc: 'رفتار پیش‌فرض تولید محتوا و نگهداری لاگ' }),
      e.createElement(Msg, { msg: msg[0], clear: function () { msg[1](null); } }),
      !f && q.loading ? e.createElement(Loading)
        : !f ? e.createElement(ErrorBox, null, 'خطا در بارگذاری تنظیمات')
        : e.createElement(c.Card, null,
            e.createElement(c.CardBody, null,
              e.createElement(c.SelectControl, {
                label: 'لحن پیش‌فرض محتوا',
                options: [
                  { label: 'صمیمی', value: 'friendly' },
                  { label: 'حرفه‌ای', value: 'professional' },
                  { label: 'خبردهی', value: 'informative' },
                  { label: 'تبلیغاتی', value: 'promotional' },
                ],
                value: f.enzo_default_tone, onChange: function (v) { form[1](Object.assign({}, f, { enzo_default_tone: v })); },
              }),
              e.createElement(c.SelectControl, {
                label: 'زبان خروجی',
                options: [
                  { label: 'فارسی', value: 'fa' },
                  { label: 'انگلیسی', value: 'en' },
                ],
                value: f.enzo_default_language, onChange: function (v) { form[1](Object.assign({}, f, { enzo_default_language: v })); },
              }),
              e.createElement(c.TextareaControl, {
                label: 'قالب سفارشی پرامپت (اختیاری)',
                help: 'اگر خالی باشد، قالب داخلی افزونه استفاده می‌شود.',
                rows: 4, value: f.enzo_post_template,
                onChange: function (v) { form[1](Object.assign({}, f, { enzo_post_template: v })); },
              }),
              e.createElement(c.CheckboxControl, {
                label: 'نمایش پیش‌نمایش تلگرام بعد از تولید',
                checked: !!f.enzo_auto_preview,
                onChange: function (v) { form[1](Object.assign({}, f, { enzo_auto_preview: v })); },
              }),
              e.createElement(c.TextControl, {
                type: 'number', label: 'نگهداری لاگ‌ها (روز)',
                value: f.enzo_log_retention_days,
                onChange: function (v) { form[1](Object.assign({}, f, { enzo_log_retention_days: v })); },
              }),
              e.createElement(c.Button, { variant: 'primary', isBusy: busy[0], onClick: save }, 'ذخیره تنظیمات'))));
  }

  // ------------------------------------------------------------------ Help

  function Help() {
    return e.createElement('div', null,
      e.createElement(PageHeader, { title: 'راهنما', desc: 'راه‌اندازی در ۵ دقیقه و پاسخ سوال‌های رایج' }),
      e.createElement(c.Card, null,
        e.createElement(c.CardHeader, null, e.createElement('b', null, 'شروع سریع')),
        e.createElement(c.CardBody, null,
          e.createElement('ol', { className: 'enzo-ol' },
            e.createElement('li', null, 'بخش «تلگرام» — توکن ربات را از @BotFather بگیرید و ذخیره کنید.'),
            e.createElement('li', null, 'بخش «کانال‌ها» — کانال مقصد را با شناسه عددی ثبت کنید و ربات را ادمین کانال کنید.'),
            e.createElement('li', null, 'بخش «AI Providerها» — یک سرویس سازگار OpenAI (مثل OpenRouter) اضافه و تست کنید.'),
            e.createElement('li', null, 'بخش «AI تولید» — موضوع بدهید و خروجی را بازبینی کنید.'),
            e.createElement('li', null, 'بخش «پست‌ها» — تأیید کنید، زمان‌بندی کنید یا فوری منتشر کنید.')))),
      e.createElement(c.Card, { style: { marginTop: '16px' } },
        e.createElement(c.CardHeader, null, e.createElement('b', null, 'سوال‌های رایج')),
        e.createElement(c.CardBody, null,
          e.createElement('p', null, e.createElement('b', null, 'پست سر موعد منتشر نشد؟'), ' WP-Cron سایت کم‌بازدید است — cron واقعی سرور تنظیم کنید (راهنما در بخش زمان‌بندی).'),
          e.createElement('p', null, e.createElement('b', null, 'خطای اتصال تلگرام؟'), ' میزبان باید به api.telegram.org دسترسی داشته باشد.'),
          e.createElement('p', null, e.createElement('b', null, 'کلید API کجا ذخیره می‌شود؟'), ' رمزنگاری‌شده (XChaCha20) در دیتابیس — در پاسخ‌های REST و لاگ‌ها هرگز نمایش داده نمی‌شود.'))),
      e.createElement(c.Card, { style: { marginTop: '16px' } },
        e.createElement(c.CardBody, null,
          e.createElement('p', null, 'نسخه: ', e.createElement('code', null, (typeof ENZO_REST_URL !== 'undefined' && ENZO_REST_URL.version) || ''), ' — پشتیبانی: از طریق بازارچه خرید (تیکت).'))));
  }

  // ------------------------------------------------------------------ App shell

  var sections = [
    ['dashboard', 'داشبورد', 'dashboard'],
    ['generated', 'متن‌های تولید شده', 'media-document'],
    ['content', 'ایجاد محتوا', 'edit'],
    ['ai-generate', 'تولید با AI', 'admin-post'],
    ['posts', 'پست‌ها', 'admin-post'],
    ['scheduler', 'زمان‌بندی', 'calendar'],
    ['queue', 'صف انتشار', 'sort'],
    ['channels', 'کانال‌ها', 'groups'],
    ['ai-providers', 'سرویس‌های AI', 'admin-network'],
    ['telegram', 'تلگرام', 'share'],
    ['logs', 'لاگ‌ها', 'marker'],
    ['settings', 'تنظیمات', 'admin-settings'],
    ['help', 'راهنما', 'editor-help'],
  ];

  function App() {
    var active = useState('dashboard');
    function go(id) { active[1](id); }

    var view;
    switch (active[0]) {
      case 'dashboard': view = e.createElement(Dashboard, { go: go }); break;
      case 'generated': view = e.createElement(Generated, { go: go }); break;
      case 'content': view = e.createElement(ContentCreate, { go: go }); break;
      case 'ai-generate': view = e.createElement(AiGenerate, { go: go }); break;
      case 'posts': view = e.createElement(Posts, { go: go }); break;
      case 'scheduler': view = e.createElement(Scheduler, { go: go }); break;
      case 'queue': view = e.createElement(Queue, { go: go }); break;
      case 'channels': view = e.createElement(Channels, { go: go }); break;
      case 'ai-providers': view = e.createElement(Providers, { go: go }); break;
      case 'telegram': view = e.createElement(Telegram, { go: go }); break;
      case 'logs': view = e.createElement(Logs, { go: go }); break;
      case 'settings': view = e.createElement(Settings, { go: go }); break;
      case 'help': view = e.createElement(Help, { go: go }); break;
      default: view = e.createElement('p', null, 'بخش یافت نشد.');
    }

    return e.createElement('div', { className: 'enzo-admin-wrap' },
      e.createElement('div', { className: 'enzo-sidebar' },
        e.createElement('div', { className: 'enzo-brand' },
          e.createElement('span', { className: 'enzo-brand-icon' },
            e.createElement('svg', { viewBox: '0 0 24 24', width: '22', height: '22', fill: 'none' },
              e.createElement('path', { d: 'M21.5 3.4 2.9 10.6c-1 .4-1 1.8.1 2.1l4.6 1.5 1.7 5.2c.3 1 1.6 1.2 2.2.4l2.5-3 4.6 3.4c.8.6 2 .2 2.2-.8l2.3-14.6c.2-1-.7-1.8-1.6-1.4z', fill: '#fff' }))),
          e.createElement('div', null,
            e.createElement('div', { className: 'enzo-brand-name' }, 'انزو'),
            e.createElement('div', { className: 'enzo-brand-sub' }, 'AI Telegram Admin'))),
        sections.map(function (s) {
          return e.createElement('button', {
            key: s[0],
            className: 'enzo-nav-btn' + (active[0] === s[0] ? ' enzo-nav-active' : ''),
            onClick: function () { go(s[0]); },
          },
            e.createElement('span', { className: 'dashicons dashicons-' + s[2] }),
            e.createElement('span', null, s[1]));
        })),
      e.createElement('div', { className: 'enzo-main' }, view));
  }

  wp.element.render(e.createElement(App), document.getElementById('enzo-admin-root'));
})(window.wp);
