<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Logs Parser</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        :root { color-scheme: light dark; --border: #d1d5db; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, system-ui, "Segoe UI", Roboto, sans-serif;
            margin: 0; padding: 24px; background: #f8fafc; color: #111827;
        }
        h1 { margin: 0 0 16px; font-size: 22px; }
        h2 { margin: 24px 0 12px; font-size: 16px; color: #374151; }
        .card { background: #fff; padding: 18px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.06); margin-bottom: 18px; }
        .row { display: flex; gap: 14px; flex-wrap: wrap; align-items: end; }
        label { display: flex; flex-direction: column; font-size: 12px; color: var(--muted); gap: 4px; }
        input, select, button { padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font: inherit; background: #fff; }
        button { background: #2563eb; color: #fff; border-color: #2563eb; cursor: pointer; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        button.secondary { background: #fff; color: #2563eb; }
        .progress { height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden; }
        .progress > div { height: 100%; background: #10b981; transition: width .3s; }
        .charts { display: grid; gap: 18px; grid-template-columns: 1fr; }
        @media (min-width: 1100px) { .charts { grid-template-columns: 1fr 1fr; } }
        canvas { width: 100% !important; height: 320px !important; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        th { background: #f8fafc; cursor: pointer; user-select: none; position: sticky; top: 0; }
        th .arrow { color: #94a3b8; margin-left: 4px; }
        td.url { max-width: 380px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .muted { color: var(--muted); font-size: 12px; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 8px 12px; border-radius: 6px; }
        .ok { color: #047857; }
        .err { color: #b91c1c; }
    </style>
</head>
<body x-data="dashboard({ from: '{{ $defaultFrom }}', to: '{{ $defaultTo }}' })" x-init="init()">

<h1>Анализ логов веб-сервера</h1>

<section class="card">
    <h2>Загрузка лог-файла</h2>
    <form @submit.prevent="upload()" class="row">
        <label>
            Файл (.log / .txt)
            <input type="file" name="log_file" x-ref="file" required>
        </label>
        <button type="submit" :disabled="uploading">
            <span x-text="uploading ? 'Загрузка...' : 'Загрузить'"></span>
        </button>
        <span class="muted" x-show="!job">Дубль того же файла не создаст повторных записей.</span>
    </form>

    <template x-if="uploadError">
        <div class="alert" style="margin-top:10px" x-text="uploadError"></div>
    </template>

    <template x-if="job">
        <div style="margin-top:14px">
            <div class="row" style="justify-content: space-between">
                <div>
                    Job #<span x-text="job.id"></span> —
                    <span x-text="job.status"></span>
                    <span x-show="job.duplicate" class="muted">(уже импортировано)</span>
                </div>
                <div class="muted">
                    Обработано: <span x-text="job.lines_processed"></span>
                    <template x-if="job.lines_invalid > 0">
                        <span> · Невалидных: <span x-text="job.lines_invalid"></span></span>
                    </template>
                </div>
            </div>
            <div class="progress" style="margin-top:8px">
                <div :style="`width: ${progressPercent}%`"></div>
            </div>
            <template x-if="job.error">
                <div class="alert" style="margin-top:10px" x-text="'Ошибка: ' + job.error"></div>
            </template>
        </div>
    </template>
</section>

<section class="card">
    <h2>Фильтры</h2>
    <form @submit.prevent="reload()" class="row">
        <label>С даты <input type="date" x-model="filters.date_from" required></label>
        <label>По дату <input type="date" x-model="filters.date_to" required></label>
        <label>ОС
            <select x-model="filters.os">
                <option value="">— любая —</option>
                <option value="windows">Windows</option>
                <option value="macos">macOS</option>
                <option value="linux">Linux</option>
                <option value="android">Android</option>
                <option value="ios">iOS</option>
                <option value="other">Other</option>
            </select>
        </label>
        <label>Архитектура
            <select x-model="filters.arch">
                <option value="">— любая —</option>
                <option value="x86">x86</option>
                <option value="x64">x64</option>
                <option value="unknown">unknown</option>
            </select>
        </label>
        <label>Боты
            <select x-model="filters.bots">
                <option value="all">Все</option>
                <option value="humans">Только люди</option>
                <option value="bots">Только боты</option>
            </select>
        </label>
        <button type="submit" :disabled="loading">Применить</button>
        <button type="button" class="secondary" @click="resetFilters()">Сброс</button>
    </form>
    <template x-if="filterError">
        <div class="alert" style="margin-top:10px" x-text="filterError"></div>
    </template>
</section>

<section class="card">
    <h2>Графики</h2>
    <div class="charts">
        <div>
            <div class="muted">Запросы по дням (люди / боты)</div>
            <canvas x-ref="chartRequests"></canvas>
        </div>
        <div>
            <div class="muted">Доля топ-3 браузеров (%)</div>
            <canvas x-ref="chartBrowsers"></canvas>
        </div>
    </div>
</section>

<section class="card">
    <h2>Сводка по дням</h2>
    <div style="overflow:auto; max-height:520px">
        <table>
            <thead>
                <tr>
                    <th @click="sortBy('date')">Дата <span class="arrow" x-text="sortArrow('date')"></span></th>
                    <th @click="sortBy('requests')">Запросов <span class="arrow" x-text="sortArrow('requests')"></span></th>
                    <th @click="sortBy('top_url')">Самый популярный URL <span class="arrow" x-text="sortArrow('top_url')"></span></th>
                    <th @click="sortBy('top_browser')">Самый популярный браузер <span class="arrow" x-text="sortArrow('top_browser')"></span></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in tableRows" :key="row.date">
                    <tr>
                        <td x-text="row.date"></td>
                        <td x-text="row.requests"></td>
                        <td class="url" :title="row.top_url" x-text="row.top_url || '—'"></td>
                        <td x-text="row.top_browser || '—'"></td>
                    </tr>
                </template>
                <tr x-show="!loading && tableRows.length === 0">
                    <td colspan="4" class="muted" style="text-align:center; padding:24px">Нет данных за выбранный диапазон</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<script>
    let requestsChart = null;
    let browsersChart = null;

    function buildRequestsChart(canvas) {
        return new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    { label: 'Люди', data: [], borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.1)', tension: .25 },
                    { label: 'Боты', data: [], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.1)', tension: .25 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
    }

    function buildBrowsersChart(canvas) {
        return new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } },
            },
        });
    }

    function applyRequestsData(data) {
        if (!requestsChart) return;
        requestsChart.data.labels = data.labels;
        requestsChart.data.datasets[0].data = data.humans;
        requestsChart.data.datasets[1].data = data.bots;
        requestsChart.update();
    }

    function applyBrowsersData(data) {
        if (!browsersChart) return;
        const palette = ['#2563eb', '#10b981', '#ef4444'];
        browsersChart.data.labels = data.labels;
        browsersChart.data.datasets = (data.browsers || []).map((br, i) => ({
            label: br,
            data: data.series[br] || [],
            borderColor: palette[i % palette.length],
            backgroundColor: palette[i % palette.length] + '22',
            tension: .25,
        }));
        browsersChart.update();
    }

    function dashboard(initial) {
        const uploadUrl = '{{ route('imports.store') }}';

        return {
            filters: {
                date_from: initial.from,
                date_to: initial.to,
                os: '',
                arch: '',
                bots: 'all',
                sort: 'date',
                dir: 'asc',
            },
            tableRows: [],
            loading: false,
            filterError: null,

            uploading: false,
            uploadError: null,
            job: null,
            jobPoller: null,

            async init() {
                requestsChart = buildRequestsChart(this.$refs.chartRequests);
                browsersChart = buildBrowsersChart(this.$refs.chartBrowsers);
                await this.reload();
            },

            async upload() {
                this.uploadError = null;
                const fileInput = this.$refs.file;
                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    this.uploadError = 'Файл не выбран.';
                    return;
                }

                const file = fileInput.files[0];
                const fd = new FormData();
                fd.append('log_file', file);

                const csrfMeta = document.querySelector('meta[name=csrf-token]');
                const csrf = csrfMeta ? csrfMeta.content : '';

                this.uploading = true;
                try {
                    const res = await fetch(uploadUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: fd,
                    });

                    let data = null;
                    try { data = await res.json(); } catch (_) {}

                    if (!res.ok) {
                        this.uploadError = this.extractError(data) || ('Ошибка загрузки (HTTP ' + res.status + ')');
                        return;
                    }

                    this.job = {
                        id: data.job_id,
                        status: data.status,
                        duplicate: !!data.duplicate,
                        lines_processed: 0,
                        lines_invalid: 0,
                        error: null,
                    };
                    this.startPolling();
                } catch (e) {
                    this.uploadError = 'Сетевая ошибка: ' + (e && e.message ? e.message : e);
                } finally {
                    this.uploading = false;
                    fileInput.value = '';
                }
            },

            startPolling() {
                clearInterval(this.jobPoller);
                this.jobPoller = setInterval(() => this.pollProgress(), 1500);
            },

            async pollProgress() {
                if (!this.job) return;
                try {
                    const res = await fetch('/imports/' + this.job.id + '/progress', { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.job.status = data.status;
                    this.job.lines_processed = data.lines_processed;
                    this.job.lines_invalid = data.lines_invalid;
                    this.job.error = data.error;

                    if (data.finished) {
                        clearInterval(this.jobPoller);
                        if (data.status === 'done') {
                            await this.reload();
                        }
                    }
                } catch (_) {}
            },

            get progressPercent() {
                if (!this.job) return 0;
                if (this.job.status === 'done') return 100;
                return Math.min(95, Math.floor(Math.log10(Math.max(this.job.lines_processed, 1)) * 20));
            },

            resetFilters() {
                this.filters = {
                    date_from: initial.from,
                    date_to: initial.to,
                    os: '', arch: '', bots: 'all', sort: 'date', dir: 'asc',
                };
                this.reload();
            },

            sortBy(field) {
                if (this.filters.sort === field) {
                    this.filters.dir = this.filters.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.filters.sort = field;
                    this.filters.dir = 'asc';
                }
                this.reload();
            },

            sortArrow(field) {
                if (this.filters.sort !== field) return '↕';
                return this.filters.dir === 'asc' ? '▲' : '▼';
            },

            queryString() {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([k, v]) => {
                    if (v !== '' && v !== null && v !== undefined) params.append(k, v);
                });
                return params.toString();
            },

            async reload() {
                this.filterError = null;
                this.loading = true;
                try {
                    const qs = this.queryString();
                    const [reqRes, brRes, tblRes] = await Promise.all([
                        fetch('/api/stats/requests?' + qs, { headers: { 'Accept': 'application/json' } }),
                        fetch('/api/stats/browsers?' + qs, { headers: { 'Accept': 'application/json' } }),
                        fetch('/api/stats/table?' + qs,    { headers: { 'Accept': 'application/json' } }),
                    ]);
                    if (!reqRes.ok || !brRes.ok || !tblRes.ok) {
                        const errRes = !reqRes.ok ? reqRes : (!brRes.ok ? brRes : tblRes);
                        let data = null;
                        try { data = await errRes.json(); } catch (_) {}
                        this.filterError = this.extractError(data) || ('Ошибка загрузки данных (HTTP ' + errRes.status + ')');
                        return;
                    }
                    const [requests, browsers, table] = await Promise.all([reqRes.json(), brRes.json(), tblRes.json()]);
                    applyRequestsData(requests);
                    applyBrowsersData(browsers);
                    this.tableRows = table.rows;
                } catch (e) {
                    this.filterError = 'Сетевая ошибка: ' + (e && e.message ? e.message : e);
                } finally {
                    this.loading = false;
                }
            },

            extractError(payload) {
                if (!payload) return null;
                if (payload.message && !payload.errors) return payload.message;
                if (payload.errors) return Object.values(payload.errors).flat().join('\n');
                return null;
            },
        };
    }
</script>

</body>
</html>
