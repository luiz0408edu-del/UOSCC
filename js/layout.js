document.addEventListener("DOMContentLoaded", () => {
    const currentPage = window.location.pathname.split('/').pop();
    if (currentPage === 'login.html' || currentPage === 'publico.html') return;

    const sidebarHTML = `
        <aside>
            <div>
                <div class="sidebar-brand">
                    Painel de Impacto
                    <span>UOSCC · REDCAM</span>
                </div>
                <nav class="sidebar-menu" aria-label="Navegação principal">
                    <section class="sidebar-menu-group" data-menu-group="menu">
                        <h2 class="sidebar-section-title">Menu</h2>
                        <ul>
                            <li><a href="apoiador.html" id="menu-apoiador" data-role-page="apoiador">Meu apoio</a></li>
                            <li><a href="dashboard.html" id="menu-dashboard" data-role-page="dashboard">Painel geral</a></li>
                        </ul>
                    </section>
                    <section class="sidebar-menu-group" data-menu-group="gestao">
                        <h2 class="sidebar-section-title">Gestão</h2>
                        <ul>
                            <li><a href="projetos.html" id="menu-projetos" data-role-page="projetos">Projetos</a></li>
                            <li><a href="apoiadores.html" id="menu-apoiadores" data-role-page="apoiadores">Apoiadores</a></li>
                            <li><a href="financeiro.html" id="menu-financeiro" data-role-page="financeiro">Recursos</a></li>
                            <li><a href="metas.html" id="menu-metas" data-role-page="metas">Indicadores</a></li>
                            <li><a href="evidencias.html" id="menu-evidencias" data-role-page="evidencias">Evidências</a></li>
                            <li><a href="relatorios.html" id="menu-relatorios" data-role-page="relatorios">Relatórios</a></li>
                        </ul>
                    </section>
                    <section class="sidebar-menu-group" data-menu-group="contas">
                        <h2 class="sidebar-section-title">Gerenciamento de contas</h2>
                        <ul>
                            <li><a href="publico.html" id="menu-publico" data-role-page="publico">Página pública</a></li>
                            <li><a href="contas.html" id="menu-contas" data-role-page="contas">Acessos das OSCs</a></li>
                            <li><a href="conta.html" id="menu-conta" data-role-page="conta">Minha conta</a></li>
                            <li><a href="configuracoes.html" id="menu-configuracoes" data-role-page="configuracoes">Configurações</a></li>
                        </ul>
                    </section>
                </nav>
            </div>
        </aside>
    `;

    const topbarHTML = `
        <header class="topbar">
            <span>Sistema de Transparência e Prestação de Contas</span>
            <span id="current-user-name"></span>
            <button class="theme-toggle" id="theme-toggle" type="button">Tema escuro</button>
            <button class="btn" onclick="logout()" style="padding: 6px 12px; font-size: 0.85rem; background-color: #6C757D;">Sair</button>
        </header>
    `;

    if (!document.querySelector('aside')) {
        document.body.insertAdjacentHTML('afterbegin', sidebarHTML);
    }

    const mainContainer = document.querySelector('.main-container');
    if (mainContainer && !document.querySelector('header.topbar')) {
        mainContainer.insertAdjacentHTML('afterbegin', topbarHTML);
    }

    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
});

async function logout() {
    try {
        if (typeof apiRequest === 'function') {
            await apiRequest({ action: 'logout' }, { method: 'POST' });
        }
    } finally {
        window.location.href = 'login.html';
    }
}