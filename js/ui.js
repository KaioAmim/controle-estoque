/* ════════════════════════════════════════════════════
   ui.js — Funções de interface compartilhadas:
   navegação entre views e sistema de notificações (toast).

   Depende de: data.js (renderReport)
════════════════════════════════════════════════════ */

/**
 * Alterna a view (página) exibida na área principal do app.
 * Remove a classe "active" de todas as views e botões do menu,
 * depois ativa apenas a view e o botão correspondentes ao parâmetro.
 *
 * @param {string} v - Identificador da view: 'produtos', 'relatorio' ou 'usuarios'
 */
function showView(v) {
  // Desativa todas as views
  document.querySelectorAll('.view').forEach(x => x.classList.remove('active'));

  // Desativa todos os botões do menu lateral
  document.querySelectorAll('.nav-btn').forEach(x => x.classList.remove('active'));

  // Ativa a view e o botão correspondentes
  document.getElementById('view-' + v).classList.add('active');
  document.getElementById('nav-' + v).classList.add('active');

  // Se a view ativa for "relatório", renderiza os dados do relatório
  if (v === 'relatorio') renderReport();
  
  // Se a view ativa for "usuarios", renderiza a tabela de usuários
  if (v === 'usuarios') renderUsers();
}

/**
 * Exibe uma notificação temporária (toast) no canto inferior direito.
 * O toast desaparece automaticamente após 2,8 segundos.
 * Caso já exista um toast visível, o timer é reiniciado.
 *
 * @param {string} msg   - Texto a exibir na notificação
 * @param {string} type  - Tipo da notificação: 'green' | 'danger' | 'amber'
 */
function toast(msg, type = 'green') {
  // Mapeamento de tipo para cor do indicador visual (ponto colorido)
  const colors = {
    green:  '#22c55e',
    danger: '#ef4444',
    amber:  '#f59e0b',
  };

  // Atualiza a cor do ponto e o texto da mensagem
  document.getElementById('toast-dot').style.background = colors[type] || colors.green;
  document.getElementById('toast-msg').textContent = msg;

  // Torna o toast visível (via classe .show definida no CSS)
  const t = document.getElementById('toast');
  t.classList.add('show');

  // Cancela timer anterior (se o toast for chamado em sequência rápida)
  clearTimeout(toastTimer);

  // Agenda o desaparecimento após 2,8 segundos
  toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}
