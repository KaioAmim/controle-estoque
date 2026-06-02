# 🦺 UniStock — Sistema de Controle de Estoque de EPIs

> Projeto de extensão universitária para gerenciamento de Equipamentos de Proteção Individual (EPIs), com controle de estoque, validação de Certificado de Aprovação (CA) e gestão de usuários.

---

## 📋 Índice

- [Sobre o Projeto](#sobre-o-projeto)
- [Funcionalidades](#funcionalidades)
- [Tecnologias Utilizadas](#tecnologias-utilizadas)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Pré-requisitos](#pré-requisitos)
- [Instalação e Configuração](#instalação-e-configuração)
- [Configuração do Banco de Dados](#configuração-do-banco-de-dados)
- [Configuração de E-mail (Recuperação de Senha)](#configuração-de-e-mail-recuperação-de-senha)
- [Deploy no Render](#deploy-no-render)
- [Uso do Sistema](#uso-do-sistema)
- [API Endpoints](#api-endpoints)
- [Credenciais Padrão](#credenciais-padrão)

---

## Sobre o Projeto

O **UniStock** é um sistema web voltado para o gerenciamento de estoque de EPIs em ambientes corporativos ou institucionais. Ele permite cadastrar produtos com suas respectivas informações de Certificado de Aprovação (CA), monitorar quantidades em estoque, gerar alertas para itens com estoque baixo ou zerado, e consultar automaticamente a validade e descrição dos CAs diretamente do site [ConsultaCA](https://consultaca.com).

O sistema possui dois perfis de acesso: **administrador** (acesso completo, incluindo gerenciamento de usuários) e **usuário comum** (visualização e gestão de produtos).

---

## Funcionalidades

- **🔐 Autenticação** — Login com usuário e senha, logout seguro
- **🔑 Recuperação de Senha** — Redefinição via e-mail com link temporário (expira em 30 minutos)
- **📦 Gestão de Produtos (EPIs)**
  - Cadastro, edição e exclusão de produtos
  - Campos: nome, categoria, quantidade, estoque mínimo
  - Suporte a Certificado de Aprovação (CA): número, validade e descrição
- **🔍 Consulta de CA Automática** — Busca dados de validade e descrição do CA via integração com consultaca.com
- **📊 Filtros de Estoque** — Filtro por status: Normal, Baixo e Zerado
- **📈 Relatórios** — Painel com resumo geral, EPIs por categoria e alertas ativos
- **👥 Gerenciamento de Usuários** — Exclusivo para administradores: cadastrar, editar e remover usuários
- **💾 Persistência** — Dados armazenados em banco de dados MySQL via API PHP

---

## Tecnologias Utilizadas

| Camada         | Tecnologia                          |
|----------------|-------------------------------------|
| Frontend       | HTML5, CSS3, JavaScript (Vanilla)   |
| Backend        | PHP 8+                              |
| Banco de Dados | MySQL / MariaDB                     |
| E-mail         | Brevo API (HTTP — sem SMTP)         |
| Integração     | Web scraping via cURL (consultaca.com) |
| Deploy         | Render (Docker / Apache + PHP)      |

---

## Estrutura do Projeto

```
controle-estoque/
├── api/
│   ├── db.php               # Conexão com o banco de dados MySQL
│   ├── products.php         # CRUD de produtos (GET, POST, DELETE)
│   ├── users.php            # CRUD de usuários (GET, POST, DELETE)
│   ├── consultar_ca.php     # Consulta de CA via scraping no consultaca.com
│   ├── forgot_password.php  # Solicitação de redefinição de senha (Brevo API)
│   ├── reset_password.php   # Processamento do token e atualização de senha
│   └── proxy_ca.php         # Proxy auxiliar para requisições CA
├── css/
│   └── styles.css           # Estilos globais da aplicação
├── img/
│   └── (logos e imagens do projeto)
├── js/
│   ├── app.js               # Ponto de entrada — bootstrap da aplicação
│   ├── auth.js              # Lógica de login, logout e recuperação de senha
│   ├── data.js              # Dados globais, localStorage e funções utilitárias
│   ├── products.js          # Renderização e lógica da view de produtos
│   ├── report.js            # Renderização da view de relatórios
│   ├── ui.js                # Navegação entre views e helpers de UI
│   └── users.js             # Gerenciamento de usuários (admin)
├── Dockerfile               # Configuração para deploy via Docker (Render)
├── database.sql             # Script SQL para criação do banco de dados
├── .env.example             # Exemplo de variáveis de ambiente
├── index.html               # Página principal do sistema
└── reset_senha.html         # Página de redefinição de senha (via token)
```

---

## Pré-requisitos

- **Servidor web** com suporte a PHP 8.0+ (Apache, Nginx ou XAMPP/WAMP)
- **PHP** com as extensões habilitadas:
  - `mysqli`
  - `curl`
- **MySQL 5.7+** ou **MariaDB 10.3+**
- Navegador moderno (Chrome, Firefox, Edge)

---

## Instalação e Configuração

### 1. Clone o repositório

```bash
git clone https://github.com/KaioAmim/controle-estoque.git
cd controle-estoque
```

### 2. Configure o arquivo `.env`

Copie o exemplo e preencha com suas credenciais:

```bash
cp .env.example .env
```

```env
# Banco de Dados
DB_HOST=seu-host-mysql
DB_NAME=unistock
DB_USER=seu-usuario
DB_PASS=sua-senha
DB_PORT=3306

# Brevo API (envio de e-mail)
BREVO_API_KEY=xkeysib-...
BREVO_FROM=seu-email-verificado@dominio.com

# URL base do sistema (sem barra no final)
BASE_URL=https://seu-dominio.com
```

### 3. Coloque o projeto no servidor

```bash
# XAMPP (Windows)
C:\xampp\htdocs\controle-estoque

# Linux (Apache)
/var/www/html/controle-estoque
```

---

## Configuração do Banco de Dados

### Execute o script SQL

```bash
mysql -u root -p < database.sql
```

Ou cole o conteúdo do arquivo `database.sql` diretamente no phpMyAdmin.

### O script cria:

- **Banco de dados** `unistock`
- **Tabela `usuarios`** — armazena os usuários do sistema
- **Tabela `produtos`** — armazena os EPIs
- **Tabela `reset_tokens`** — tokens temporários para redefinição de senha
- **Usuário padrão** `admin` com senha `123456`

> ⚠️ **Importante:** Altere a senha do usuário `admin` após o primeiro acesso!

---

## Configuração de E-mail (Recuperação de Senha)

O sistema utiliza a **API HTTP do Brevo** para envio de e-mails — sem dependência de SMTP, compatível com qualquer hospedagem (incluindo Render).

### 1. Crie uma conta no Brevo

Acesse [brevo.com](https://www.brevo.com) e crie uma conta gratuita (300 e-mails/dia).

### 2. Gere uma API Key

Vá em **Configurações → SMTP & API → API Keys → Generate a new API key**.

A chave começa com `xkeysib-...`

### 3. Verifique o remetente

Vá em **Configurações → Remetentes, Domínios e IPs → Adicionar remetente** e confirme o e-mail que será usado como remetente.

### 4. Configure as variáveis de ambiente

```env
BREVO_API_KEY=xkeysib-sua-chave-aqui
BREVO_FROM=email-verificado@dominio.com
```

> O link de redefinição de senha expira automaticamente em **30 minutos**.

---

## Deploy no Render

O projeto está configurado para deploy via Docker no [Render](https://render.com).

### Variáveis de ambiente obrigatórias no painel do Render

| Variável        | Descrição                              |
|-----------------|----------------------------------------|
| `DB_HOST`       | Host do banco de dados MySQL           |
| `DB_NAME`       | Nome do banco                          |
| `DB_USER`       | Usuário do banco                       |
| `DB_PASS`       | Senha do banco                         |
| `DB_PORT`       | Porta do banco (padrão: 3306)          |
| `BASE_URL`      | URL pública do sistema no Render       |
| `BREVO_API_KEY` | Chave da API do Brevo                  |
| `BREVO_FROM`    | E-mail remetente verificado no Brevo   |

> ⚠️ **Nunca suba o arquivo `.env` para o repositório.** Ele já está no `.gitignore`. Configure as variáveis diretamente no painel do Render.

---

## Uso do Sistema

### Tela de Login

- Insira seu **usuário** e **senha** e clique em "Entrar"
- Em caso de senha esquecida, clique em **"Esqueci minha senha"** e informe o usuário e e-mail cadastrado — você receberá um link por e-mail

### Gerenciamento de Produtos

- **Adicionar EPI**: clique em "Novo Produto" e preencha o formulário
- **Consultar CA**: ao informar o número do CA, clique em "Consultar CA" para buscar automaticamente validade e descrição
- **Editar / Excluir**: use os ícones no card do produto
- **Filtrar**: use os botões para ver itens com estoque **Normal**, **Baixo** ou **Zerado**

### Relatórios

Acesse a aba **Relatório** para visualizar:
- Total de EPIs cadastrados
- Quantidade com estoque zerado, baixo, CA vencido ou CA próximo do vencimento
- EPIs agrupados por categoria
- Lista de alertas ativos

### Gerenciamento de Usuários (Admin)

Visível apenas para o perfil `admin`:
- Adicionar, editar e remover usuários
- O usuário `admin` não pode ser removido

---

## API Endpoints

Todos os endpoints estão na pasta `api/` e retornam JSON.

| Método   | Endpoint                      | Descrição                                |
|----------|-------------------------------|------------------------------------------|
| `GET`    | `api/products.php`            | Lista todos os produtos                  |
| `POST`   | `api/products.php`            | Cria ou atualiza um produto              |
| `DELETE` | `api/products.php`            | Remove um produto por ID                 |
| `GET`    | `api/users.php`               | Lista todos os usuários                  |
| `POST`   | `api/users.php`               | Cria ou atualiza um usuário              |
| `DELETE` | `api/users.php`               | Remove um usuário por ID                 |
| `GET`    | `api/consultar_ca.php?ca=NUM` | Consulta validade e descrição de um CA   |
| `POST`   | `api/forgot_password.php`    | Solicita redefinição de senha por e-mail |
| `POST`   | `api/reset_password.php`      | Redefine a senha com token válido        |

---

## Credenciais Padrão

| Usuário   | Senha     | Perfil        |
|-----------|-----------|---------------|
| `admin`   | `123456`  | Administrador |
| `gerente` | `estoque` | Usuário comum |

> ⚠️ **Altere as senhas padrão imediatamente após a instalação!**
