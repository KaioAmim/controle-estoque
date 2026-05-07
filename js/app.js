/* ════════════════════════════════════════════════════
   app.js — Ponto de entrada e inicialização do sistema.

   Este é o último script carregado pelo index.html.
   Garante que todos os módulos estejam disponíveis
   antes de executar o bootstrap da aplicação.

   Depende de: data.js (seed)
════════════════════════════════════════════════════ */

/**
 * Inicializa o sistema ao carregar a página.
 * A função seed() verifica se há dados no localStorage;
 * se não houver, insere os produtos de demonstração.
 *
 * O usuário verá a tela de login, pois #app-screen
 * só fica visível após a autenticação (auth.js > doLogin).
 */
seed();
