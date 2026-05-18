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
- [Uso do Sistema](#uso-do-sistema)
- [API Endpoints](#api-endpoints)
- [Credenciais Padrão](#credenciais-padrão)
- [Capturas de Tela](#capturas-de-tela)
- [Contribuindo](#contribuindo)

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
  - Campos: nome, SKU, categoria, quantidade, estoque mínimo
  - Suporte a Certificado de Aprovação (CA): número, validade e descrição
- **🔍 Consulta de CA Automática** — Busca dados de validade e descrição do CA via integração com consultaca.com
- **📊 Filtros de Estoque** — Filtro por status: Normal, Baixo e Zerado
- **📈 Relatórios** — Painel com resumo geral, EPIs por categoria e alertas ativos
- **👥 Gerenciamento de Usuários** — Exclusivo para administradores: cadastrar, editar e remover usuários
- **💾 Persistência** — Dados armazenados em banco de dados MySQL via API PHP

---

## Tecnologias Utilizadas

| Camada     | Tecnologia                          |
|------------|-------------------------------------|
| Frontend   | HTML5, CSS3, JavaScript (Vanilla)   |
| Backend    | PHP 8+                              |
| Banco de Dados | MySQL / MariaDB                 |
| E-mail     | SMTP via Gmail (sem biblioteca externa) |
| Integração | Web scraping via cURL (consultaca.com) |

---

## Estrutura do Projeto

```
controle-estoque/
├── api/
│   ├── db.php               # Conexão com o banco de dados MySQL
│   ├── products.php          # CRUD de produtos (GET, POST, DELETE)
│   ├── users.php             # CRUD de usuários (GET, POST, DELETE)
│   ├── consultar_ca.php      # Consulta de CA via scraping no consultaca.com
│   ├── forgot_password.php   # Solicitação de redefinição de senha (envio de e-mail)
│   ├── reset_password.php    # Processamento do token e atualização de senha
│   └── proxy_ca.php          # Proxy auxiliar para requisições CA
├── css/
│   └── styles.css            # Estilos globais da aplicação
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
├── database.sql             # Script SQL para criação do banco de dados
├── index.html               # Página principal do sistema
└── reset_senha.html         # Página de redefinição de senha (via token)
```

---

## Pré-requisitos

- **Servidor web** com suporte a PHP 8.0+ (Apache, Nginx ou XAMPP/WAMP)
- **PHP** com as extensões habilitadas:
  - `mysqli`
  - `curl`
  - `dom` / `DOMDocument`
- **MySQL 5.7+** ou **MariaDB 10.3+**
- Navegador moderno (Chrome, Firefox, Edge)

---

## Instalação e Configuração

### 1. Clone o repositório

```bash
git clone https://github.com/seu-usuario/controle-estoque.git
```

### 2. Coloque o projeto no servidor

Copie a pasta para o diretório do seu servidor web:

```bash
# XAMPP (Windows)
C:\xampp\htdocs\controle-estoque

# WAMP (Windows)
C:\wamp64\www\controle-estoque

# Linux (Apache)
/var/www/html/controle-estoque
```

### 3. Configure a conexão com o banco de dados

Edite o arquivo `api/db.php` com as credenciais do seu banco:

```php
$host = 'localhost';   // Host do MySQL
$db   = 'unistock';   // Nome do banco de dados
$user = 'root';        // Usuário do MySQL
$pass = '';            // Senha do MySQL
```

---

## Configuração do Banco de Dados

### 1. Execute o script SQL

Acesse o MySQL (via phpMyAdmin, terminal ou qualquer cliente) e execute o arquivo `database.sql`:

```bash
mysql -u root -p < database.sql
```

Ou cole o conteúdo do arquivo diretamente no phpMyAdmin.

### O script cria:

- **Banco de dados** `unistock`
- **Tabela `usuarios`** — armazena os usuários do sistema (id, usuario, senha, nome, email)
- **Tabela `produtos`** — armazena os EPIs (id, nome, SKU, categoria, numero_ca, descricao_ca, validade_ca, quantidade, estoque_minimo)
- **Tabela `reset_tokens`** — tokens temporários para redefinição de senha
- **Usuário padrão** `admin` com senha `123456`

> ⚠️ **Importante:** Altere a senha do usuário `admin` após o primeiro acesso!

---

## Configuração de E-mail (Recuperação de Senha)

O sistema usa o Gmail via SMTP para enviar e-mails de redefinição de senha. Para ativar essa funcionalidade:

1. Acesse sua conta Google e habilite a [verificação em duas etapas](https://myaccount.google.com/security)
2. Gere uma [Senha de App](https://myaccount.google.com/apppasswords) para "Outro aplicativo"
3. Edite o arquivo `api/forgot_password.php` com suas credenciais:

```php
$SMTP_USER = 'seu-email@gmail.com';    // Seu endereço Gmail
$SMTP_PASS = 'sua_senha_de_app';       // Senha de app de 16 dígitos (sem espaços)
$BASE_URL  = 'http://localhost/controle-estoque'; // URL base do sistema
```

> O link de redefinição de senha expira automaticamente em **30 minutos**.

---

## Uso do Sistema

### Acessando o sistema

Abra o navegador e acesse:

```
http://localhost/controle-estoque/
```

### Tela de Login

- Insira seu **usuário** e **senha**
- Pressione **Enter** ou clique em "Entrar"
- Em caso de senha esquecida, clique em "Esqueci minha senha" e informe o usuário e e-mail cadastrado

### Gerenciamento de Produtos

- **Adicionar EPI**: clique no botão "Novo Produto" e preencha o formulário
- **Consultar CA**: ao informar o número do CA, clique em "Consultar CA" para buscar automaticamente a validade e a descrição
- **Editar**: clique no ícone de edição no card do produto
- **Excluir**: clique no ícone de exclusão (requer confirmação)
- **Filtrar**: use os botões de filtro para ver itens com estoque **Normal**, **Baixo** ou **Zerado**

### Relatórios

Acesse a aba **Relatório** para visualizar:
- Total de EPIs cadastrados
- Quantidade com estoque zerado, baixo, CA vencido ou CA próximo do vencimento
- EPIs agrupados por categoria (gráfico de barras)
- Lista de alertas ativos

### Gerenciamento de Usuários (Admin)

Visível apenas para o usuário `admin`:
- Adicionar novos usuários com nome, usuário, senha e e-mail
- Editar dados de usuários existentes
- Remover usuários (o usuário `admin` não pode ser removido)

---

## API Endpoints

Todos os endpoints estão na pasta `api/` e retornam JSON.

| Método   | Endpoint                      | Descrição                                    |
|----------|-------------------------------|----------------------------------------------|
| `GET`    | `api/products.php`            | Lista todos os produtos                      |
| `POST`   | `api/products.php`            | Cria ou atualiza um produto                  |
| `DELETE` | `api/products.php`            | Remove um produto por ID                     |
| `GET`    | `api/users.php`               | Lista todos os usuários                      |
| `POST`   | `api/users.php`               | Cria ou atualiza um usuário                  |
| `DELETE` | `api/users.php`               | Remove um usuário por ID                     |
| `GET`    | `api/consultar_ca.php?ca=NUM` | Consulta validade e descrição de um CA       |
| `POST`   | `api/forgot_password.php`     | Solicita redefinição de senha por e-mail     |
| `POST`   | `api/reset_password.php`      | Redefine a senha com token válido            |

---

## Credenciais Padrão

| Usuário   | Senha     | Perfil        |
|-----------|-----------|---------------|
| `admin`   | `123456`  | Administrador |
| `gerente` | `estoque` | Usuário comum |

> ⚠️ **Altere as senhas padrão imediatamente após a instalação!**

---

## Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. Faça um **fork** do projeto
2. Crie uma branch para sua feature: `git checkout -b feature/minha-feature`
3. Faça o commit das suas alterações: `git commit -m 'feat: adiciona minha feature'`
4. Envie para o repositório remoto: `git push origin feature/minha-feature`
5. Abra um **Pull Request**

---

## 📄 Licença

Este projeto foi desenvolvido como **projeto de extensão universitária** e é de uso livre para fins educacionais.

---

<p align="center">Desenvolvido com 💙 como projeto de extensão universitária</p>
