/* ════════════════════════════════════════════════════
   auth.js — Autenticação: login e logout do sistema.

   Depende de: data.js (USERS, renderAll)
════════════════════════════════════════════════════ */

/**
 * Realiza o login do usuário.
 * Lê os valores dos campos #lu (usuário) e #lp (senha),
 * busca na lista USERS e, se encontrar correspondência:
 *   - armazena o usuário em currentUser
 *   - atualiza o nome exibido na topbar (#hu)
 *   - oculta a tela de login e exibe o app
 *   - renderiza todos os dados da interface
 * Caso contrário, exibe a mensagem de erro (#lerr).
 */
function doLogin() {
  const u = document.getElementById('lu').value.trim();
  const p = document.getElementById('lp').value;

  // Oculta qualquer mensagem de erro anterior
  document.getElementById('lerr').style.display = 'none';

  // Busca o usuário com credenciais correspondentes
  const user = USERS.find(x => x.u === u && x.p === p);

  if (!user) {
    // Credenciais inválidas: exibe mensagem de erro
    document.getElementById('lerr').style.display = 'block';
    return;
  }

  // Credenciais válidas: salva o usuário logado
  currentUser = user;

  // Atualiza o nome do usuário na barra superior
  document.getElementById("hu").textContent = user.n;

  // Mostra o botão de usuários apenas se for admin
  const navUsuarios = document.getElementById("nav-usuarios");
  if (navUsuarios) {
    navUsuarios.style.display = user.u === "admin" ? "flex" : "none";
  }

  // Troca de tela: esconde login, exibe o app
  document.getElementById("login-screen").classList.remove("active");
  document.getElementById("app-screen").classList.add("active");

  // Carrega e renderiza todos os dados do sistema
  renderAll();
}

/**
 * Realiza o logout do usuário.
 * Esconde o app e volta para a tela de login.
 * Não limpa os dados do localStorage (apenas encerra a sessão visual).
 */
function doLogout() {
  document.getElementById('app-screen').classList.remove('active');
  document.getElementById('login-screen').classList.add('active');
}

/* ── EVENT LISTENER: Enter no campo de senha ────
   Permite que o usuário faça login pressionando Enter
   enquanto o cursor estiver no campo de senha.
──────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const lp = document.getElementById('lp');
  if (lp) {
    lp.addEventListener('keydown', e => {
      if (e.key === 'Enter') doLogin();
    });
  }
});


/* Recuperação de senha */
function openForgotPasswordModal() {
  document.getElementById('forgot-modal-wrap').classList.add('open');
}

function closeForgotPasswordModal() {
  document.getElementById('forgot-modal-wrap').classList.remove('open');
}

function sendPasswordReset() {
  const user = document.getElementById('forgot-user').value.trim();
  const email = document.getElementById('forgot-email').value.trim();
  const err = document.getElementById('forgot-err');

  err.style.display = 'none';

  if (!user || !email) {
    err.textContent = 'Preencha o usuário e o e-mail.';
    err.style.display = 'block';
    return;
  }

  toast(`Link de redefinição enviado para ${email}.`, 'green');
  closeForgotPasswordModal();

  // Aqui pode ser integrado futuramente com backend PHP + SMTP.
  console.log('Recuperação solicitada para:', user, email);
}
