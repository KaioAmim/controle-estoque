/* ════════════════════════════════════════════════════
   data.js — Dados, banco de dados (localStorage) e
   funções utilitárias compartilhadas pelo sistema.

   Este arquivo deve ser carregado PRIMEIRO, pois todos
   os outros módulos dependem das funções aqui definidas.
════════════════════════════════════════════════════ */

/* ── USUÁRIOS DO SISTEMA ─────────────────────────
   Lista estática de usuários para autenticação.
──────────────────────────────────────────────────── */
const USERS = [
  { u: 'admin',   p: '123456', n: 'Admin'   },
  { u: 'gerente', p: 'estoque', n: 'Gerente' },
];

/* ── BASE DE DADOS LOCAL DE CAs ──────────────────
   Removida a base estática para priorizar a consulta real.
──────────────────────────────────────────────────── */
const CA_DB = {};

/* Data e hora atual (usada como referência para status de validade de CA) */
const now = new Date();

/* ── VARIÁVEIS GLOBAIS DE ESTADO ─────────────────
   Controlam qual produto está sendo editado/excluído
   e o estado de autenticação.
──────────────────────────────────────────────────── */
let editId      = null; // ID do produto em edição (null = novo produto)
let delId       = null; // ID do produto a ser excluído
let caCache     = null; // Dados do CA consultado (para evitar re-consulta)
let toastTimer  = null; // Referência ao timer do toast (para cancelamento)
let currentUser = null; // Objeto do usuário logado


/* ════════════════════════════════════════════════
   FUNÇÕES UTILITÁRIAS
════════════════════════════════════════════════ */

/**
 * Retorna a data daqui a N dias no formato "YYYY-MM-DD".
 * @param {number} d - Quantidade de dias a partir de hoje
 * @returns {string} Data no formato ISO (YYYY-MM-DD)
 */
function daysFromNow(d) {
  const x = new Date(now);
  x.setDate(x.getDate() + d);
  return x.toISOString().slice(0, 10);
}

/**
 * Lê o array de produtos do localStorage.
 * @returns {Array} Lista de produtos
 */
function gDB() {
  try {
    return JSON.parse(localStorage.getItem('stockos_v3') || '[]');
  } catch {
    return [];
  }
}

/**
 * Salva o array de produtos no localStorage.
 * @param {Array} d - Array de produtos a salvar
 */
function sDB(d) {
  localStorage.setItem('stockos_v3', JSON.stringify(d));
}

/**
 * Gera um ID único baseado em timestamp + aleatoriedade.
 * @returns {string} ID único
 */
function uid() {
  return Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
}

/**
 * Escapa caracteres HTML para evitar XSS.
 * @param {*} s - Valor a escapar
 * @returns {string} String segura
 */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[c]));
}

/**
 * Formata uma data ISO "YYYY-MM-DD" para o padrão brasileiro "DD/MM/AAAA".
 * @param {string|null} v - Data no formato ISO
 * @returns {string} Data formatada ou "—"
 */
function fmt(v) {
  if (!v) return '—';
  const [y, m, d] = v.split('-');
  return `${d}/${m}/${y}`;
}


/* ════════════════════════════════════════════════
   FUNÇÕES DE STATUS (regras de negócio)
════════════════════════════════════════════════ */

/**
 * Retorna o status de validade de um CA.
 */
function caStatus(val) {
  if (!val) return { cls: 'badge-none', lbl: 'Sem CA' };
  const d = new Date(val + 'T00:00:00');
  const diff = (d - now) / 864e5; // diferença em dias
  if (diff < 0)    return { cls: 'badge-danger', lbl: 'Vencido'   };
  if (diff <= 60)  return { cls: 'badge-warn',   lbl: 'Vencendo'  };
  return { cls: 'badge-ok', lbl: 'Válido' };
}

/**
 * Retorna o status de quantidade em estoque.
 */
function estqStatus(qty, min) {
  if (qty === 0)   return { cls: 'badge-danger', lbl: 'Zerado' };
  if (qty <= min)  return { cls: 'badge-warn',   lbl: 'Baixo'  };
  return { cls: 'badge-ok', lbl: 'Normal' };
}


/* ════════════════════════════════════════════════
   SEED DE DADOS DE DEMONSTRAÇÃO
   (Desativado para não poluir com dados falsos)
════════════════════════════════════════════════ */

function seed() {
  // Não faz nada para iniciar o sistema limpo
  // Se quiser dados de exemplo, adicione-os aqui.
}
