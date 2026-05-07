/* ════════════════════════════════════════════════════
   report.js — Renderização da view de Relatório.

   Gera três painéis de análise:
     1. Resumo geral (contadores de status)
     2. EPIs por categoria (gráfico de barras horizontal)
     3. Alertas ativos (produtos com problemas de estoque/CA)

   Depende de: data.js (gDB, esc, fmt, caStatus, now)
════════════════════════════════════════════════════ */

/**
 * Renderiza todos os painéis da view de Relatório.
 * Chamada automaticamente ao navegar para a view "relatorio" (em ui.js).
 */
function renderReport() {
  const db = gDB();

  /* ── 1. RESUMO GERAL ──────────────────────────── */

  // Calcula os totais de cada categoria de alerta
  const sem    = db.filter(x => x.qty === 0).length;
  const baixo  = db.filter(x => x.qty > 0 && x.qty <= x.min).length;
  const caVenc = db.filter(x => {
    if (!x.val) return false;
    return new Date(x.val + 'T00:00:00') < now;
  }).length;
  const caWarn = db.filter(x => {
    if (!x.val) return false;
    const d = new Date(x.val + 'T00:00:00');
    const diff = (d - now) / 864e5;
    return diff >= 0 && diff <= 60;
  }).length;
  const semCA = db.filter(x => !x.ca).length; // produtos sem CA cadastrado

  // Injeta o HTML do painel de resumo
  document.getElementById('rpt-resumo').innerHTML = `
    <div class="rpt-title">Resumo geral</div>
    <div class="rpt-row">
      <span class="rpt-lbl">Total de EPIs</span>
      <span class="rpt-val val-sky">${db.length}</span>
    </div>
    <div class="rpt-row">
      <span class="rpt-lbl">Estoque zerado</span>
      <span class="rpt-val val-red">${sem}</span>
    </div>
    <div class="rpt-row">
      <span class="rpt-lbl">Estoque baixo</span>
      <span class="rpt-val val-amber">${baixo}</span>
    </div>
    <div class="rpt-row">
      <span class="rpt-lbl">CA vencido</span>
      <span class="rpt-val val-red">${caVenc}</span>
    </div>
    <div class="rpt-row">
      <span class="rpt-lbl">CA vencendo (≤60 dias)</span>
      <span class="rpt-val val-amber">${caWarn}</span>
    </div>
    <div class="rpt-row">
      <span class="rpt-lbl">Sem CA cadastrado</span>
      <span class="rpt-val" style="color:var(--tx3)">${semCA}</span>
    </div>
  `;

  /* ── 2. GRÁFICO: EPIs por categoria ──────────── */

  // Agrupa produtos por categoria e conta quantos há em cada uma
  const cats = {};
  db.forEach(p => {
    cats[p.cat] = (cats[p.cat] || 0) + 1;
  });

  // Ordena do maior para o menor (para exibir as maiores categorias primeiro)
  const sorted = Object.entries(cats).sort((a, b) => b[1] - a[1]);

  // O maior valor serve de referência para escalar as barras (100% = maior categoria)
  const mx = sorted[0]?.[1] || 1;

  // Injeta o gráfico de barras horizontais
  document.getElementById('rpt-cats').innerHTML =
    `<div class="rpt-title">EPIs por categoria</div>` +
    sorted.map(([c, n]) => `
      <div class="bar-item">
        <div class="bar-labels">
          <span>${esc(c)}</span>
          <span style="color:var(--tx)">${n}</span>
        </div>
        <div class="bar-track">
          <!-- Largura proporcional ao total da maior categoria -->
          <div class="bar-fill" style="width:${Math.round(n / mx * 100)}%"></div>
        </div>
      </div>
    `).join('');

  /* ── 3. ALERTAS ATIVOS ────────────────────────── */

  // Filtra produtos que possuem algum tipo de alerta (CA ou estoque)
  // Limita a 8 itens para não sobrecarregar o painel
  const alertas = db.filter(x => {
    const sc = caStatus(x.val);
    return sc.cls !== 'badge-ok' || x.qty === 0 || x.qty <= x.min;
  }).slice(0, 8);

  // Injeta a lista de alertas (ou mensagem de "sem alertas")
  document.getElementById('rpt-alertas').innerHTML =
    `<div class="rpt-title">Alertas ativos</div>` + (
      alertas.length
        ? alertas.map(p => {
            const sc    = caStatus(p.val);
            const isCA  = sc.cls === 'badge-danger' || sc.cls === 'badge-warn';
            // Cor do valor: vermelho se vencido/zerado, amarelo se vencendo/baixo
            const valCor = isCA
              ? (sc.cls === 'badge-danger' ? 'val-red' : 'val-amber')
              : (p.qty === 0 ? 'val-red' : 'val-amber');
            // Exibe info de CA (data de validade) ou de estoque (quantidade)
            const valTxt = isCA ? 'CA: ' + fmt(p.val) : 'Qtd: ' + p.qty;
            return `
              <div class="rpt-row">
                <span class="rpt-lbl">${esc(p.nome)}</span>
                <span class="rpt-val ${valCor}">${valTxt}</span>
              </div>
            `;
          }).join('')
        : '<p style="color:var(--tx3);font-size:13px;padding:.5rem 0">Nenhum alerta no momento.</p>'
    );
}
