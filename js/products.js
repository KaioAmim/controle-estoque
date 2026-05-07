/* ════════════════════════════════════════════════════
   products.js — Gestão de produtos:
   renderização da tabela, modais de cadastro/edição/exclusão
   e consulta de CA (Certificado de Aprovação).

   Depende de: data.js (gDB, sDB, uid, esc, fmt, caStatus,
                        estqStatus, CA_DB, editId, delId,
                        caCache, now)
               ui.js (toast)
════════════════════════════════════════════════════ */

/* ════════════════════════════════════════════════
   RENDERIZAÇÃO: Stats cards (indicadores rápidos)
════════════════════════════════════════════════ */

/**
 * Atualiza os cards de estatísticas no topo da view de Produtos.
 * Calcula totais de: produtos cadastrados, estoque baixo, zerado e CA em alerta.
 * Também atualiza o contador numérico no botão de menu lateral.
 */
function renderStats() {
  const db = gDB();

  // Produtos com estoque zerado
  const sem = db.filter(x => x.qty === 0).length;
  // Produtos abaixo do mínimo (mas com algum estoque)
  const baixo = db.filter(x => x.qty > 0 && x.qty <= x.min).length;
  // CAs já vencidos
  const caVenc = db.filter(x => {
    if (!x.val) return false;
    return new Date(x.val + 'T00:00:00') < now;
  }).length;
  // CAs vencendo em até 60 dias
  const caWarn = db.filter(x => {
    if (!x.val) return false;
    const d = new Date(x.val + 'T00:00:00');
    const diff = (d - now) / 864e5;
    return diff >= 0 && diff <= 60;
  }).length;

  // Injeta os cards de estatística no DOM
  document.getElementById('stats').innerHTML = `
    <div class="stat-card">
      <div class="stat-card-label">Total de EPIs</div>
      <div class="stat-card-val val-sky">${db.length}</div>
      <div class="stat-card-sub">produtos cadastrados</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Estoque baixo</div>
      <div class="stat-card-val val-amber">${baixo}</div>
      <div class="stat-card-sub">abaixo do mínimo</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Sem estoque</div>
      <div class="stat-card-val val-red">${sem}</div>
      <div class="stat-card-sub">produtos zerados</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">CA em alerta</div>
      <div class="stat-card-val val-amber">${caVenc + caWarn}</div>
      <div class="stat-card-sub">${caVenc} vencido · ${caWarn} vencendo</div>
    </div>
  `;

  // Atualiza o badge numérico no menu lateral
  document.getElementById('nc').textContent = db.length;
}


/* ════════════════════════════════════════════════
   RENDERIZAÇÃO: Select de categorias (filtro)
════════════════════════════════════════════════ */

/**
 * Atualiza o <select> de filtro por categoria com as categorias
 * disponíveis nos produtos cadastrados (sem duplicatas, ordem alfabética).
 * Preserva a categoria selecionada atualmente, se ela ainda existir.
 */
function renderCats() {
  const cats = [...new Set(gDB().map(x => x.cat))].sort();
  const sel = document.getElementById('catf');
  const cur = sel.value; // preserva a seleção atual

  sel.innerHTML =
    '<option value="">Todas as categorias</option>' +
    cats.map(c => `<option value="${esc(c)}"${c === cur ? ' selected' : ''}>${esc(c)}</option>`).join('');
}


/* ════════════════════════════════════════════════
   RENDERIZAÇÃO: Tabela de produtos
════════════════════════════════════════════════ */

/**
 * Renderiza (ou re-renderiza) a tabela de produtos, aplicando
 * os filtros de busca por texto e por categoria.
 *
 * Filtros ativos:
 *   - #sq (search query): busca por nome, SKU ou número de CA
 *   - #catf (categoria): exibe apenas produtos da categoria selecionada
 */
function renderTable() {
  const q   = document.getElementById('sq').value.toLowerCase();
  const cat = document.getElementById('catf').value;

  // Filtra os produtos conforme os critérios ativos
  const db = gDB().filter(p => {
    const mq = !q ||
      p.nome.toLowerCase().includes(q) ||
      (p.sku || '').toLowerCase().includes(q) ||
      (p.ca  || '').includes(q);
    return mq && (!cat || p.cat === cat);
  });

  const tb = document.getElementById('tbody');

  // Exibe mensagem de "nenhum resultado" se o array filtrado estiver vazio
  if (!db.length) {
    tb.innerHTML = `<tr class="empty-row"><td colspan="8">Nenhum produto encontrado</td></tr>`;
    return;
  }

  // Monta as linhas da tabela com os dados de cada produto
    tb.innerHTML = db.map(p => {
      const se     = estqStatus(p.qty, p.min); // status de estoque
      const sc     = caStatus(p.val);          // status do CA
      const caDesc = p.desc || '';             // usa a descrição salva no produto

      return `<tr>
        <td>
          <div class="cell-name">${esc(p.nome)}</div>
          <div class="cell-sku">${esc(p.sku)}</div>
        </td>
        <td><span class="badge-cat">${esc(p.cat)}</span></td>
        <td>
          <div class="cell-ca">${p.ca ? 'CA ' + esc(p.ca) : '—'}</div>
          ${caDesc ? `<div class="cell-ca-desc" title="${esc(caDesc)}">${esc(caDesc)}</div>` : ''}
        </td>
      <td><div class="cell-date">${fmt(p.val)}</div></td>
      <td><span class="badge ${sc.cls}"><span class="badge-dot"></span>${sc.lbl}</span></td>
      <td class="qty-cell">${p.qty}</td>
      <td><span class="badge ${se.cls}"><span class="badge-dot"></span>${se.lbl}</span></td>
      <td>
        <div class="actions">
          <button class="btn-edit" onclick="openModal('${p.id}')">Editar</button>
          <button class="btn-del"  onclick="openConfirm('${p.id}')">Excluir</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}


/* ════════════════════════════════════════════════
   ORQUESTRADOR: renderiza tudo de uma vez
════════════════════════════════════════════════ */

/**
 * Executa todas as funções de renderização em sequência.
 * Chamada sempre que os dados mudam (salvar, excluir, login).
 */
function renderAll() {
  renderStats();
  renderCats();
  renderTable();
}


/* ════════════════════════════════════════════════
   MODAL: Cadastro e edição de produto
════════════════════════════════════════════════ */

/**
 * Abre o modal de cadastro/edição de produto.
 * Se `id` for fornecido, pré-preenche os campos com os dados do produto.
 * Se `id` for null/undefined, prepara o modal para um novo cadastro.
 *
 * @param {string|null} id - ID do produto a editar (null para novo)
 */
function openModal(id) {
  // Reseta estado interno
  editId   = id || null;
  caCache  = null;

  // Esconde e reseta o painel de resultado de CA
  const r = document.getElementById('ca-result');
  r.style.display = 'none';
  r.className = 'ca-result';

  // Busca o produto no banco (undefined se for novo)
  const p = id ? gDB().find(x => x.id === id) : null;

  // Atualiza o título do modal
  document.getElementById('modal-title').textContent = id ? 'Editar produto' : 'Novo produto';

  // Preenche os campos com os dados do produto (ou valores padrão)
  document.getElementById('f-nome').value = p?.nome ?? '';
  document.getElementById('f-sku').value  = p?.sku  ?? '';
  document.getElementById('f-cat').value  = p?.cat  ?? '';
  document.getElementById('f-ca').value   = p?.ca   ?? '';
  document.getElementById('f-val').value  = p?.val  ?? '';
  document.getElementById('f-qty').value  = p?.qty  ?? 0;
  document.getElementById('f-min').value  = p?.min  ?? 5;
  document.getElementById('f-desc').value = p?.desc ?? '';

  // Abre o modal
  document.getElementById('modal-wrap').classList.add('open');

  // Foca no campo "Nome" para facilitar o uso por teclado
  setTimeout(() => document.getElementById('f-nome').focus(), 60);
}

/**
 * Fecha o modal de cadastro/edição e reseta o ID de edição.
 */
function closeModal() {
  document.getElementById('modal-wrap').classList.remove('open');
  editId = null;
}


/* ════════════════════════════════════════════════
   CONSULTA DE CA (Certificado de Aprovação)
════════════════════════════════════════════════ */

/**
 * Consulta o CA no site consultaca.com via PHP intermediário.
 *
 * Por que o PHP é necessário aqui?
 * O navegador não pode buscar diretamente um site externo por
 * segurança (política de CORS). O PHP funciona como um
 * "intermediário": o JS chama o nosso PHP, e o PHP busca
 * o consultaca.com no servidor e devolve o resultado.
 *
 * Fluxo:
 *  JS → api/consultar_ca.php → consultaca.com → PHP → JS
 *
 * Resposta esperada do PHP:
 *  Sucesso: { sucesso: true, ca: "46734", validade: "2026-08-15" }
 *  Erro:    { sucesso: false, erro: "mensagem" }
 */
async function consultarCA() {
  const ca  = document.getElementById('f-ca').value.trim();
  const btn = document.getElementById('btn-ca');
  const res = document.getElementById('ca-result');

  // Valida: o CA deve conter apenas números
  if (!ca || !/^\d+$/.test(ca)) {
    toast('Informe um número de CA válido.', 'amber');
    return;
  }

  // Desabilita o botão e exibe spinner enquanto aguarda resposta
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>Consultando…';
  res.style.display = 'none';

  try {
    // Chama o PHP passando o número do CA como parâmetro na URL
    const response = await fetch(`api/proxy_ca.php?ca=${encodeURIComponent(ca)}`);

    // Verifica se a resposta HTTP foi bem-sucedida (status 200)
    if (!response.ok) {
      throw new Error(`Erro HTTP ${response.status}`);
    }

    // Converte a resposta de JSON para objeto JavaScript
    const data = await response.json();

    if (data.sucesso) {
      // ── SUCESSO: CA encontrado e validade extraída ──

      // Armazena em cache para uso posterior (ex: ao salvar o produto)
      caCache = { ca: data.ca, val: data.validade };

      // Preenche automaticamente o campo de data de validade e descrição
      document.getElementById('f-val').value = data.validade || '';
      if (data.descricao) {
        document.getElementById('f-desc').value = data.descricao;
      }

      // Exibe painel verde de sucesso com badge de status
      const sc = caStatus(data.validade);
      const badgeHtml = `<span class="badge ${sc.cls}" style="font-size:11px"><span class="badge-dot"></span>${sc.lbl}</span>`;
      res.className = 'ca-result ok';
      res.innerHTML = `
        <strong>CA ${esc(data.ca)} encontrado</strong> ${badgeHtml}<br>
        Fonte: <a href="https://consultaca.com/${esc(data.ca)}" target="_blank"
          style="color:var(--accent-l)">consultaca.com</a><br>
        Validade: <strong>${fmt(data.validade)}</strong>
      `;
      res.style.display = 'block';

    } else {
      // ── ERRO RETORNADO PELO PHP (CA não encontrado, falha de rede, etc.) ──
      res.className = 'ca-result err';
      res.innerHTML = `<strong>Não foi possível consultar o CA ${esc(ca)}.</strong><br>
        ${esc(data.erro || 'Erro desconhecido.')}<br>
        <span style="opacity:.8">Você pode informar a validade manualmente ou
        <a href="https://consultaca.com/${esc(ca)}" target="_blank"
          style="color:inherit;text-decoration:underline">consultar direto no site</a>.</span>`;
      res.style.display = 'block';
    }

  } catch (err) {
    // ── ERRO DE REDE ou falha inesperada no fetch ──
    // Isso acontece se o servidor PHP estiver offline ou inacessível
    res.className = 'ca-result err';
    res.innerHTML = `<strong>Erro de conexão com o servidor.</strong><br>
      Verifique se o servidor PHP está rodando e tente novamente.<br>
      <em style="opacity:.7">Detalhe: ${esc(err.message)}</em>`;
    res.style.display = 'block';

    console.error('[consultarCA] Erro ao chamar api/consultar_ca.php:', err);
  } finally {
    // Sempre reabilita o botão, independente do resultado
    btn.disabled = false;
    btn.textContent = 'Consultar CA';
  }
}


/* ════════════════════════════════════════════════
   SALVAR PRODUTO (criar ou atualizar)
════════════════════════════════════════════════ */

/**
 * Lê os dados do formulário de produto, valida os campos obrigatórios
 * e salva no localStorage (criando novo ou atualizando existente).
 * Após salvar: fecha o modal, re-renderiza a interface e exibe toast.
 */
function saveProduct() {
  const nome = document.getElementById('f-nome').value.trim();
  const cat  = document.getElementById('f-cat').value.trim();

  // Validações dos campos obrigatórios
  if (!nome) { toast('O nome do produto é obrigatório.', 'danger'); return; }
  if (!cat)  { toast('A categoria é obrigatória.', 'danger');        return; }

  const db = gDB();

  // Monta o objeto do produto com os valores do formulário
  const obj = {
    id:   editId || uid(),                               // mantém o ID se for edição
    nome,
    sku:  document.getElementById('f-sku').value.trim() || '—',
    cat,
    ca:   document.getElementById('f-ca').value.trim()  || null,
    val:  document.getElementById('f-val').value         || null,
    qty:  Math.max(0, parseInt(document.getElementById('f-qty').value) || 0),
    min:  Math.max(0, parseInt(document.getElementById('f-min').value) || 5),
    desc: document.getElementById('f-desc').value.trim(),
  };

  if (editId) {
    // Modo edição: substitui o produto existente pelo atualizado
    const i = db.findIndex(x => x.id === editId);
    if (i >= 0) db[i] = obj;
  } else {
    // Modo criação: adiciona o novo produto ao final da lista
    db.push(obj);
  }

  sDB(db);         // persiste no localStorage
  closeModal();    // fecha o modal
  renderAll();     // atualiza a interface

  // Exibe feedback de sucesso ao usuário
  toast(editId ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.', 'green');
}


/* ════════════════════════════════════════════════
   MODAL: Confirmação de exclusão
════════════════════════════════════════════════ */

/**
 * Abre o modal de confirmação de exclusão com o nome do produto.
 * @param {string} id - ID do produto a excluir
 */
function openConfirm(id) {
  delId = id;
  const p = gDB().find(x => x.id === id);
  // Exibe o nome do produto no texto de confirmação
  document.getElementById('del-name').textContent = p?.nome ?? 'este produto';
  document.getElementById('confirm-wrap').classList.add('open');
}

/**
 * Fecha o modal de confirmação e reseta o ID de exclusão pendente.
 */
function closeConfirm() {
  document.getElementById('confirm-wrap').classList.remove('open');
  delId = null;
}

/**
 * Confirma e executa a exclusão do produto com ID em `delId`.
 * Remove o produto do banco, fecha o modal e atualiza a interface.
 */
function confirmDelete() {
  if (!delId) return;

  // Filtra o produto com o ID marcado para exclusão
  sDB(gDB().filter(x => x.id !== delId));

  closeConfirm();
  renderAll();
  toast('Produto removido do estoque.', 'danger');
}


/* ════════════════════════════════════════════════
   EVENT LISTENERS: Fechar modais ao clicar no overlay
════════════════════════════════════════════════ */

// Fecha o modal de edição se clicar fora da caixa (no fundo escurecido)
document.getElementById('modal-wrap').addEventListener('click', e => {
  if (e.target === document.getElementById('modal-wrap')) closeModal();
});

// Fecha o modal de confirmação se clicar fora da caixa
document.getElementById('confirm-wrap').addEventListener('click', e => {
  if (e.target === document.getElementById('confirm-wrap')) closeConfirm();
});
