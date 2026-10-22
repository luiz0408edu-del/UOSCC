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
let currentPreferences = {};

const navigationGroups = [
    ['menu', 'Menu', [['apoiador', 'Meu apoio'], ['dashboard', 'Painel geral']]],
    ['gestao', 'Gestão', [
        ['projetos', 'Projetos'], ['apoiadores', 'Apoiadores'], ['financeiro', 'Recursos'],
        ['metas', 'Indicadores'], ['evidencias', 'Evidências'], ['relatorios', 'Relatórios'],
    ]],
    ['contas', 'Gerenciamento de contas', [
        ['publico', 'Página pública'], ['contas', 'Acessos das OSCs'], ['conta', 'Minha conta'], ['configuracoes', 'Configurações'],
    ]],
];
const dashboardItems = [
    ['dinheiro', 'Dinheiro recebido'], ['voluntariado', 'Horas de voluntariado'],
    ['pessoas', 'Pessoas atendidas'], ['metas', 'Metas concluídas'], ['evidencias', 'Evidências registradas'],
];

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
        .app-dialog { width: min(540px, calc(100% - 32px)); max-height: 90vh; margin: auto; padding: 24px; border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-color); background: var(--surface-color); }
        .app-dialog::backdrop { background: #17221dcc; }
        .app-dialog h2 { margin-bottom: 18px; font-size: 1.25rem; }
        .app-dialog form { display: grid; gap: 14px; }
        .app-dialog label { display: grid; gap: 5px; font-size: .9rem; font-weight: 600; }
        .app-dialog input, .app-dialog select, .app-dialog textarea { width: 100%; padding: 9px 10px; border: 1px solid var(--border-color); border-radius: 5px; font: inherit; }
        .app-dialog textarea { min-height: 78px; resize: vertical; }
        .app-dialog menu { display: flex; justify-content: flex-end; gap: 9px; margin-top: 8px; }
        .app-dialog .btn-secondary { padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-color); background: var(--surface-color); cursor: pointer; }
        .evidence-item { padding: 18px; background: var(--surface-color); border: 1px solid var(--border-color); border-radius: 6px; }
        .evidence-item h3 { margin-bottom: 8px; }
        .evidence-item p { margin-top: 8px; color: var(--muted-text); }
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
        .theme-toggle { padding: 6px 12px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--surface-color); color: var(--text-color); cursor: pointer; }
        .empty-state { padding: 18px; color: var(--muted-text); }
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
            { name: 'accepted_resources', label: 'Tipos de recurso aceitos', type: 'checkboxes', options: ['Dinheiro', 'Material', 'Voluntariado'], required: true },
        ],
        headers: ['Ações', 'Projeto', 'OSC', 'Recursos aceitos', 'Indicadores', 'Recebido'],
        row: (item) => [actions(item), escapeHTML(item.name), escapeHTML(item.organization), escapeHTML((item.accepted_resources || []).join(', ')), number(item.indicators), money(item.received)],
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
            { name: 'quantity', label: 'Quantidade (R$ para dinheiro, horas ou unidades)', type: 'number', min: 0, step: 'any', required: true },
            { name: 'material_name', label: 'Material', required: false },
            { name: 'unit', label: 'Unidade', type: 'select', options: ['kg', 'g', 'litro', 'ml', 'unidade', 'peça', 'caixa', 'pacote'], required: false },
            { name: 'supporter_id', label: 'Apoiador', type: 'supporters', required: false },
            { name: 'indicator_id', label: 'Vincular ao indicador (opcional)', type: 'indicators', required: false },
            { name: 'usage', label: 'Como foi usado', type: 'textarea', required: false },
        ],
        headers: ['Ações', 'Data', 'Projeto', 'Tipo', 'Quantidade', 'Apoiador', 'Indicador', 'Como foi usado'],
        row: (item) => [actions(item), escapeHTML(item.date), escapeHTML(item.project || '—'), escapeHTML(item.type), item.type === 'Dinheiro' ? money(item.quantity) : item.type === 'Material' ? `${number(item.quantity)} ${escapeHTML(item.unit || 'Unidade não informada')} · ${escapeHTML(item.material_name || 'Material sem descrição')}` : `${number(item.quantity)} h`, escapeHTML(item.supporter || '—'), escapeHTML(item.indicator || '—'), escapeHTML(item.usage || '—')],
    },
    indicators: {
        button: 'Novo indicador', body: '#tabela-indicadores',
        fields: [
            { name: 'project_id', label: 'Projeto', type: 'projects', required: true },
            { name: 'name', label: 'Indicador', required: true },
            { name: 'date', label: 'Data da medição', type: 'date', required: true },
            { name: 'target', label: 'Meta planejada', type: 'number', min: 0, step: 'any', required: true },
            { name: 'achieved', source: 'manualAchieved', label: 'Alcançado manualmente (antes dos recursos vinculados)', type: 'number', min: 0, step: 'any', required: true },
            { name: 'people_when_met', label: 'Pessoas beneficiadas ao atingir a meta', type: 'number', min: 0, step: 1, required: true },
        ],
        headers: ['Ações', 'Projeto', 'Indicador', 'Data', 'Meta', 'Alcançado', 'Pessoas previstas', 'Progresso', 'Status'],
        row: (item) => [actions(item), escapeHTML(item.project || '—'), escapeHTML(item.name), escapeHTML(item.date), number(item.target), number(item.achieved), number(item.people_when_met), `${number(item.progress)}%`, Number(item.achieved) >= Number(item.target) ? 'Concluído' : 'Em andamento'],
    },
    people_served: {
        button: 'Registrar atendimento', body: '#tabela-pessoas',
        fields: [
            { name: 'project_id', label: 'Projeto', type: 'projects', required: true },
            { name: 'date', label: 'Data', type: 'date', required: true },
            { name: 'quantity', label: 'Pessoas atendidas', type: 'number', min: 1, step: 1, required: true },
            { name: 'description', label: 'Observação (até 500 caracteres)', type: 'textarea', required: false, maxLength: 500 },
        ],
        headers: ['Ações', 'Data', 'Projeto', 'Pessoas', 'Observação'],
        row: (item) => [`${actions(item)} <button type="button" data-action="increment" data-id="${Number(item.id)}">Aumentar</button>`, escapeHTML(item.date), escapeHTML(item.project), number(item.quantity), escapeHTML(item.description || '—')],
    },
    evidences: {
        button: 'Nova evidência', body: '#grid-evidencias', cards: true,
        fields: [
            { name: 'project_id', label: 'Projeto', type: 'projects', required: true },
            { name: 'title', label: 'Título', required: true },
            { name: 'type', label: 'Tipo', type: 'select', options: ['Foto', 'Vídeo', 'Áudio', 'Texto', 'Documento', 'Outro'], required: true },
            { name: 'file', label: 'Arquivo (imagem, vídeo, áudio, texto ou PDF; até 100 MB)', type: 'file', required: true },
            { name: 'date', label: 'Data da evidência', type: 'date', required: true },
            { name: 'description', label: 'Descrição', type: 'textarea', required: false },
        ],
    },
};

function actions(item) {
    return `<div class="row-actions"><button type="button" data-action="edit" data-id="${Number(item.id)}">Editar</button><button type="button" data-action="delete" data-id="${Number(item.id)}">Excluir</button></div>`;
}

async function relatedOptions(type, projectId = null) {
    if (!['projects', 'supporters', 'indicators'].includes(type)) return [];
    return apiRequest({ entity: type, ...(type === 'indicators' && projectId ? { project_id: projectId } : {}) });
}

async function renderField(field, value, isEditing = false, projectId = null) {
    const required = field.required && !(field.type === 'file' && isEditing) ? 'required' : '';
    if (field.type === 'checkboxes') {
        const selected = Array.isArray(value) ? value : field.options;
        return `<fieldset><legend>${escapeHTML(field.label)}</legend>${field.options.map((option) => `<label><input type="checkbox" name="${field.name}" value="${escapeHTML(option)}" ${selected.includes(option) ? 'checked' : ''}> ${escapeHTML(option)}</label>`).join('')}</fieldset>`;
    }
    if (field.type === 'select' || field.type === 'projects' || field.type === 'supporters') {
        const options = field.options
            ? field.options.map((option) => `<option value="${escapeHTML(option)}" ${value === option ? 'selected' : ''}>${escapeHTML(option)}</option>`).join('')
            : (await relatedOptions(field.type, projectId)).map((item) => {
                const label = field.type === 'indicators' ? `${item.project} · ${item.name}` : item.name;
                return `<option value="${item.id}" ${Number(value) === Number(item.id) ? 'selected' : ''}>${escapeHTML(label)}</option>`;
            }).join('');
        const optional = !field.required ? '<option value="">Não informado</option>' : '';
        return `<label>${escapeHTML(field.label)}<select name="${field.name}" ${required}>${optional}${options}</select></label>`;
    }
    if (field.type === 'file') {
        const accept = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,audio/mpeg,audio/mp4,audio/wav,audio/ogg,text/plain,application/pdf';
        return `<label>${escapeHTML(field.label)}<input type="file" name="file" accept="${accept}" ${required}></label>${value ? `<small>Arquivo atual: ${escapeHTML(value)}</small>` : ''}`;
    }
    if (field.type === 'textarea') {
        return `<label>${escapeHTML(field.label)}<textarea name="${field.name}" ${required} ${field.maxLength ? `maxlength="${field.maxLength}"` : ''}>${escapeHTML(value || '')}</textarea></label>`;
    }
    const attrs = [
        `type="${field.type || 'text'}"`, `name="${field.name}"`, `value="${escapeHTML(value ?? '')}"`,
        required, field.min !== undefined ? `min="${field.min}"` : '', field.step ? `step="${field.step}"` : '',
    ].filter(Boolean).join(' ');
    return `<label>${escapeHTML(field.label)}<input ${attrs}></label>`;
}

async function openEditor(entity, existing = null) {
    const config = configs[entity];
    const fields = config.fields.filter((field) => !(currentUser?.role === 'osc' && (field.type === 'supporters' || (entity === 'projects' && ['organization', 'accepted_resources'].includes(field.name)))));
    const dialog = document.createElement('dialog');
    dialog.className = 'app-dialog';
    dialog.innerHTML = `<h2>${existing ? 'Editar' : 'Novo registro'}</h2><form>${(await Promise.all(fields.map((field) => renderField(field, existing?.[field.source || field.name], Boolean(existing), existing?.project_id)))).join('')}<p class="form-error" role="alert"></p><menu><button type="button" class="btn-secondary" data-cancel>Cancelar</button><button type="submit" class="btn">Salvar</button></menu></form>`;
    document.body.append(dialog);
    dialog.showModal();
    dialog.querySelector('[data-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    const projectSelect = dialog.querySelector('select[name="project_id"]');
    const indicatorSelect = dialog.querySelector('select[name="indicator_id"]');
    const typeSelect = dialog.querySelector('select[name="type"]');
    const materialNameField = dialog.querySelector('[name="material_name"]')?.closest('label');
    const unitField = dialog.querySelector('[name="unit"]')?.closest('label');
    const updateMaterialFields = () => {
        if (materialNameField) materialNameField.hidden = typeSelect?.value !== 'Material';
        if (unitField) unitField.hidden = typeSelect?.value !== 'Material';
        const quantityLabel = dialog.querySelector('[name="quantity"]')?.closest('label');
        if (quantityLabel && typeSelect) {
            quantityLabel.firstChild.textContent = typeSelect.value === 'Dinheiro'
                ? 'Quantidade (R$)'
                : typeSelect.value === 'Voluntariado' ? 'Quantidade (horas)' : 'Quantidade';
        }
    };
    typeSelect?.addEventListener('change', updateMaterialFields);
    updateMaterialFields();
    if (projectSelect && indicatorSelect) {
        projectSelect.addEventListener('change', async () => {
            const indicators = await relatedOptions('indicators', projectSelect.value);
            indicatorSelect.innerHTML = '<option value="">Não informado</option>' + indicators.map((item) => `<option value="${item.id}">${escapeHTML(item.name)}</option>`).join('');
        });
    }
    dialog.querySelector('form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = dialog.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const formData = new FormData(event.currentTarget);
            const values = Object.fromEntries(formData);
            if (entity === 'projects') values.accepted_resources = formData.getAll('accepted_resources');
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
            if (entity === 'people_served' && document.getElementById('report-filters')) await loadReport();
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
        if (button.dataset.action === 'increment' && entity === 'people_served') {
            openPeopleServedIncrement(item).catch((error) => notify(error.message));
            return;
        }
        if (button.dataset.action === 'edit') {
            openEditor(entity, item).catch((error) => notify(error.message));
            return;
        }
        if (!window.confirm('Excluir este registro? Esta ação não pode ser desfeita.')) return;
        try {
            await apiRequest({ entity, id: item.id }, { method: 'DELETE' });
            await loadEntity(entity);
            if (entity === 'projects' || entity === 'supporters') await refreshRelatedViews();
            if (entity === 'people_served' && document.getElementById('report-filters')) await loadReport();
        } catch (error) {
            notify(error.message);
        }
    });
}

async function openPeopleServedIncrement(item) {
    const dialog = document.createElement('dialog');
    dialog.className = 'app-dialog';
    dialog.innerHTML = `<h2>Aumentar pessoas atendidas</h2><p>${escapeHTML(item.project)} · ${escapeHTML(item.date)} · ${number(item.quantity)} já registradas</p><form><label>Quantidade a acrescentar<input name="increment" type="number" min="1" step="1" required></label><p class="form-error" role="alert"></p><menu><button type="button" class="btn-secondary" data-cancel>Cancelar</button><button type="submit" class="btn">Acrescentar</button></menu></form>`;
    document.body.append(dialog);
    dialog.showModal();
    dialog.querySelector('[data-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    dialog.querySelector('form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = dialog.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const increment = Number(new FormData(event.currentTarget).get('increment'));
            await apiRequest({ action: 'increment-people-served' }, {
                method: 'POST',
                body: JSON.stringify({ id: item.id, increment }),
            });
            dialog.close();
            await loadEntity('people_served');
            await loadReport();
            notify(`${number(increment)} pessoa(s) acrescentada(s) ao registro.`);
        } catch (error) {
            dialog.querySelector('.form-error').textContent = error.message;
            submit.disabled = false;
        }
    });
}

async function loadDashboard() {
    const form = document.getElementById('dashboard-filters');
    if (form && !form.dataset.bound) {
        form.dataset.bound = 'true';
        const organizationSelect = form.elements.organization;
        if (currentUser.role === 'admin') {
            const organizations = await apiRequest({ action: 'organizations' });
            organizationSelect.innerHTML = '<option value="">Todas as OSCs</option>' + organizations.map((organization) => `<option value="${escapeHTML(organization)}">${escapeHTML(organization)}</option>`).join('');
        } else {
            document.getElementById('dashboard-organization-filter').hidden = true;
        }
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            loadDashboard().catch((error) => notify(error.message));
        });
    }
    const filters = form ? Object.fromEntries([...new FormData(form)].filter(([, value]) => value)) : {};
    const data = await apiRequest({ action: 'dashboard', ...filters });
    applyDashboardPreferences();
    document.getElementById('card-dinheiro').textContent = money(data.money);
    document.getElementById('card-voluntariado').textContent = `${number(data.volunteer)} h`;
    document.getElementById('card-pessoas').textContent = number(data.people);
    document.getElementById('card-metas').textContent = `${data.completedGoals} / ${data.totalGoals}`;
    document.getElementById('card-evidencias').textContent = number(data.evidences);
    document.getElementById('dashboard-materials').innerHTML = reportTable(
        'Materiais recebidos por projeto',
        ['Projeto', 'OSC', 'Material', 'Quantidade'],
        (data.materials || []).map((item) => [item.project, item.organization, item.material_name, `${number(item.quantity)} ${item.unit}`]),
    );
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
        + reportTable('Recursos', ['Data', 'Projeto', 'Tipo', 'Quantidade', 'Apoiador', 'Uso'], report.resources.map((item) => [item.date, item.project || '—', item.type, item.type === 'Dinheiro' ? money(item.quantity) : item.type === 'Material' ? `${number(item.quantity)} ${item.unit || 'Unidade não informada'} · ${item.material_name || 'Material sem descrição'}` : `${number(item.quantity)} h`, item.supporter || '—', item.usage || '—']))
        + reportTable('Indicadores', ['Data', 'Projeto', 'Indicador', 'Meta', 'Alcançado'], report.indicators.map((item) => [item.date, item.project || '—', item.name, number(item.target), number(item.achieved)]))
        + reportTable('Pessoas atendidas', ['Data', 'Projeto', 'Quantidade', 'Observação'], report.peopleServed.map((item) => [item.date, item.project, number(item.quantity), item.description || '—']))
        + reportTable('Evidências', ['Data', 'Projeto', 'Título', 'Tipo'], report.evidences.map((item) => [item.date, item.project || '—', item.title, item.type]));
}

async function loadPublicPage() {
    const accountLink = document.getElementById('link-conta');
    const session = await apiRequest({ action: 'me' });
    if (session.user) {
        currentPreferences = await apiRequest({ action: 'preferences' });
        applyThemePreference();
        accountLink.href = session.user.role === 'supporter' ? 'apoiador.html' : 'dashboard.html';
        accountLink.textContent = session.user.role === 'supporter' ? 'Meu apoio' : 'Painel geral';
    }
    const form = document.getElementById('consulta-apoiador');
    const tokenField = form.elements.token;
    const result = document.getElementById('resultado-apoiador');
    const query = new URLSearchParams(window.location.search).get('token') || '';
    tokenField.value = query;
    const container = document.getElementById('conteudo-publico');
    const filtersForm = document.getElementById('public-filters');
    const filterOrganizations = filtersForm.elements.organization;
    const initialFilters = new URLSearchParams(window.location.search);
    const renderPublicData = async () => {
        const filters = Object.fromEntries([...new FormData(filtersForm)].filter(([, value]) => value));
        if (!filters.organization && initialFilters.get('organization')) filters.organization = initialFilters.get('organization');
        const data = await apiRequest({ entity: 'public', ...filters });
        const selectedOrganization = filters.organization || '';
        filterOrganizations.innerHTML = '<option value="">Todas as OSCs</option>' + data.organizations.map((organization) => `<option value="${escapeHTML(organization)}">${escapeHTML(organization)}</option>`).join('');
        filterOrganizations.value = selectedOrganization;
        container.innerHTML = `<div class="card-grid"><div class="card"><h3>Dinheiro recebido</h3><div class="value">${money(data.summary.money)}</div></div><div class="card"><h3>Horas de voluntariado</h3><div class="value">${number(data.summary.volunteer)} h</div></div><div class="card"><h3>Pessoas atendidas</h3><div class="value">${number(data.summary.people)}</div></div><div class="card"><h3>Evidências publicadas</h3><div class="value">${number(data.summary.evidences)}</div></div></div>`
            + reportTable('Projetos apoiados', ['Projeto', 'OSC', 'Indicadores', 'Recebido'], data.projects.map((item) => [item.name, item.organization, number(item.indicators), money(item.received)]))
            + reportTable('Materiais recebidos', ['Projeto', 'OSC', 'Material', 'Quantidade'], (data.summary.materials || []).map((item) => [item.project, item.organization, item.material_name, `${number(item.quantity)} ${item.unit}`]))
            + reportTable('Destinação dos recursos', ['Data', 'Projeto', 'OSC', 'Tipo', 'Quantidade', 'Apoiador', 'Uso'], data.resources.map((item) => [item.date, item.project || '—', item.organization, item.type, item.type === 'Dinheiro' ? money(item.quantity) : item.type === 'Material' ? `${number(item.quantity)} ${item.unit || 'Unidade não informada'} · ${item.material_name || 'Material sem descrição'}` : `${number(item.quantity)} h`, item.supporter, item.usage || '—']))
            + `<section class="report-section"><h2>Evidências publicadas</h2>${data.evidences.length ? data.evidences.map((item) => {
                const safeUrl = /^(https?:\/\/|\.\.\/uploads\/evidence\/)/i.test(item.url) ? item.url : '#';
                return `<article class="evidence-item"><h3>${escapeHTML(item.title)}</h3><span>${escapeHTML(item.date)} · ${escapeHTML(item.project)} · ${escapeHTML(item.type)}</span><p>${escapeHTML(item.description || '')}</p><a href="${escapeHTML(safeUrl)}" target="_blank" rel="noopener">Abrir evidência</a></article>`;
            }).join('') : '<p class="empty-state">Nenhuma evidência publicada.</p>'}</section>`
            + `<section class="report-section"><h2>Maiores apoiadores · ranking geral</h2><p>Classificação acumulada de todas as contribuições. Os materiais são apresentados na transparência, mas não entram nos rankings.</p><div class="leaderboard-grid">${reportTable('Maiores doadores de dinheiro', ['Posição', 'Apoiador', 'Total'], data.rankings.money.top.map((item) => [item.position, item.supporter, money(item.total)]))}${reportTable('Maiores apoiadores voluntários', ['Posição', 'Apoiador', 'Horas'], data.rankings.volunteer.top.map((item) => [item.position, item.supporter, `${number(item.total)} h`]))}</div></section>`;
    };
    filtersForm.elements.organization.value = initialFilters.get('organization') || '';
    filtersForm.elements.from.value = initialFilters.get('from') || '';
    filtersForm.elements.to.value = initialFilters.get('to') || '';
    filtersForm.addEventListener('submit', (event) => {
        event.preventDefault();
        renderPublicData().catch((error) => notify(error.message));
    });
    await renderPublicData();

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
    const form = document.getElementById('support-request-form');
    form.elements.name.value = data.name;
    form.elements.project_id.innerHTML = '<option value="">Selecione um projeto</option>' + data.projects.map((project) => `<option value="${project.id}">${escapeHTML(project.name)} · ${escapeHTML(project.organization)}</option>`).join('');
    const typeSelect = form.elements.type;
    const materialNameField = form.elements.material_name.closest('label');
    const unitField = form.elements.unit.closest('label');
    const quantityLabel = form.elements.quantity.closest('label');
    const updateResourceFields = () => {
        const project = data.projects.find((item) => Number(item.id) === Number(form.elements.project_id.value));
        const acceptedResources = project?.accepted_resources || [];
        const selectedType = typeSelect.value;
        typeSelect.innerHTML = '<option value="">Selecione</option>' + acceptedResources.map((type) => `<option value="${escapeHTML(type)}">${type === 'Voluntariado' ? 'Tempo voluntário' : escapeHTML(type)}</option>`).join('');
        if (acceptedResources.includes(selectedType)) typeSelect.value = selectedType;
        materialNameField.hidden = typeSelect.value !== 'Material';
        unitField.hidden = typeSelect.value !== 'Material';
        form.elements.material_name.hidden = typeSelect.value !== 'Material';
        form.elements.unit.hidden = typeSelect.value !== 'Material';
        quantityLabel.firstChild.textContent = typeSelect.value === 'Dinheiro'
            ? 'Valor do apoio (R$)'
            : typeSelect.value === 'Voluntariado' ? 'Horas de trabalho voluntário' : 'Quantidade';
        form.elements.material_name.required = typeSelect.value === 'Material';
        form.elements.unit.required = typeSelect.value === 'Material';
    };
    const updateIndicators = () => {
        const indicators = data.indicators.filter((item) => Number(item.project_id) === Number(form.elements.project_id.value));
        form.elements.indicator_id.innerHTML = '<option value="">Sem vínculo</option>' + indicators.map((item) => `<option value="${item.id}">${escapeHTML(item.name)}</option>`).join('');
    };
    form.elements.project_id.addEventListener('change', () => {
        updateIndicators();
        updateResourceFields();
    });
    typeSelect.addEventListener('change', updateResourceFields);
    updateIndicators();
    updateResourceFields();
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        form.querySelector('[role="alert"]').textContent = '';
        try {
            await apiRequest({ action: 'submit-support-request' }, { method: 'POST', body: new FormData(form) });
            form.reset();
            form.elements.name.value = data.name;
            updateIndicators();
            updateResourceFields();
            notify('Pedido enviado para análise da UOSCC ou da OSC.');
            await loadSupporterPageData(await apiRequest({ action: 'supporter-dashboard' }));
        } catch (error) {
            form.querySelector('[role="alert"]').textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
    await loadSupporterPageData(data);
}

async function loadSupporterPageData(data) {
    const resources = document.getElementById('supporter-resources');
    resources.innerHTML = `<p>${number(data.total)} contribuições aprovadas</p>${reportTable('Minhas contribuições e voluntariado', ['Data', 'Projeto', 'Tipo', 'Quantidade', 'Destinação'], data.resources.map((item) => [item.date, item.project || 'Aguardando vínculo', item.type, item.type === 'Dinheiro' ? money(item.quantity) : item.type === 'Material' ? `${number(item.quantity)} ${item.unit || ''} · ${item.material_name || ''}` : `${number(item.quantity)} h`, item.usage || 'Sem descrição']))}`;
    const requests = document.getElementById('supporter-requests');
    const statusLabels = { pending: 'Aguardando análise', approved: 'Aprovado', rejected: 'Não aprovado' };
    requests.innerHTML = data.requests.length ? `<table><thead><tr><th>Enviado em</th><th>Projeto</th><th>Tipo</th><th>Quantidade</th><th>Detalhes</th><th>Status</th><th>Comprovante</th></tr></thead><tbody>${data.requests.map((item) => `<tr><td>${escapeHTML(item.created_at)}</td><td>${escapeHTML(item.project)}</td><td>${escapeHTML(item.type)}</td><td>${item.type === 'Dinheiro' ? money(item.quantity) : item.type === 'Material' ? `${number(item.quantity)} ${escapeHTML(item.unit || '')}` : `${number(item.quantity)} h`}</td><td>${item.type === 'Material' ? escapeHTML(item.material_name || '') + ' · ' : ''}${escapeHTML(item.description || '')}</td><td>${statusLabels[item.status] || escapeHTML(item.status)}</td><td><a href="${escapeHTML(item.evidence_url)}" target="_blank" rel="noopener">Abrir</a></td></tr>`).join('')}</tbody></table>` : '<p class="empty-state">Nenhum pedido enviado.</p>';
}

async function loadSupportRequests() {
    const container = document.getElementById('support-requests');
    const requests = await apiRequest({ action: 'support-requests' });
    container.innerHTML = requests.length ? `<table><thead><tr><th>Apoiador</th><th>Projeto / OSC</th><th>Tipo</th><th>Quantidade</th><th>Descrição</th><th>Comprovante</th><th>Análise</th></tr></thead><tbody>${requests.map((item) => `<tr><td>${escapeHTML(item.supporter)}</td><td>${escapeHTML(item.project)}<br><small>${escapeHTML(item.organization)}</small></td><td>${escapeHTML(item.type)}</td><td>${item.type === 'Dinheiro' ? money(item.quantity) : item.type === 'Material' ? `${number(item.quantity)} ${escapeHTML(item.unit || '')}` : `${number(item.quantity)} h`}</td><td>${item.type === 'Material' ? escapeHTML(item.material_name || '') + ' · ' : ''}${escapeHTML(item.description || '—')}${item.indicator ? `<br><small>Indicador: ${escapeHTML(item.indicator)}</small>` : ''}</td><td><a href="${escapeHTML(item.evidence_url)}" target="_blank" rel="noopener">Abrir</a></td><td><button type="button" data-request-id="${Number(item.id)}" data-decision="approve">Aprovar</button> <button type="button" data-request-id="${Number(item.id)}" data-decision="reject">Rejeitar</button></td></tr>`).join('')}</tbody></table>` : '<p class="empty-state">Nenhum pedido pendente.</p>';
}

function bindSupportRequests() {
    const container = document.getElementById('support-requests');
    container.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-decision]');
        if (!button) return;
        button.disabled = true;
        try {
            await apiRequest({ action: 'review-support-request' }, { method: 'POST', body: JSON.stringify({ id: button.dataset.requestId, decision: button.dataset.decision }) });
            await loadSupportRequests();
            await loadEntity('resources');
            if (document.getElementById('card-dinheiro')) await loadDashboard();
            notify(button.dataset.decision === 'approve' ? 'Apoio aprovado e contabilizado.' : 'Pedido rejeitado.');
        } catch (error) {
            button.disabled = false;
            notify(error.message);
        }
    });
}

function setRoleNavigation(user) {
    const permissions = roleNavigationPermissions(user.role);
    for (const [group, , items] of navigationGroups) {
        const container = document.querySelector(`[data-menu-group="${group}"]`);
        const list = container.querySelector('ul');
        const savedOrder = (currentPreferences.menuOrder || []).filter((key) => items.some(([itemKey]) => itemKey === key) && permissions.includes(key));
        const order = [...new Set([...savedOrder, ...items.map(([key]) => key).filter((key) => permissions.includes(key))])];
        for (const key of order) {
            const link = list.querySelector(`[data-role-page="${key}"]`);
            if (link) list.append(link.closest('li'));
        }
        for (const [key] of items) {
            const link = list.querySelector(`[data-role-page="${key}"]`);
            if (link) link.closest('li').hidden = !permissions.includes(key);
        }
        container.hidden = !order.length;
    }
    const userName = document.getElementById('current-user-name');
    if (userName) userName.textContent = user.name || user.email;
    applyThemePreference();
    applyDashboardPreferences();
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle && !themeToggle.dataset.bound) {
        themeToggle.dataset.bound = 'true';
        themeToggle.addEventListener('click', async () => {
            currentPreferences.theme = currentPreferences.theme === 'dark' ? 'light' : 'dark';
            applyThemePreference();
            try {
                currentPreferences = await apiRequest({ action: 'preferences' }, { method: 'POST', body: JSON.stringify(currentPreferences) });
            } catch (error) {
                notify(error.message);
            }
        });
    }
}

function roleNavigationPermissions(role) {
    if (role === 'admin') return ['dashboard', 'projetos', 'apoiadores', 'financeiro', 'metas', 'evidencias', 'relatorios', 'publico', 'contas', 'conta', 'configuracoes'];
    if (role === 'osc') return ['dashboard', 'projetos', 'financeiro', 'metas', 'evidencias', 'relatorios', 'publico', 'conta', 'configuracoes'];
    return ['apoiador', 'publico', 'conta', 'configuracoes'];
}

function applyThemePreference() {
    const theme = currentPreferences.theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
    const button = document.getElementById('theme-toggle');
    if (button) button.textContent = theme === 'dark' ? 'Tema claro' : 'Tema escuro';
}

function applyDashboardPreferences() {
    const container = document.getElementById('dashboard-cards');
    if (!container) return;
    const hidden = currentPreferences.hiddenCards || [];
    const defaultOrder = dashboardItems.map(([key]) => key);
    const order = [...new Set([...(currentPreferences.cardOrder || []), ...defaultOrder])];
    for (const key of order) {
        const card = container.querySelector(`[data-dashboard-card="${key}"]`);
        if (card) {
            card.hidden = hidden.includes(key);
            container.append(card);
        }
    }
}

function settingsList(items, list, order = [], allowHide = false, hiddenItems = []) {
    const defaultOrder = items.map(([key]) => key);
    const orderedItems = [...new Set([...order, ...defaultOrder])].filter((key) => defaultOrder.includes(key));
    list.replaceChildren(...orderedItems.map((key) => {
        const [, label] = items.find(([itemKey]) => itemKey === key);
        const row = document.createElement('li');
        row.dataset.settingKey = key;
        const text = document.createElement(allowHide ? 'label' : 'span');
        if (allowHide) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = !hiddenItems.includes(key);
            checkbox.dataset.visibility = '';
            text.append(checkbox, document.createTextNode(` ${label}`));
        } else {
            text.textContent = label;
        }
        const up = document.createElement('button');
        up.type = 'button';
        up.dataset.move = '-1';
        up.setAttribute('aria-label', `Mover ${label} para cima`);
        up.textContent = 'Subir';
        const down = document.createElement('button');
        down.type = 'button';
        down.dataset.move = '1';
        down.setAttribute('aria-label', `Mover ${label} para baixo`);
        down.textContent = 'Descer';
        row.append(text, up, down);
        return row;
    }));
    const updateMoveButtons = () => {
        const rows = [...list.querySelectorAll('[data-setting-key]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-move="-1"]').disabled = index === 0;
            row.querySelector('[data-move="1"]').disabled = index === rows.length - 1;
        });
    };
    updateMoveButtons();
    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-move]');
        if (!button) return;
        const row = button.closest('li');
        if (button.dataset.move === '-1' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
        if (button.dataset.move === '1' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        updateMoveButtons();
    });
}

async function loadAccountPage() {
    const overview = await apiRequest({ action: 'account-overview' });
    const profileForm = document.getElementById('account-profile-form');
    const status = profileForm.querySelector('[role="status"]');
    const avatar = document.getElementById('account-avatar');
    profileForm.elements.name.value = currentUser.name || '';
    profileForm.elements.email.value = currentUser.email || '';
    if (overview.avatarUrl) avatar.src = overview.avatarUrl;
    document.getElementById('account-role-note').textContent = overview.isSupporter
        ? 'Os totais e a classificação abaixo são atualizados diretamente pelas contribuições aprovadas.'
        : 'Os totais individuais de doações são vinculados às contas de apoiadores.';
    document.getElementById('account-money').textContent = money(overview.totals.money);
    document.getElementById('account-volunteer').textContent = `${number(overview.totals.volunteer)} h`;
    document.getElementById('account-materials').innerHTML = reportTable(
        'Materiais doados',
        ['Projeto', 'Material', 'Quantidade'],
        overview.totals.materials.map((item) => [item.project, item.material_name, `${number(item.quantity)} ${item.unit}`]),
    );
    const moneyRanking = overview.rankings.money;
    const volunteerRanking = overview.rankings.volunteer;
    document.getElementById('account-money-rank').textContent = !overview.isSupporter
        ? 'Classificação pessoal disponível para apoiadores.'
        : moneyRanking.position
            ? `Você está na posição ${moneyRanking.position} do ranking de dinheiro.`
            : `Faltam ${money(moneyRanking.amountToReach)} para entrar no top 10.`;
    document.getElementById('account-volunteer-rank').textContent = !overview.isSupporter
        ? 'Classificação pessoal disponível para apoiadores.'
        : volunteerRanking.position
            ? `Você está na posição ${volunteerRanking.position} do ranking de voluntariado.`
            : `Faltam ${number(volunteerRanking.amountToReach)} h para entrar no top 10.`;
    profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = profileForm.querySelector('[type="submit"]');
        button.disabled = true;
        status.textContent = '';
        try {
            const result = await apiRequest({ action: 'account' }, {
                method: 'POST',
                body: JSON.stringify({ operation: 'profile', name: profileForm.elements.name.value }),
            });
            currentUser.name = result.name;
            const userName = document.getElementById('current-user-name');
            if (userName) userName.textContent = result.name;
            status.textContent = 'Perfil atualizado.';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
    const avatarForm = document.getElementById('account-avatar-form');
    avatarForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = avatarForm.querySelector('[type="submit"]');
        button.disabled = true;
        status.textContent = '';
        try {
            const result = await apiRequest({ action: 'account-avatar' }, {
                method: 'POST',
                body: new FormData(avatarForm),
            });
            avatar.src = result.avatarUrl;
            currentUser.avatar_url = result.avatarUrl;
            status.textContent = 'Foto de perfil atualizada.';
            avatarForm.reset();
        } catch (error) {
            status.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
}

function loadSettingsPage() {
    const allowedPages = roleNavigationPermissions(currentUser.role);
    const menuOrder = currentPreferences.menuOrder || [];
    navigationGroups.forEach(([group, , items]) => {
        const available = items.filter(([key]) => allowedPages.includes(key));
        settingsList(available, document.getElementById(`settings-menu-${group}`), menuOrder.filter((key) => available.some(([itemKey]) => itemKey === key)));
    });
    if (currentUser.role === 'supporter') document.getElementById('settings-cards-section').hidden = true;
    else settingsList(dashboardItems, document.getElementById('settings-cards'), currentPreferences.cardOrder || [], true, currentPreferences.hiddenCards || []);
    const theme = currentPreferences.theme === 'dark' ? 'dark' : 'light';
    document.querySelector(`input[name="theme"][value="${theme}"]`).checked = true;
    const form = document.getElementById('preferences-form');
    const status = document.getElementById('preferences-status');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const menuOrder = navigationGroups.flatMap(([group]) => [...document.querySelectorAll(`#settings-menu-${group} [data-setting-key]`)].map((row) => row.dataset.settingKey));
        const preferences = {
            theme: new FormData(form).get('theme'),
            menuOrder,
            cardOrder: currentUser.role === 'supporter' ? currentPreferences.cardOrder || [] : collectOrder('settings-cards'),
            hiddenCards: currentUser.role === 'supporter' ? currentPreferences.hiddenCards || [] : collectHiddenCards(),
        };
        try {
            currentPreferences = await apiRequest({ action: 'preferences' }, { method: 'POST', body: JSON.stringify(preferences) });
            setRoleNavigation(currentUser);
            status.textContent = 'Configurações salvas.';
        } catch (error) {
            status.textContent = error.message;
        }
    });
    bindAccountManagement();
    const identitySection = document.getElementById('public-identity-section');
    if (currentUser.role === 'supporter') {
        identitySection.hidden = false;
        apiRequest({ action: 'supporter-dashboard' }).then((data) => {
            document.querySelector('#public-identity-form [name="public_name_allowed"]').checked = data.publicNameAllowed;
        }).catch((error) => notify(error.message));
        document.getElementById('public-identity-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const status = form.querySelector('[role="status"]');
            try {
                await apiRequest({ action: 'account' }, {
                    method: 'POST',
                    body: JSON.stringify({
                        operation: 'public-name-visibility',
                        allowed: form.elements.public_name_allowed.checked,
                    }),
                });
                status.textContent = 'Sua autorização de exibição foi atualizada.';
            } catch (error) {
                status.textContent = error.message;
            }
        });
    }
}

function collectOrder(listId) {
    return [...document.querySelectorAll(`#${listId} [data-setting-key]`)].map((row) => row.dataset.settingKey);
}

function collectHiddenCards() {
    return [...document.querySelectorAll('#settings-cards [data-setting-key]')]
        .filter((row) => !row.querySelector('[data-visibility]').checked)
        .map((row) => row.dataset.settingKey);
}

function bindAccountManagement() {
    const passwordForm = document.getElementById('password-form');
    passwordForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const alert = passwordForm.querySelector('[role="alert"]');
        const status = passwordForm.querySelector('[role="status"]');
        const button = passwordForm.querySelector('[type="submit"]');
        alert.textContent = '';
        status.textContent = '';
        if (passwordForm.elements.new_password.value !== passwordForm.elements.confirm_password.value) {
            alert.textContent = 'A confirmação da nova senha não corresponde.';
            return;
        }
        button.disabled = true;
        try {
            await apiRequest({ action: 'account' }, {
                method: 'POST',
                body: JSON.stringify({
                    operation: 'password',
                    current_password: passwordForm.elements.current_password.value,
                    new_password: passwordForm.elements.new_password.value,
                }),
            });
            passwordForm.reset();
            status.textContent = 'Senha alterada. Sua sessão atual continua ativa.';
        } catch (error) {
            alert.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });

    const deleteForm = document.getElementById('delete-account-form');
    deleteForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!window.confirm('Excluir sua conta permanentemente? Esta ação não pode ser desfeita.')) return;
        const alert = deleteForm.querySelector('[role="alert"]');
        const button = deleteForm.querySelector('[type="submit"]');
        alert.textContent = '';
        button.disabled = true;
        try {
            await apiRequest({ action: 'account' }, {
                method: 'POST',
                body: JSON.stringify({
                    operation: 'delete',
                    current_password: deleteForm.elements.current_password.value,
                }),
            });
            window.location.href = 'login.html';
        } catch (error) {
            alert.textContent = error.message;
            button.disabled = false;
        }
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
    report.peopleServed.forEach((item) => rows.push(['Atendimento', item.date, item.project, item.description, 'Pessoas', '', item.quantity, '']));
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
                const response = await apiRequest({ action: 'register-supporter' }, {
                    method: 'POST',
                    body: JSON.stringify(Object.fromEntries(new FormData(signupForm))),
                });
                window.location.href = response.user.role === 'supporter' ? 'apoiador.html' : 'dashboard.html';
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
        currentPreferences = await apiRequest({ action: 'preferences' });
        setRoleNavigation(currentUser);
        if (currentUser.role === 'supporter' && !['apoiador.html', 'conta.html', 'configuracoes.html'].includes(page)) {
            const content = document.querySelector('.content');
            content.innerHTML = '<div class="page-header"><div><h1>Área restrita</h1><p>Esta página contém ferramentas de gestão. Você pode acompanhar seus pedidos ou consultar a transparência pública.</p><p><a class="btn" href="apoiador.html">Meu apoio</a> <a class="btn" href="publico.html">Página pública</a></p></div></div>';
            return;
        }
        const oscPages = ['dashboard.html', 'financeiro.html', 'metas.html', 'evidencias.html', 'projetos.html', 'relatorios.html', 'conta.html', 'configuracoes.html'];
        if (currentUser.role === 'osc' && !oscPages.includes(page)) {
            window.location.href = 'dashboard.html';
            return;
        }
        if (currentUser.role !== 'supporter' && page === 'apoiador.html') {
            window.location.href = 'dashboard.html';
            return;
        }
    }

    if (isPublic) return loadPublicPage();
    if (page === 'conta.html') return loadAccountPage();
    if (page === 'configuracoes.html') return loadSettingsPage();
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
        bindEntity('people_served');
        await loadEntity('people_served');
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
        if (entity === 'resources') {
            bindSupportRequests();
            await loadSupportRequests();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initialize().catch((error) => notify(error.message));
});