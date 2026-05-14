/* ════════════════════════════════════════════════════
   users.js — Gerenciamento de usuários:
   renderização da tabela, modais de cadastro/edição/exclusão
   e persistência no localStorage.

   Depende de: data.js (USERS, saveUsers, loadUsers, esc, uid)
              ui.js (toast)
════════════════════════════════════════════════════ */

/* ════════════════════════════════════════════════
   RENDERIZAÇÃO: Tabela de usuários
════════════════════════════════════════════════ */

/**
 * Renderiza (ou re-renderiza) a tabela de usuários.
 * Exibe todos os usuários cadastrados com opções de edição e exclusão.
 */
function renderUsers() {
  const tb = document.getElementById('users-tbody');

  // Exibe mensagem de "nenhum resultado" se não houver usuários
  if (!USERS.length) {
    tb.innerHTML = `<tr class="empty-row"><td colspan="4">Nenhum usuário cadastrado</td></tr>`;
    return;
  }

  // Monta as linhas da tabela com os dados de cada usuário
  tb.innerHTML = USERS.map((u, idx) => {
    const tipo = u.u === 'admin' ? 'Administrador' : 'Usuário';
    const isAdmin = u.u === 'admin';
    
    return `<tr>
      <td>
        <div class="cell-name">${esc(u.u)}</div>
      </td>
      <td>${esc(u.n)}</td>
      <td><span class="badge ${isAdmin ? 'badge-ok' : 'badge-warn'}" style="font-size:12px"><span class="badge-dot"></span>${tipo}</span></td>
      <td>
        <div class="actions">
          <button class="btn-edit" onclick="openUserModal(${idx})">Editar</button>
          ${u.u !== 'admin' ? `<button class="btn-del" onclick="openUserConfirm(${idx})">Excluir</button>` : '<span style="opacity:0.5">Protegido</span>'}
        </div>
      </td>
    </tr>`;
  }).join('');
}


/* ════════════════════════════════════════════════
   MODAL: Cadastro e edição de usuário
════════════════════════════════════════════════ */

/**
 * Abre o modal de cadastro/edição de usuário.
 * Se `idx` for fornecido, pré-preenche os campos com os dados do usuário.
 * Se `idx` for null/undefined, prepara o modal para um novo cadastro.
 *
 * @param {number|null} idx - Índice do usuário a editar (null para novo)
 */
function openUserModal(idx) {
  // Reseta estado interno
  editUserId = idx !== undefined ? idx : null;

  // Esconde e reseta a mensagem de erro
  const err = document.getElementById('user-err');
  err.style.display = 'none';
  err.textContent = '';

  // Busca o usuário no array (undefined se for novo)
  const u = idx !== undefined ? USERS[idx] : null;

  // Atualiza o título do modal
  document.getElementById('user-modal-title').textContent = idx !== undefined ? 'Editar usuário' : 'Novo usuário';

  // Preenche os campos com os dados do usuário (ou valores padrão)
  document.getElementById('f-user-u').value = u?.u ?? '';
  document.getElementById('f-user-p').value = u?.p ?? '';
  document.getElementById('f-user-n').value = u?.n ?? '';
  document.getElementById('f-user-e').value = u?.e ?? '';

  // Desabilita o campo de usuário se for edição (para não permitir mudança de login)
  document.getElementById('f-user-u').disabled = idx !== undefined;

  // Abre o modal
  document.getElementById('user-modal-wrap').classList.add('open');

  // Foca no primeiro campo disponível
  setTimeout(() => {
    if (idx !== undefined) {
      document.getElementById('f-user-p').focus();
    } else {
      document.getElementById('f-user-u').focus();
    }
  }, 60);
}

/**
 * Fecha o modal de cadastro/edição e reseta o ID de edição.
 */
function closeUserModal() {
  document.getElementById('user-modal-wrap').classList.remove('open');
  editUserId = null;
}


/* ════════════════════════════════════════════════
   SALVAR USUÁRIO (criar ou atualizar)
════════════════════════════════════════════════ */

/**
 * Lê os dados do formulário de usuário, valida os campos obrigatórios
 * e salva no array USERS (criando novo ou atualizando existente).
 * Após salvar: fecha o modal, re-renderiza a interface e exibe toast.
 */
async function saveUser() {
  const u   = document.getElementById('f-user-u').value.trim();
  const p   = document.getElementById('f-user-p').value;
  const n   = document.getElementById('f-user-n').value.trim();
  const e   = document.getElementById('f-user-e').value.trim();
  const err = document.getElementById('user-err');

  if (!u) { err.textContent = 'O nome de usuário é obrigatório.'; err.style.display = 'block'; return; }
  if (!p) { err.textContent = 'A senha é obrigatória.';           err.style.display = 'block'; return; }
  if (!n) { err.textContent = 'O nome completo é obrigatório.';   err.style.display = 'block'; return; }
  if (!e) { err.textContent = 'O e-mail é obrigatório.';          err.style.display = 'block'; return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
    err.textContent = 'Informe um e-mail válido.';
    err.style.display = 'block';
    return;
  }

  // Valida duplicata apenas para novo cadastro
  if (editUserId === null && USERS.find(x => x.u === u)) {
    err.textContent = 'Este nome de usuário já está em uso.';
    err.style.display = 'block';
    return;
  }

  const existingUser = editUserId !== null ? USERS[editUserId] : null;
  const obj = { id: existingUser?.id ?? null, u, p, n, e };

  try {
    const res = await fetch('api/users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(obj)
    });
    const json = await res.json();
    if (json.success) {
      obj.id = json.id;
      if (editUserId !== null) {
        USERS[editUserId] = obj;
      } else {
        USERS.push(obj);
      }
      saveUsers();
      closeUserModal();
      renderUsers();
      toast(editUserId !== null ? 'Usuário atualizado com sucesso.' : 'Usuário cadastrado com sucesso.', 'green');
    } else {
      err.textContent = 'Erro ao salvar: ' + (json.erro || 'desconhecido');
      err.style.display = 'block';
    }
  } catch (e) {
    err.textContent = 'Erro de conexão com o servidor.';
    err.style.display = 'block';
  }
}


/* ════════════════════════════════════════════════
   MODAL: Confirmação de exclusão
════════════════════════════════════════════════ */

/**
 * Abre o modal de confirmação de exclusão com o nome do usuário.
 * @param {number} idx - Índice do usuário a excluir
 */
function openUserConfirm(idx) {
  const u = USERS[idx];
  // Exibe o nome do usuário no texto de confirmação
  document.getElementById('del-user-name').textContent = u?.u ?? 'este usuário';
  document.getElementById('user-confirm-wrap').classList.add('open');
  editUserId = idx; // Armazena o índice para uso posterior
}

/**
 * Fecha o modal de confirmação e reseta o ID de edição.
 */
function closeUserConfirm() {
  document.getElementById('user-confirm-wrap').classList.remove('open');
  editUserId = null;
}

/**
 * Confirma e executa a exclusão do usuário com índice em `editUserId`.
 * Remove o usuário do array, fecha o modal e atualiza a interface.
 */
async function confirmDeleteUser() {
  if (editUserId === null) return;

  const user = USERS[editUserId];
  const id   = user?.id;

  USERS.splice(editUserId, 1);
  saveUsers();
  closeUserConfirm();
  renderUsers();
  toast('Usuário removido do sistema.', 'danger');

  if (id) {
    try {
      await fetch('api/users.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) })
      });
    } catch (e) {
      console.warn('Falha ao remover usuário do banco:', e);
    }
  }
}


/* ════════════════════════════════════════════════
   EVENT LISTENERS: Fechar modais ao clicar no overlay
════════════════════════════════════════════════ */

// Fecha o modal de edição se clicar fora da caixa (no fundo escurecido)
document.getElementById('user-modal-wrap').addEventListener('click', e => {
  if (e.target === document.getElementById('user-modal-wrap')) closeUserModal();
});

// Fecha o modal de confirmação se clicar fora da caixa
document.getElementById('user-confirm-wrap').addEventListener('click', e => {
  if (e.target === document.getElementById('user-confirm-wrap')) closeUserConfirm();
});
