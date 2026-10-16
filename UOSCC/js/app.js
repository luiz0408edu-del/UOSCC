const apiUrl = '../php/index.php';

async function apiRequest(params, options = {}) {
    const query = new URLSearchParams(params).toString();
    const response = await fetch(`${apiUrl}?${query}`, {
        credentials: 'same-origin',
        headers: options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' },
        ...options,
    });
    const result = await response.json();
    if (!response.ok) {
        throw new Error(result.error || 'Não foi possível concluir a solicitação.');
    }
    return result;
}

window.apiRequest = apiRequest;

let currentUser = null;

const OSC_AREAS = [
    'Educação e formação cidadã',
    'Meio ambiente e sustentabilidade',
    'Saúde e bem-estar comunitário',
    'Assistência social e apoio às famílias',
    'Direitos humanos e inclusão social',
    'Cultura, esporte e lazer',
];

const escapeHTML = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[character]));

const money = (value) => Number(value || 0).toLocaleString('pt-BR', {
    style: 'currency', currency: 'BRL', maximumFractionDigits: 2,
});

const number = (value) => Number(value || 0).toLocaleString('pt-BR');

function notify(message) {
    let notice = document.querySelector('.api-notice');
    if (!notice) {
        notice = document.createElement('div');
        notice.className = 'api-notice';
        notice.setAttribute('role', 'status');
        document.body.append(notice);
    }
    notice.textContent = message;
    notice.hidden = false;
    clearTimeout(notice.timeout);
    notice.timeout = setTimeout(() => { notice.hidden = true; }, 4500);
}

function addAppStyles() {
    if (document.getElementById('app-styles')) return;
    const style = document.createElement('style');
    style.id = 'app-styles';
    style.textContent = `
        .api-notice { position: fixed; right: 20px; bottom: 20px; z-index: 20; max-width: min(420px, calc(100vw - 40px)); padding: 12px 16px; background: #212529; color: white; border-radius: 6px; box-shadow: 0 4px 16px #0002; }
        .api-notice[hidden] { display: none; }
        .row-actions { display: flex; gap: 8px; white-space: nowrap; }
        .row-actions button { border: 0; background: none; color: var(--primary); cursor: pointer; font: inherit; text-decoration: underline; }
        .row-actions button[data-action="delete"] { color: #b02a37; }
        .app-dialog { width: min(540px, calc(100% - 32px)); max-height: 90vh; margin: auto; padding: 24px; border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-color); }
        .app-dialog::backdrop { background: #17221dcc; }
        .app-dialog h2 { margin-bottom: 18px; font-size: 1.25rem; }
        .app-dialog form { display: grid; gap: 14px; }
        .app-dialog label { display: grid; gap: 5px; font-size: .9rem; font-weight: 600; }
        .app-dialog input, .app-dialog select, .app-dialog textarea { width: 100%; padding: 9px 10px; border: 1px solid var(--border-color); border-radius: 5px; font: inherit; }
        .app-dialog textarea { min-height: 78px; resize: vertical; }
        .app-dialog menu { display: flex; justify-content: flex-end; gap: 9px; margin-top: 8px; }
        .app-dialog .btn-secondary { padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 6px; background: white; cursor: pointer; }
        .evidence-item { padding: 18px; background: white; border: 1px solid var(--border-color); border-radius: 6px; }
        .evidence-item h3 { margin-bottom: 8px; }
        .evidence-item p { margin-top: 8px; color: #6c757d; }
        .report-section { margin-top: 24px; }
        .report-section h2 { margin-bottom: 8px; font-size: 1.05rem; }
        .report-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; margin: 18px 0; }
        .report-filters label { display: grid; gap: 5px; font-size: .85rem; font-weight: 600; }
        .report-filters input, .report-filters select { min-width: 150px; padding: 9px 10px; border: 1px solid var(--border-color); border-radius: 5px; font: inherit; }
        .report-note-form { display: grid; gap: 12px; max-width: 760px; margin: 18px 0; padding: 16px 0; border-top: 1px solid var(--border-color); }
        .report-note-form[hidden] { display: none; }
        .report-note-form label { display: grid; gap: 6px; font-weight: 600; }
        .report-note-form input, .report-note-form textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; font: inherit; }
        .report-note-form textarea { resize: vertical; }
        .report-note-form [role="status"] { color: var(--primary); }
        .public-lookup { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; margin: 20px 0; }
        .public-lookup label { display: grid; gap: 5px; font-weight: 600; }
        .public-lookup input { min-width: min(360px, 75vw); padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; }
        .account-form { display: grid; gap: 16px; max-width: 560px; }
        .account-form label { display: grid; gap: 6px; font-weight: 600; }
        .account-form input, .account-form select { padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; font: inherit; }
        .account-form [role="alert"] { color: #b02a37; }
        .empty-state { padding: 18px; color: #6c757d; }
        @media (max-width: 700px) { .app-dialog { padding: 18px; } .row-actions { flex-wrap: wrap; } }
        @media print { aside, header.topbar, .report-filters, #exportar-csv, #exportar-pdf { display: none !important; } body, .main-container, .content { display: block; height: auto; overflow: visible; padding: 0; background: white; } #relatorio-conteudo { border: 0 !important; padding: 0 !important; } table { break-inside: auto; } tr { break-inside: avoid; } }
    `;
    document.head.append(style);
}

const configs = {
    projects: {
        button: 'Novo projeto', body: '#tabela-projetos',
        fields: [
            { name: 'name', label: 'Nome do projeto', required: true },
            { name: 'organization', label: 'Área da OSC responsável', type: 'select', options: OSC_AREAS, required: true },
        ],
        headers: ['Ações', 'Projeto', 'OSC', 'Indicadores', 'Recebido'],
        row: (item) => [actions(item), escapeHTML(item.name), escapeHTML(item.organization), number(item.indicators), money(item.received)],
    },
    supporters: {
        button: 'Novo apoiador', body: '#tabela-apoiadores',
        fields: [
            { name: 'name', label: 'Nome', required: true },
            { name: 'type', label: 'Tipo', type: 'select', options: ['Empresa', 'Pessoa', 'Poder público'], required: true },
        ],
        headers: ['Ações', 'Nome', 'Tipo', 'Total apoiado', 'Consulta pública'],
        row: (item) => [actions(item), escapeHTML(item.name), escapeHTML(item.type), money(item.total), `<a href="publico.html?token=${encodeURIComponent(item.public_token)}">Abrir link</a>`],
    },
    resources: {
        button: 'Novo recurso', body: '#tabela-recursos',
        fields: [
            { name: 'date', label: 'Data', type: 'date', required: true },
            { name: 'project_id', label: 'Projeto', type: 'projects', required: true },
            { name: 'type', label: 'Tipo de recurso', type: 'select', options: ['Dinheiro', 'Material', 'Voluntariado'], required: true },
            { name: 'quantity', label: 'Quantidade (R$ para dinheiro, horas para voluntariado)', type: 'number', min: 0, step: 'any', required: true },
            { name: 'supporter_id', label: 'Apoiador', type: 'supporters', required: false },
            { name: 'usage', label: 'Como foi usado', type: 'textarea', required: false },
        ],
        headers: ['Ações', 'Data', 'Projeto', 'Tipo', 'Quantidade', 'Apoiador', 'Como foi usado'],
        row: (item) => [actions(item), escapeHTML(item.date), escapeHTML(item.project || '—'), escapeHTML(item.type), item.type === 'Dinheiro' ? money(item.quantity) : number(item.quantity), escapeHTML(item.supporter || '—'), escapeHTML(item.usage || '—')],
    },
    indicators: {
        button: 'Novo indicador', body: '#tabela-indicadores',
        fields: [
            { name: 'project_id', label: 'Projeto', type: 'projects', required: true },
            { name: 'name', label: 'Indicador', required: true },
            { name: 'date', label: 'Data da medição', type: 'date', required: true },
            { name: 'target', label: 'Meta planejada', type: 'number', min: 0, step: 'any', required: true },
            { name: 'achieved', label: 'Valor alcançado', type: 'number', min: 0, step: 'any', required: true },
        ],
        headers: ['Ações', 'Projeto', 'Indicador', 'Data', 'Meta', 'Alcançado', 'Progresso', 'Status'],
        row: (item) => [actions(item), escapeHTML(item.project || '—'), escapeHTML(item.name), escapeHTML(item.date), number(item.target), number(item.achieved), `${number(item.progress)}%`, Number(item.achieved) >= Number(item.target) ? 'Concluído' : 'Em andamento'],
    },
    evidences: {
        button: 'Nova evidência', body: '#grid-evidencias', cards: true,
        fields: [
            { name: 'project_id', label: 'Projeto', type: 'projects', required: true },
            { name: 'title', label: 'Título', required: true },
            { name: 'type', label: 'Tipo', type: 'select', options: ['Foto', 'Vídeo', 'Áudio', 'Texto', 'Documento', 'Outro'], required: true },
            { name: 'file', label: 'Arquivo (imagem, vídeo, áudio, texto ou PDF; até 25 MB)', type: 'file', required: true },
            { name: 'date', label: 'Data da evidência', type: 'date', required: true },
            { name: 'description', label: 'Descrição', type: 'textarea', required: false },
        ],
    },
};

function actions(item) {
    return `<div class="row-actions"><button type="button" data-action="edit" data-id="${Number(item.id)}">Editar</button><button type="button" data-action="delete" data-id="${Number(item.id)}">Excluir</button></div>`;
}

async function relatedOptions(type) {
    if (type !== 'projects' && type !== 'supporters') return [];
    return apiRequest({ entity: type });
}

async function renderField(field, value, isEditing = false) {
    const required = field.required && !(field.type === 'file' && isEditing) ? 'required' : '';
    if (field.type === 'select' || field.type === 'projects' || field.type === 'supporters') {
        const options = field.options
            ? field.options.map((option) => `<option value="${escapeHTML(option)}" ${value === option ? 'selected' : ''}>${escapeHTML(option)}</option>`).join('')
            : (await relatedOptions(field.type)).map((item) => `<option value="${item.id}" ${Number(value) === Number(item.id) ? 'selected' : ''}>${escapeHTML(item.name)}</option>`).join('');
        const optional = !field.required ? '<option value="">Não informado</option>' : '';
        return `<label>${escapeHTML(field.label)}<select name="${field.name}" ${required}>${optional}${options}</select></label>`;
    }
    if (field.type === 'file') {
        const accept = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,audio/mpeg,audio/mp4,audio/wav,audio/ogg,text/plain,application/pdf';
        return `<label>${escapeHTML(field.label)}<input type="file" name="file" accept="${accept}" ${required}></label>${value ? `<small>Arquivo atual: ${escapeHTML(value)}</small>` : ''}`;
    }
    if (field.type === 'textarea') {
        return `<label>${escapeHTML(field.label)}<textarea name="${field.name}" ${required}>${escapeHTML(value || '')}</textarea></label>`;
    }
    const attrs = [
        `type="${field.type || 'text'}"`, `name="${field.name}"`, `value="${escapeHTML(value ?? '')}"`,
        required, field.min !== undefined ? `min="${field.min}"` : '', field.step ? `step="${field.step}"` : '',
    ].filter(Boolean).join(' ');
    return `<label>${escapeHTML(field.label)}<input ${attrs}></label>`;
}

async function openEditor(entity, existing = null) {
    const config = configs[entity];
    const fields = config.fields.filter((field) => !(currentUser?.role === 'osc' && (field.type === 'supporters' || (entity === 'projects' && field.name === 'organization'))));
    const dialog = document.createElement('dialog');
    dialog.className = 'app-dialog';
    dialog.innerHTML = `<h2>${existing ? 'Editar' : 'Novo registro'}</h2><form>${(await Promise.all(fields.map((field) => renderField(field, existing?.[field.name], Boolean(existing))))).join('')}<p class="form-error" role="alert"></p><menu><button type="button" class="btn-secondary" data-cancel>Cancelar</button><button type="submit" class="btn">Salvar</button></menu></form>`;
    document.body.append(dialog);
    dialog.showModal();
    dialog.querySelector('[data-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    dialog.querySelector('form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = dialog.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const values = Object.fromEntries(new FormData(event.currentTarget));
            if (currentUser?.role === 'osc' && entity === 'projects') values.organization = currentUser.organization;
            if (entity === 'evidences') {
                const file = dialog.querySelector('input[name="file"]').files[0];
                if (file) {
                    const upload = new FormData();
                    upload.append('file', file);
                    const stored = await apiRequest({ action: 'upload' }, { method: 'POST', body: upload });
                    values.url = stored.url;
                } else if (existing?.url) {
                    values.url = existing.url;
                }
                delete values.file;
            }
            const method = existing ? 'PUT' : 'POST';
            await apiRequest({ entity, ...(existing ? { id: existing.id } : {}) }, {
                method,
                body: JSON.stringify(values),
            });
            dialog.close();
            await loadEntity(entity);
            if (entity === 'projects' || entity === 'supporters') await refreshRelatedViews();
        } catch (error) {
            dialog.querySelector('.form-error').textContent = error.message;
            submit.disabled = false;
        }
    });
}

function emptyRow(colspan) {
    return `<tr><td colspan="${colspan}" class="empty-state">Nenhum registro cadastrado.</td></tr>`;
}

async function loadEntity(entity) {
    const config = configs[entity];
    const items = await apiRequest({ entity });
    const container = document.querySelector(config.body);
    if (config.cards) {
        container.innerHTML = items.length ? items.map((item) => {
            const safeUrl = /^(https?:\/\/|\.\.\/uploads\/evidence\/)/i.test(item.url) ? item.url : '#';
            return `<article class="evidence-item"><div class="row-actions">${actions(item)}</div><h3>${escapeHTML(item.title)}</h3><span>${escapeHTML(item.project || '—')} · ${escapeHTML(item.type)} · ${escapeHTML(item.date)}</span><p>${escapeHTML(item.description || '')}</p><a href="${escapeHTML(safeUrl)}" target="_blank" rel="noopener">Abrir evidência</a></article>`;
        }).join('') : '<p class="empty-state">Nenhuma evidência cadastrada.</p>';
    } else {
        container.innerHTML = items.length ? items.map((item) => `<tr>${config.row(item).map((cell) => `<td>${cell}</td>`).join('')}</tr>`).join('') : emptyRow(config.headers.length);
    }
    container.dataset.entity = entity;
    container.dataset.items = JSON.stringify(items);
}

async function refreshRelatedViews() {
    for (const entity of ['projects', 'supporters', 'resources', 'indicators', 'evidences']) {
        if (document.querySelector(configs[entity].body)) await loadEntity(entity);
    }
    if (document.getElementById('card-dinheiro')) await loadDashboard();
}

function bindEntity(entity) {
    const config = configs[entity];
    document.querySelector(`.page-header .btn`)?.addEventListener('click', () => openEditor(entity).catch((error) => notify(error.message)));
    const container = document.querySelector(config.body);
    container.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const items = JSON.parse(container.dataset.items || '[]');
        const item = items.find((candidate) => Number(candidate.id) === Number(button.dataset.id));
        if (!item) return;
        if (button.dataset.action === 'edit') {
            openEditor(entity, item).catch((error) => notify(error.message));
            return;
        }
        if (!window.confirm('Excluir este registro? Esta ação não pode ser desfeita.')) return;
        try {
            await apiRequest({ entity, id: item.id }, { method: 'DELETE' });
            await loadEntity(entity);
            if (entity === 'projects' || entity === 'supporters') await refreshRelatedViews();
        } catch (error) {
            notify(error.message);
        }
    });
}

async function loadDashboard() {
    const data = await apiRequest({ action: 'dashboard' });
    document.getElementById('card-dinheiro').textContent = money(data.money);
    document.getElementById('card-voluntariado').textContent = `${number(data.volunteer)} h`;
    document.getElementById('card-pessoas').textContent = number(data.people);
    document.getElementById('card-metas').textContent = `${data.completedGoals} / ${data.totalGoals}`;
    document.getElementById('card-evidencias').textContent = number(data.evidences);
}

function reportTable(title, headings, rows) {
    return `<section class="report-section"><h2>${escapeHTML(title)}</h2><table><thead><tr>${headings.map((heading) => `<th>${escapeHTML(heading)}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.map((row) => `<tr>${row.map((cell) => `<td>${escapeHTML(cell)}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${headings.length}" class="empty-state">Nenhum registro.</td></tr>`}</tbody></table></section>`;
}

async function loadReport() {
    const form = document.getElementById('report-filters');
    const noteForm = document.getElementById('report-note-form');
    const filters = Object.fromEntries(new FormData(form));
    const organizationSelect = form.elements.organization;
    if (currentUser.role === 'admin') {
        const organizations = await apiRequest({ action: 'organizations' });
        const selectedOrganization = organizationSelect.value;
        organizationSelect.innerHTML = '<option value="">Todas as OSCs</option>' + organizations.map((organization) => `<option value="${escapeHTML(organization)}">${escapeHTML(organization)}</option>`).join('');
        organizationSelect.value = selectedOrganization;
    } else {
        organizationSelect.closest('label').hidden = true;
    }
    const report = await apiRequest({ action: 'report', ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) });
    window.currentReport = report;
    noteForm.hidden = currentUser.role !== 'admin';
    if (currentUser.role === 'admin') {
        noteForm.elements.title.value = report.reportNote?.title || '';
        noteForm.elements.narrative.value = report.reportNote?.narrative || '';
    }
    const summary = report.summary;
    const container = document.getElementById('relatorio-conteudo');
    const reportNoteHTML = report.reportNote
        ? `<section class="report-section"><h2>${escapeHTML(report.reportNote.title)}</h2><p style="white-space: pre-wrap">${escapeHTML(report.reportNote.narrative)}</p></section>`
        : '';
    container.innerHTML = `<div class="card-grid"><div class="card"><h3>Dinheiro recebido</h3><div class="value">${money(summary.money)}</div></div><div class="card"><h3>Horas de voluntariado</h3><div class="value">${number(summary.volunteer)} h</div></div><div class="card"><h3>Pessoas atendidas</h3><div class="value">${number(summary.people)}</div></div><div class="card"><h3>Metas concluídas</h3><div class="value">${summary.completedGoals} / ${summary.totalGoals}</div></div></div>`
        + reportNoteHTML
        + reportTable('Projetos', ['Projeto', 'OSC', 'Indicadores', 'Recebido'], report.projects.map((item) => [item.name, item.organization, number(item.indicators), money(item.received)]))
        + reportTable('Recursos', ['Data', 'Projeto', 'Tipo', 'Quantidade', 'Apoiador', 'Uso'], report.resources.map((item) => [item.date, item.project || '—', item.type, item.type === 'Dinheiro' ? money(item.quantity) : number(item.quantity), item.supporter || '—', item.usage || '—']))
        + reportTable('Indicadores', ['Data', 'Projeto', 'Indicador', 'Meta', 'Alcançado'], report.indicators.map((item) => [item.date, item.project || '—', item.name, number(item.target), number(item.achieved)]))
        + reportTable('Evidências', ['Data', 'Projeto', 'Título', 'Tipo'], report.evidences.map((item) => [item.date, item.project || '—', item.title, item.type]));
}

async function loadPublicPage() {
    const form = document.getElementById('consulta-apoiador');
    const tokenField = form.elements.token;
    const result = document.getElementById('resultado-apoiador');
    const query = new URLSearchParams(window.location.search).get('token') || '';
    tokenField.value = query;
    const data = await apiRequest({ entity: 'public' });
    const container = document.getElementById('conteudo-publico');
    container.innerHTML = `<div class="card-grid"><div class="card"><h3>Dinheiro recebido</h3><div class="value">${money(data.summary.money)}</div></div><div class="card"><h3>Horas de voluntariado</h3><div class="value">${number(data.summary.volunteer)} h</div></div><div class="card"><h3>Pessoas atendidas</h3><div class="value">${number(data.summary.people)}</div></div><div class="card"><h3>Evidências publicadas</h3><div class="value">${number(data.summary.evidences)}</div></div></div>`
        + reportTable('Projetos apoiados', ['Projeto', 'OSC', 'Indicadores', 'Recebido'], data.projects.map((item) => [item.name, item.organization, number(item.indicators), money(item.received)]))
        + reportTable('Destinação dos recursos', ['Data', 'Projeto', 'Tipo', 'Quantidade', 'Uso'], data.resources.map((item) => [item.date, item.project || '—', item.type, item.type === 'Dinheiro' ? money(item.quantity) : number(item.quantity), item.usage || '—']))
        + `<section class="report-section"><h2>Evidências publicadas</h2>${data.evidences.length ? data.evidences.map((item) => {
            const safeUrl = /^(https?:\/\/|\.\.\/uploads\/evidence\/)/i.test(item.url) ? item.url : '#';
            return `<article class="evidence-item"><h3>${escapeHTML(item.title)}</h3><span>${escapeHTML(item.date)} · ${escapeHTML(item.project)} · ${escapeHTML(item.type)}</span><p>${escapeHTML(item.description || '')}</p><a href="${escapeHTML(safeUrl)}" target="_blank" rel="noopener">Abrir evidência</a></article>`;
        }).join('') : '<p class="empty-state">Nenhuma evidência publicada.</p>'}</section>`;

    async function lookupSupporter(token) {
        result.replaceChildren();
        if (!token) return;
        try {
            const personal = await apiRequest({ entity: 'public', token });
            if (!personal.donation) {
                result.textContent = 'Nenhuma contribuição encontrada para esse código.';
                return;
            }
            result.innerHTML = `<h2>Contribuições de ${escapeHTML(personal.donation.name)}</h2>${reportTable('Destino das contribuições', ['Data', 'Projeto', 'Tipo', 'Quantidade', 'Uso'], personal.donation.resources.map((item) => [item.date, item.project || '—', item.type, item.type === 'Dinheiro' ? money(item.quantity) : number(item.quantity), item.usage || '—']))}`;
        } catch (error) {
            result.textContent = error.message;
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        lookupSupporter(tokenField.value.trim());
    });
    if (query) await lookupSupporter(query);
}

async function loadSupporterPage() {
    const data = await apiRequest({ action: 'supporter-dashboard' });
    const container = document.getElementById('acompanhamento-apoiador');
    container.innerHTML = `<p>${number(data.total)} contribuições registradas</p>${reportTable('Minhas contribuições e voluntariado', ['Data', 'Projeto', 'Tipo', 'Quantidade', 'Destinação'], data.resources.map((item) => [item.date, item.project || 'Aguardando vínculo', item.type, item.type === 'Dinheiro' ? money(item.quantity) : number(item.quantity), item.usage || 'Sem descrição']))}`;
}

function setRoleNavigation(user) {
    const permissions = user.role === 'admin'
        ? ['dashboard', 'financeiro', 'metas', 'evidencias', 'projetos', 'apoiadores', 'relatorios', 'publico', 'contas']
        : user.role === 'osc'
            ? ['dashboard', 'financeiro', 'metas', 'evidencias', 'projetos', 'relatorios', 'publico']
            : ['apoiador', 'publico'];
    document.querySelectorAll('.sidebar-menu a[data-role-page]').forEach((link) => {
        link.hidden = !permissions.includes(link.dataset.rolePage);
    });
    document.querySelectorAll('.sidebar-section-title').forEach((label) => {
        let item = label.nextElementSibling;
        let visibleLink = false;
        while (item && !item.classList.contains('sidebar-section-title')) {
            visibleLink ||= Boolean(item.querySelector('a:not([hidden])'));
            item = item.nextElementSibling;
        }
        label.hidden = !visibleLink;
    });
}

function bindOSCAccountForm() {
    for (const [formId, action, success] of [
        ['form-conta-osc', 'create-osc-account', 'Acesso da OSC criado.'],
        ['form-conta-admin', 'create-admin-account', 'Acesso de administrador criado.'],
    ]) {
        const form = document.getElementById(formId);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('[type="submit"]');
            button.disabled = true;
            try {
                await apiRequest({ action }, { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
                form.reset();
                form.querySelector('[role="alert"]').textContent = '';
                notify(success);
            } catch (error) {
                form.querySelector('[role="alert"]').textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    }
}

function exportReportCsv() {
    const report = window.currentReport;
    if (!report) return;
    const rows = [['Seção', 'Data', 'Projeto', 'Descrição', 'Tipo', 'Meta', 'Realizado', 'Uso']];
    if (report.reportNote) rows.push(['Relatório', `${report.filters.from} a ${report.filters.to}`, report.filters.organization || 'Todas as OSCs', report.reportNote.title, '', '', '', report.reportNote.narrative]);
    report.resources.forEach((item) => rows.push(['Recurso', item.date, item.project, item.type, item.type, '', item.quantity, item.usage]));
    report.indicators.forEach((item) => rows.push(['Indicador', item.date, item.project, item.name, '', item.target, item.achieved, '']));
    report.evidences.forEach((item) => rows.push(['Evidência', item.date, item.project, item.title, item.type, '', '', item.description]));
    const csv = rows.map((row) => row.map((cell) => {
        let value = String(cell ?? '');
        if (/^[\s]*[=+\-@]/.test(value)) value = `'${value}`;
        return `"${value.replace(/"/g, '""')}"`;
    }).join(';')).join('\r\n');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob(['\ufeff', csv], { type: 'text/csv;charset=utf-8' }));
    link.download = 'relatorio-uoscc.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

async function initialize() {
    addAppStyles();
    const page = window.location.pathname.split('/').pop();
    const isPublic = page === 'publico.html';
    const isLogin = page === 'login.html';

    if (isLogin) {
        const form = document.getElementById('loginForm');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const response = await apiRequest({ action: 'login' }, {
                    method: 'POST',
                    body: JSON.stringify({
                        email: document.getElementById('email').value,
                        password: document.getElementById('password').value,
                    }),
                });
                window.location.href = response.user.role === 'supporter' ? 'apoiador.html' : 'dashboard.html';
            } catch (error) {
                notify(error.message);
                button.disabled = false;
            }
        });
        const signupForm = document.getElementById('signupForm');
        signupForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = signupForm.querySelector('[type="submit"]');
            button.disabled = true;
            try {
                await apiRequest({ action: 'register-supporter' }, { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(signupForm))) });
                notify('Conta criada. Entre com seu e-mail e senha.');
                signupForm.reset();
            } catch (error) {
                signupForm.querySelector('[role="alert"]').textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
        try {
            const current = await apiRequest({ action: 'me' });
            if (current.user) window.location.href = current.user.role === 'supporter' ? 'apoiador.html' : 'dashboard.html';
        } catch (error) {
            notify(error.message);
        }
        return;
    }

    if (!isPublic) {
        const current = await apiRequest({ action: 'me' });
        if (!current.user) {
            window.location.href = 'login.html';
            return;
        }
        currentUser = current.user;
        if (currentUser.role === 'supporter' && page !== 'apoiador.html') {
            window.location.href = 'apoiador.html';
            return;
        }
        const oscPages = ['dashboard.html', 'financeiro.html', 'metas.html', 'evidencias.html', 'projetos.html', 'relatorios.html'];
        if (currentUser.role === 'osc' && !oscPages.includes(page)) {
            window.location.href = 'dashboard.html';
            return;
        }
        if (currentUser.role !== 'supporter' && page === 'apoiador.html') {
            window.location.href = 'dashboard.html';
            return;
        }
        setRoleNavigation(currentUser);
    }

    if (isPublic) return loadPublicPage();
    if (page === 'apoiador.html') return loadSupporterPage();
    if (page === 'dashboard.html') return loadDashboard();
    if (page === 'contas.html') {
        if (currentUser.role !== 'admin') {
            window.location.href = 'dashboard.html';
            return;
        }
        return bindOSCAccountForm();
    }
    if (page === 'relatorios.html') {
        document.getElementById('report-filters').addEventListener('submit', (event) => {
            event.preventDefault();
            loadReport().catch((error) => notify(error.message));
        });
        document.getElementById('exportar-csv').addEventListener('click', exportReportCsv);
        document.getElementById('exportar-pdf').addEventListener('click', () => window.print());
        document.getElementById('report-note-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const noteForm = event.currentTarget;
            const filters = Object.fromEntries(new FormData(document.getElementById('report-filters')));
            const payload = { ...filters, ...Object.fromEntries(new FormData(noteForm)) };
            const status = noteForm.querySelector('[role="status"]');
            const button = noteForm.querySelector('[type="submit"]');
            button.disabled = true;
            status.textContent = '';
            try {
                await apiRequest({ action: 'save-report-note' }, { method: 'POST', body: JSON.stringify(payload) });
                await loadReport();
                document.getElementById('report-note-form').querySelector('[role="status"]').textContent = 'Texto do relatório salvo.';
            } catch (error) {
                status.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
        return loadReport();
    }

    const entity = Object.keys(configs).find((key) => document.querySelector(configs[key].body));
    if (entity) {
        bindEntity(entity);
        await loadEntity(entity);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initialize().catch((error) => notify(error.message));
});